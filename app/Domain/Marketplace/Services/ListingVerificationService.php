<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Enums\ItemType;
use App\Domain\Marketplace\Enums\VerificationStatus;
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;

/**
 * Scores listing descriptions for completeness and honesty signals before
 * buyers commit — the basis for fair escrow judgement later.
 */
class ListingVerificationService
{
    /**
     * @return array{status: VerificationStatus, score: int, notes: string}
     */
    public function verifyListing(DigitalListing $listing): array
    {
        if ($listing->item_type === ItemType::Digital || $listing->item_type === null) {
            return $this->verifyDigitalListing($listing);
        }

        return $this->verifyPhysicalListing($listing);
    }

    /**
     * Re-verify the frozen snapshot at purchase time.
     *
     * @return array{status: VerificationStatus, score: int, notes: string}
     */
    public function verifyOrderSnapshot(DigitalOrder $order): array
    {
        $snapshot = $order->listing_snapshot ?? [];

        if (($snapshot['item_type'] ?? ItemType::Digital->value) === ItemType::Digital->value) {
            return [
                'status' => VerificationStatus::Passed,
                'score' => 85,
                'notes' => 'Digital order snapshot locked at purchase.',
            ];
        }

        $score = 55;
        $notes = [];

        if (strlen((string) ($snapshot['description'] ?? '')) >= 80) {
            $score += 15;
        } else {
            $notes[] = 'Description could be more detailed.';
        }

        $specs = (array) ($snapshot['specs'] ?? []);
        $filledSpecs = count(array_filter($specs, fn ($v) => trim((string) $v) !== ''));

        if ($filledSpecs >= 2) {
            $score += 15;
        } else {
            $notes[] = 'Add at least two product specifications.';
        }

        if (! empty($snapshot['condition'])) {
            $score += 10;
        }

        if (($snapshot['weight_grams'] ?? 0) > 0) {
            $score += 10;
        }

        if (! empty($snapshot['handling_notes'])) {
            $score += 5;
        }

        $passThreshold = (int) config('reton.physical.verification_pass_score', 70);
        $status = $score >= $passThreshold ? VerificationStatus::Passed : VerificationStatus::Flagged;

        return [
            'status' => $status,
            'score' => min(100, $score),
            'notes' => $notes === [] ? 'Order description verified against listing snapshot.' : implode(' ', $notes),
        ];
    }

    /**
     * Compare what the buyer received against the locked listing snapshot.
     */
    public function descriptionMatchScore(DigitalOrder $order): int
    {
        $snapshot = $order->listing_snapshot ?? [];
        $listing = $order->listing;

        if ($listing === null) {
            return 50;
        }

        $snapshotChecksum = (string) ($snapshot['checksum'] ?? '');
        $currentChecksum = $listing->toSnapshot()['checksum'];

        if ($snapshotChecksum !== '' && $snapshotChecksum === $currentChecksum) {
            return 90;
        }

        return 40;
    }

    /**
     * @return array{status: VerificationStatus, score: int, notes: string}
     */
    private function verifyDigitalListing(DigitalListing $listing): array
    {
        $score = 70;

        if (strlen($listing->description) >= 30) {
            $score += 10;
        }

        if (strlen((string) $listing->delivery_payload) >= 5) {
            $score += 10;
        }

        if (strlen($listing->title) >= 8) {
            $score += 10;
        }

        return [
            'status' => VerificationStatus::Passed,
            'score' => min(100, $score),
            'notes' => 'Digital listing ready for protected sale.',
        ];
    }

    /**
     * @return array{status: VerificationStatus, score: int, notes: string}
     */
    private function verifyPhysicalListing(DigitalListing $listing): array
    {
        $score = 50;
        $notes = [];

        if (strlen($listing->description) >= 80) {
            $score += 15;
        } else {
            $notes[] = 'Physical listings need a thorough description (80+ characters).';
        }

        if ($listing->condition !== null) {
            $score += 10;
        } else {
            $notes[] = 'Specify item condition.';
        }

        if (($listing->weight_grams ?? 0) > 0) {
            $score += 10;
        } else {
            $notes[] = 'Weight is required for Giglogistics shipping.';
        }

        $specs = (array) ($listing->specs ?? []);
        $filledSpecs = count(array_filter($specs, fn ($v) => trim((string) $v) !== ''));

        if ($filledSpecs >= 2) {
            $score += 15;
        } else {
            $notes[] = 'Add at least two specifications (brand, size, colour, etc.).';
        }

        if (! empty($listing->handling_notes)) {
            $score += 5;
        }

        $passThreshold = (int) config('reton.physical.verification_pass_score', 70);
        $status = $score >= $passThreshold ? VerificationStatus::Passed : VerificationStatus::Flagged;

        return [
            'status' => $status,
            'score' => min(100, $score),
            'notes' => $notes === [] ? 'Listing passed Reton verification.' : implode(' ', $notes),
        ];
    }
}
