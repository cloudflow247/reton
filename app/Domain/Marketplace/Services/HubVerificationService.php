<?php

declare(strict_types=1);

namespace App\Domain\Marketplace\Services;

use App\Domain\Marketplace\Enums\HubVerificationStatus;

/**
 * Scores whether a physical item at the Giglogistics hub matches the locked
 * listing snapshot — the objective basis for escrow judgement.
 */
class HubVerificationService
{
    /**
     * @param  array<string, mixed>  $listingSnapshot
     * @param  array<string, mixed>  $hubFindings  weight_grams, condition, brand, detail, notes
     * @return array{status: HubVerificationStatus, score: int, report: array<string, mixed>}
     */
    public function evaluate(array $listingSnapshot, array $hubFindings): array
    {
        $checks = [];
        $score = 0;
        $max = 0;

        $max += 25;
        $weightOk = abs((int) ($hubFindings['weight_grams'] ?? 0) - (int) ($listingSnapshot['weight_grams'] ?? 0)) <= 50;
        $checks['weight'] = ['expected' => $listingSnapshot['weight_grams'] ?? null, 'found' => $hubFindings['weight_grams'] ?? null, 'passed' => $weightOk];
        $score += $weightOk ? 25 : 0;

        $max += 25;
        $conditionOk = ($hubFindings['condition'] ?? '') === ($listingSnapshot['condition'] ?? '');
        $checks['condition'] = ['expected' => $listingSnapshot['condition'] ?? null, 'found' => $hubFindings['condition'] ?? null, 'passed' => $conditionOk];
        $score += $conditionOk ? 25 : 0;

        $specs = (array) ($listingSnapshot['specs'] ?? []);
        foreach (['brand', 'detail'] as $key) {
            $max += 20;
            $expected = trim((string) ($specs[$key] ?? ''));
            $found = trim((string) ($hubFindings[$key] ?? $hubFindings['spec_'.$key] ?? ''));
            $passed = $expected === '' || strcasecmp($expected, $found) === 0;
            $checks[$key] = ['expected' => $expected ?: null, 'found' => $found ?: null, 'passed' => $passed];
            $score += $passed ? 20 : 0;
        }

        $max += 10;
        $titleMatch = str_contains(
            strtolower((string) ($hubFindings['item_label'] ?? '')),
            strtolower(substr((string) ($listingSnapshot['title'] ?? ''), 0, 12)),
        ) || strlen((string) ($listingSnapshot['title'] ?? '')) < 5;
        $checks['title'] = ['expected' => $listingSnapshot['title'] ?? null, 'found' => $hubFindings['item_label'] ?? null, 'passed' => $titleMatch];
        $score += $titleMatch ? 10 : 0;

        $normalized = $max > 0 ? (int) round(($score / $max) * 100) : 0;
        $passThreshold = (int) config('reton.physical.hub_verification_pass_score', 80);

        $status = $normalized >= $passThreshold ? HubVerificationStatus::Passed : HubVerificationStatus::Failed;

        return [
            'status' => $status,
            'score' => $normalized,
            'report' => [
                'checks' => $checks,
                'score' => $normalized,
                'pass_threshold' => $passThreshold,
                'inspector_notes' => (string) ($hubFindings['notes'] ?? 'Giglogistics hub inspection completed.'),
                'verified_at' => now()->toIso8601String(),
            ],
        ];
    }
}
