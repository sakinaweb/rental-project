<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, AccountService $accounts): JsonResponse
    {
        return $this->tokenResponse($accounts->register($request->validated()), 201);
    }

    public function login(Request $request, AccountService $accounts): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        return $this->tokenResponse($accounts->authenticate($data['email'], $data['password']));
    }

    /** PATCH /me (Phase 6, D3): name, phone, country, language. */
    public function updateMe(Request $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validate(AccountService::profileRules($user)));

        return new UserResource($user->load('role'));
    }

    public function requestOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        $user = User::where('phone', $data['phone'])->first();

        if ($user?->isActive()) {
            $otp->send($user);
        }

        // Same answer whether or not the number exists, so phone numbers can't be enumerated.
        return response()->json(['message' => __('messages.otp_sent')]);
    }

    public function verifyOtp(Request $request, OtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
        ]);

        $user = User::where('phone', $data['phone'])->first();

        if (! $user || ! $otp->verify($data['phone'], $data['code'])) {
            throw ValidationException::withMessages(['code' => __('messages.otp_invalid')]);
        }

        return $this->tokenResponse($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('role'));
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        app(AccountService::class)->ensureActive($user);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user->load('role')),
        ], $status);
    }
}
