<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Exceptions;

use App\Support\Exceptions\RenderableApiException;
use DomainException;

final class MarketplaceException extends DomainException implements RenderableApiException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'marketplace_error',
    ) {
        parent::__construct($message);
    }

    public static function cannotBuyOwnListing(): self
    {
        return new self('You cannot purchase your own listing.', 'marketplace_own_listing');
    }

    public static function listingUnavailable(): self
    {
        return new self('This listing is no longer available.', 'listing_unavailable');
    }

    public static function notDeliveredYet(): self
    {
        return new self('The seller must deliver the digital item before you can release payment.', 'awaiting_delivery');
    }

    public static function alreadyDelivered(): self
    {
        return new self('This order has already been marked as delivered.', 'already_delivered');
    }

    public static function wrongOrderState(string $expected): self
    {
        return new self("Order is not in the expected state ({$expected}).", 'invalid_order_state');
    }

    public static function deliveryAttestationRequired(): self
    {
        return new self('Confirm that what you deliver matches your listing before marking delivered.', 'delivery_attestation_required');
    }

    public static function disputeNotAllowed(): self
    {
        return new self('This order cannot be disputed in its current state.', 'dispute_not_allowed');
    }

    public static function disputeCategoryMismatch(string $category): self
    {
        return new self("That dispute type is not available right now ({$category}).", 'dispute_category_mismatch');
    }

    public static function disputeTooEarly(int $graceHours): self
    {
        return new self("Give the seller at least {$graceHours} hours to deliver before disputing non-delivery.", 'dispute_too_early');
    }

    public static function useStructuredDispute(): self
    {
        return new self('Choose a dispute reason (does not match, invalid item) instead of a generic callback.', 'use_structured_dispute');
    }

    public static function buyerMustAcceptDescription(): self
    {
        return new self('Confirm you have read and accept the item description before paying.', 'buyer_description_acceptance_required');
    }

    public static function shippingAddressRequired(): self
    {
        return new self('A complete delivery address is required for physical items.', 'shipping_address_required');
    }

    public static function listingVerificationFailed(): self
    {
        return new self('This listing did not pass Reton verification. Update the description and try again.', 'listing_verification_failed');
    }

    public static function alreadyShipped(): self
    {
        return new self('This order has already been handed to Giglogistics.', 'already_shipped');
    }

    public static function notShippedYet(): self
    {
        return new self('The item must be delivered by Giglogistics before you can release payment.', 'awaiting_carrier_delivery');
    }

    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return $this->errorCode;
    }
}
