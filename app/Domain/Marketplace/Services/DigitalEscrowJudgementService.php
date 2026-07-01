<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Callback\Enums\CallbackResolution;
use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Data\FraudContext;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Marketplace\Enums\DigitalDisputeCategory;
use App\Domain\Marketplace\Enums\DigitalOrderStatus;
use App\Domain\Marketplace\Exceptions\MarketplaceException;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Transfers\Models\Transfer;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Fair escrow judgement for peer-to-peer digital item trades.
 *
 * Combines order state, dispute category, delivery deadlines, seller trust history,
 * and fraud signals to decide when disputes are allowed and how unanswered callbacks resolve.
 */
class DigitalEscrowJudgementService
{
    public function __construct(private readonly FraudScorer $fraud) {}

    public function sellerTrustScore(User $seller): int
    {
        $completed = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Completed)
            ->count();

        $refunded = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Refunded)
            ->count();

        $disputed = DigitalOrder::query()
            ->where('seller_id', $seller->getKey())
            ->where('status', DigitalOrderStatus::Disputed)
            ->count();

        $total = $completed + $refunded + $disputed;

        if ($total === 0) {
            return 70;
        }

        $successRate = $completed / max(1, $total);
        $score = (int) round(50 + ($successRate * 50));
        $score -= min(30, $refunded * 8);
        $score -= min(20, $disputed * 4);

        return max(0, min(100, $score));
    }

    public function buyerTrustScore(User $buyer): int
    {
        $completed = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Completed)
            ->count();

        $refunded = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Refunded)
            ->count();

        $disputed = DigitalOrder::query()
            ->where('buyer_id', $buyer->getKey())
            ->where('status', DigitalOrderStatus::Disputed)
            ->count();

        $total = $completed + $refunded + $disputed;

        if ($total === 0) {
            return 75;
        }

        $fairRate = $completed / max(1, $total);
        $score = (int) round(55 + ($fairRate * 45));
        $score -= min(35, $refunded * 6);
        $score -= min(25, $disputed * 5);

        return max(0, min(100, $score));
    }

    public function assertCanRaiseDispute(DigitalOrder $order, User $buyer, DigitalDisputeCategory $category): void
    {
        if ((string) $buyer->getKey() !== (string) $order->buyer_id) {
            throw MarketplaceException::wrongOrderState('buyer');
        }

        if (! in_array($order->status, [DigitalOrderStatus::PaidHeld, DigitalOrderStatus::Delivered], true)) {
            throw MarketplaceException::disputeNotAllowed();
        }

        $allowed = DigitalDisputeCategory::forOrderStatus($order->status);

        if (! in_array($category, $allowed, true)) {
            throw MarketplaceException::disputeCategoryMismatch($category->label());
        }

        if ($category === DigitalDisputeCategory::NotDelivered) {
            $graceHours = (int) config('reton.digital.dispute_grace_hours', 24);
            $graceEnds = $order->created_at?->copy()->addHours($graceHours);

            if ($graceEnds instanceof Carbon && now()->lt($graceEnds)) {
                throw MarketplaceException::disputeTooEarly($graceHours);
            }
        }

        if (in_array($category, [DigitalDisputeCategory::NotAsDescribed, DigitalDisputeCategory::InvalidItem], true)
            && $order->status !== DigitalOrderStatus::Delivered) {
            throw MarketplaceException::notDeliveredYet();
        }
    }

    public function assertCanInitiateGenericCallback(DigitalOrder $order, User $buyer, string $reason): DigitalDisputeCategory
    {
        if ($order->status === DigitalOrderStatus::Delivered) {
            throw MarketplaceException::useStructuredDispute();
        }

        $this->assertCanRaiseDispute($order, $buyer, DigitalDisputeCategory::NotDelivered);

        return DigitalDisputeCategory::NotDelivered;
    }

    /**
     * Decide who wins when a digital-item callback expires unanswered.
     */
    public function resolveOnCallbackExpiry(Callback $callback, DigitalOrder $order): CallbackResolution
    {
        $transfer = $callback->transfer;
        $category = DigitalDisputeCategory::tryFrom((string) ($order->dispute_category ?? ''));
        $seller = User::find($order->seller_id);
        $buyer = User::find($order->buyer_id);

        $sellerHighRisk = $seller instanceof User && $this->userIsHighRisk($seller, $transfer, 'callback_expiry');
        $buyerHighRisk = $buyer instanceof User && $this->userIsHighRisk($buyer, $transfer, 'callback_expiry');
        $sellerTrust = $seller instanceof User ? $this->sellerTrustScore($seller) : 70;
        $buyerTrust = $buyer instanceof User ? $this->buyerTrustScore($buyer) : 75;

        if ($sellerHighRisk) {
            return CallbackResolution::Refund;
        }

        if ($category === DigitalDisputeCategory::NotDelivered) {
            if (! $order->isDelivered()) {
                return CallbackResolution::Refund;
            }

            if ($buyerHighRisk && $sellerTrust >= 60) {
                return CallbackResolution::Release;
            }

            return CallbackResolution::Refund;
        }

        if (in_array($category, [DigitalDisputeCategory::NotAsDescribed, DigitalDisputeCategory::InvalidItem], true)) {
            if ($buyerHighRisk && $sellerTrust >= 75 && ! $sellerHighRisk) {
                return CallbackResolution::Release;
            }

            if ($sellerTrust < 45) {
                return CallbackResolution::Refund;
            }

            return CallbackResolution::Refund;
        }

        if ($buyerHighRisk && $sellerTrust >= 70) {
            return CallbackResolution::Release;
        }

        if ($buyerTrust >= 80 && $sellerTrust < 50) {
            return CallbackResolution::Refund;
        }

        return CallbackResolution::Refund;
    }

    /**
     * @return array<string, mixed>
     */
    public function guidanceFor(DigitalOrder $order, User $viewer): array
    {
        $role = $this->roleFor($order, $viewer);
        $sellerTrust = $this->sellerTrustScore(User::find($order->seller_id) ?? $viewer);
        $confirmDeadline = $order->transfer?->hold?->expires_at;
        $graceHours = (int) config('reton.digital.dispute_grace_hours', 24);
        $graceEnds = $order->created_at?->copy()->addHours($graceHours);
        $allowedDisputes = array_map(
            fn (DigitalDisputeCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'hint' => $c->hint(),
            ],
            DigitalDisputeCategory::forOrderStatus($order->status),
        );

        $step = match ($order->status) {
            DigitalOrderStatus::PaidHeld => 1,
            DigitalOrderStatus::Delivered => 2,
            DigitalOrderStatus::Completed => 4,
            DigitalOrderStatus::Refunded => 4,
            DigitalOrderStatus::Disputed => 3,
        };

        $stepLabel = match ($order->status) {
            DigitalOrderStatus::PaidHeld => 'Waiting for delivery',
            DigitalOrderStatus::Delivered => 'Review your item',
            DigitalOrderStatus::Disputed => 'Dispute in progress',
            DigitalOrderStatus::Completed => 'Complete',
            DigitalOrderStatus::Refunded => 'Refunded',
        };

        $nextAction = match (true) {
            $role === 'seller' && $order->status === DigitalOrderStatus::PaidHeld => 'seller_deliver',
            $role === 'buyer' && $order->status === DigitalOrderStatus::Delivered => 'buyer_confirm',
            $role === 'buyer' && $order->status === DigitalOrderStatus::PaidHeld && $this->canDisputeNotDelivered($order) => 'buyer_dispute_not_delivered',
            $order->status === DigitalOrderStatus::Disputed => 'await_resolution',
            default => null,
        };

        return [
            'step' => $step,
            'step_label' => $stepLabel,
            'next_action' => $nextAction,
            'allowed_disputes' => $allowedDisputes,
            'delivery_deadline_at' => $order->delivery_deadline_at,
            'confirm_deadline_at' => $confirmDeadline,
            'dispute_grace_ends_at' => $graceEnds,
            'can_dispute_not_delivered' => $role === 'buyer' && $this->canDisputeNotDelivered($order),
            'can_dispute_quality' => $role === 'buyer' && $order->status === DigitalOrderStatus::Delivered,
            'auto_refund_at' => $order->status === DigitalOrderStatus::PaidHeld ? $order->delivery_deadline_at : null,
            'seller_trust_score' => $sellerTrust,
            'listing_description' => $role === 'buyer' && $order->isDelivered()
                ? $order->listing?->description
                : null,
        ];
    }

    public function canDisputeNotDelivered(DigitalOrder $order): bool
    {
        if ($order->status !== DigitalOrderStatus::PaidHeld) {
            return false;
        }

        $graceHours = (int) config('reton.digital.dispute_grace_hours', 24);
        $graceEnds = $order->created_at?->copy()->addHours($graceHours);

        return $graceEnds instanceof Carbon && now()->gte($graceEnds);
    }

    public function payloadChecksum(string $payload): string
    {
        return hash('sha256', $payload);
    }

    private function roleFor(DigitalOrder $order, User $viewer): ?string
    {
        if ((string) $viewer->getKey() === (string) $order->buyer_id) {
            return 'buyer';
        }

        if ((string) $viewer->getKey() === (string) $order->seller_id) {
            return 'seller';
        }

        return null;
    }

    private function userIsHighRisk(User $user, ?Transfer $transfer, string $action): bool
    {
        if ($transfer === null) {
            return false;
        }

        $wallet = $user->wallets()->where('currency', $transfer->currency)->first();

        if ($wallet === null) {
            return false;
        }

        $assessment = $this->fraud->score(new FraudContext(
            user: $user,
            wallet: $wallet,
            amount: Money::of($transfer->amount, $transfer->currency),
            action: $action,
        ));

        return $assessment->level === FraudRiskLevel::High;
    }
}
