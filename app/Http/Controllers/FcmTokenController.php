<?php

namespace App\Http\Controllers;

use App\Models\FcmToken;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:4096',
            'device_type' => 'nullable|string|max:30',
        ]);

        FcmToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'] ?? null,
            ]
        );

        return response()->json(['success' => true, 'message' => 'FCM token registered.']);
    }

    public function destroy(Request $request, string $token)
    {
        $request->user()->fcmTokens()->where('token', $token)->delete();

        return response()->json(['success' => true, 'message' => 'FCM token removed.']);
    }
}