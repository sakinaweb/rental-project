<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

// Push registration for the customer app (Phase 7). A token belongs to whoever registered it last on that device.
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
        ]);

        $device = DeviceToken::updateOrCreate(['token' => $data['token']], ['user_id' => $request->user()->id, 'platform' => $data['platform']]);

        return response()->json(['data' => ['token' => $device->token, 'platform' => $device->platform]], $device->wasRecentlyCreated ? 201 : 200);
    }

    /** On sign-out: stop pushing to this device. Only the owner's own token is removed. */
    public function destroy(Request $request, string $token): Response
    {
        DeviceToken::where('user_id', $request->user()->id)->where('token', $token)->delete();

        return response()->noContent();
    }
}
