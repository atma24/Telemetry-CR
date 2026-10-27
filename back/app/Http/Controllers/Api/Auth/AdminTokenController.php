<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IssueAdminTokenRequest;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AdminTokenController extends Controller
{
    public function store(IssueAdminTokenRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $admin = Pengguna::query()->where('email', $credentials['email'])->first();

        if (
            $admin === null
            || ! Hash::check($credentials['password'], $admin->password)
            || ! $admin->can('admin')
        ) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $token = $admin->createToken('admin-api', ['*'], now()->addHours(8));

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token->plainTextToken,
                'expires_at' => $token->accessToken->expires_at?->toISOString(),
            ],
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Pengguna) {
            abort(401);
        }

        $bearerToken = $request->bearerToken();
        $token = $bearerToken !== null ? PersonalAccessToken::findToken($bearerToken) : null;

        if ($token?->tokenable?->is($user)) {
            $token->delete();
        }

        return response()->json(status: 204);
    }
}
