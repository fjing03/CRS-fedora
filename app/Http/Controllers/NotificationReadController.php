<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The durable mark-read endpoints (SDD notifications-real-bridge D3): the
 * capture-phase click fires a keepalive fetch BEFORE the native navigation —
 * the D4 law with the DB read_at state (the localStorage store's successor).
 * Idempotent, auth-scoped, never touches another user's rows.
 */
final class NotificationReadController extends Controller
{
    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereKey((int) $data['id'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
