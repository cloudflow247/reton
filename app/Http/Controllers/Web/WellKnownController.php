<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves mobile association files for Universal Links (iOS) and App Links (Android).
 *
 * Paths must match ListingLinks::path() so installed apps open /l/{id} in-app.
 */
class WellKnownController extends Controller
{
    public function appleAppSiteAssociation(): JsonResponse
    {
        $teamId = (string) config('reton.links.mobile.apple_team_id');
        $bundleId = (string) config('reton.links.mobile.ios_bundle_id');
        $listingPrefix = rtrim((string) config('reton.links.listing_path', '/l'), '/');

        $details = [];

        if ($teamId !== '' && $bundleId !== '') {
            $details[] = [
                'appID' => "{$teamId}.{$bundleId}",
                'paths' => [
                    $listingPrefix.'/*',
                    '/pay/*',
                ],
            ];
        }

        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => $details,
            ],
        ], headers: ['Content-Type' => 'application/json']);
    }

    public function assetLinks(): JsonResponse
    {
        $package = (string) config('reton.links.mobile.android_package');
        $fingerprints = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('reton.links.mobile.android_sha256', ''))
        )));

        if ($package === '' || $fingerprints === []) {
            return response()->json([]);
        }

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }
}
