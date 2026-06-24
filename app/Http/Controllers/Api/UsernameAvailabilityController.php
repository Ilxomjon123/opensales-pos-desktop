<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UsernameAvailabilityController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        $username = trim((string) $request->query('username', ''));
        $ignoreId = $request->integer('ignore') ?: null;

        if ($username === '') {
            return response()->json(['status' => 'empty', 'available' => false]);
        }

        if (mb_strlen($username) < 5) {
            return response()->json(['status' => 'short', 'available' => false]);
        }

        if (! preg_match('/^[A-Za-z0-9._@-]+$/', $username)) {
            return response()->json(['status' => 'invalid', 'available' => false]);
        }

        $exists = User::query()
            ->where('username', $username)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        return response()->json([
            'status' => $exists ? 'taken' : 'available',
            'available' => ! $exists,
        ]);
    }
}
