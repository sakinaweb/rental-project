<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'locale' => $request->validated('locale') ?? app()->getLocale(),
            'role_id' => Role::idFor(Role::CUSTOMER),
        ]);

        return $this->tokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('messages.invalid_credentials')]);
        }

        return $this->tokenResponse($user);
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
        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('messages.account_inactive')]);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => new UserResource($user->load('role')),
        ], $status);
    }
}
