<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    /**
     * GET /api/agent/version
     * Returns the latest agent version + download URL.
     * Public endpoint (no auth) so agents can check for updates.
     */
    public function latest(): JsonResponse
    {
        // Version is read from config so ops can bump it without code deploy.
        $version = config('agent.latest_version', '1.0.5');
        $downloadUrl = url('/download/agent');

        return response()->json([
            'version' => $version,
            'download_url' => $downloadUrl,
            'release_notes_url' => 'https://github.com/syahrullrmdhn/sentraguard/releases',
        ]);
    }
}
