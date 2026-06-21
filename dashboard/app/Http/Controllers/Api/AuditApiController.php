<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with(['user', 'server'])
            ->when($request->result, fn ($q) => $q->where('result', $request->result))
            ->when($request->search, fn ($q) => $q->where(function ($sub) use ($request) {
                $sub->where('action', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhere('actor_identifier', 'like', "%{$request->search}%");
            }))
            ->latest('created_at')
            ->paginate(25);

        return response()->json($logs);
    }
}
