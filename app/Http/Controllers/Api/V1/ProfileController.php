<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /** GET /api/v1/me */
    public function show(Request $request): CustomerResource
    {
        return new CustomerResource($request->user());
    }

    /** POST /api/v1/me/password */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.min'      => 'Password minimal 8 karakter.',
            'password.letters'  => 'Password harus mengandung huruf.',
            'password.numbers'  => 'Password harus mengandung angka.',
            'password.confirmed'=> 'Konfirmasi password tidak cocok.',
        ]);

        $customer = $request->user();

        if (! Hash::check($data['current_password'], $customer->password)) {
            return response()->json(['message' => 'Password saat ini salah.'], 422);
        }

        $customer->forceFill(['password' => $data['password']])->save();

        // Revoke all OTHER tokens (keep current)
        $currentId = $customer->currentAccessToken()->id;
        $customer->tokens()->where('id', '!=', $currentId)->delete();

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }
}
