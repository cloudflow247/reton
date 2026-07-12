<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

trait RendersComingSoon
{
    /**
     * Must match defaults in config/reton.php features.
     *
     * @var array<string, bool>
     */
    private const FEATURE_DEFAULTS = [
        'withdraw' => false,
        'bills' => false,
        'cards' => false,
    ];

    /**
     * @param  array{title: string, description: string, cta_label?: string, cta_href?: string}  $copy
     */
    protected function comingSoonIfDisabled(string $feature, array $copy): ?Response
    {
        if ($this->featureEnabled($feature)) {
            return null;
        }

        return Inertia::render('ComingSoon', [
            'feature' => $feature,
            'title' => $copy['title'],
            'description' => $copy['description'],
            'ctaLabel' => $copy['cta_label'] ?? 'Back to Home',
            'ctaHref' => $copy['cta_href'] ?? '/dashboard',
        ]);
    }

    protected function denyIfComingSoon(string $feature, string $message): ?RedirectResponse
    {
        if ($this->featureEnabled($feature)) {
            return null;
        }

        return back()->with('error', $message);
    }

    protected function featureEnabled(string $feature): bool
    {
        $fallback = self::FEATURE_DEFAULTS[$feature] ?? false;

        return (bool) config("reton.features.{$feature}", $fallback);
    }
}
