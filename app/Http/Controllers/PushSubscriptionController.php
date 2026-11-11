<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pushmeldingen aan/uit zetten voor dit apparaat (vanuit resources/js/push.js) */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => 'required|url:https|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'contentEncoding' => 'nullable|in:aes128gcm,aesgcm',
        ]);

        // Hetzelfde apparaat kan eerder van een ander account zijn geweest: dan gaat het over naar jou
        PushSubscription::firstOrNew(['endpoint_hash' => PushSubscription::hash($data['endpoint'])])
            ->forceFill([
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
            ])
            ->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hash($request->string('endpoint')))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request): JsonResponse
    {
        $sent = WebPush::send($request->user(), [
            'title' => 'Meldingen staan aan',
            'body' => 'Zo krijg je een seintje als je rust voorbij is.',
            'url' => route('profile.edit').'#weekdoel',
            'tag' => 'test',
        ]);

        return response()->json(['sent' => $sent]);
    }
}
