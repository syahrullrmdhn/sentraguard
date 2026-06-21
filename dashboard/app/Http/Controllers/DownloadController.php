<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    /**
     * Download the Windows agent executable.
     * Public endpoint — no auth required (like Cloudflare's tunnel download).
     */
    public function agent(): BinaryFileResponse
    {
        // Agent lives in ../agent/ (sibling of dashboard/)
        $path = dirname(base_path()) . '/agent/build/sentraguard-agent.exe';

        abort_unless(file_exists($path), 404, 'Agent binary not found. Build the agent first.');

        return response()->download(
            $path,
            'sentraguard-agent.exe',
            [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }
}
