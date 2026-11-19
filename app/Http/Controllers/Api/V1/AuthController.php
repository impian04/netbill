<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\ActivationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const ACTIVATION_ERROR = 'Kode tidak valid atau sudah tidak berlaku. Hubungi admin untuk kode baru.';

    /**
     * POST /api/v1/auth/activate
     * Verifikasi customer_code + phone + activation_code, set password, issue token.
     */
    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_code'         => ['required', 'string', 'max:20'],
            'phone'                 => ['required', 'string', 'max:20'],
            'activation_code'       => ['required', 'string'],
            'password'              => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'device_name'           => ['required', 'string', 'max:100'],
        ], [
            'password.min'      => 'Password minimal 8 karakter.',
            'password.letters'  => 'Password harus mengandung huruf.',
            'password.numbers'  => 'Password harus mengandung angka.',
            'password.confirmed'=> 'Konfirmasi password tidak cocok.',
        ]);

        // Always use SAME generic error — prevents user enumeration
        $fail = fn () => response()->json(['message' => self::ACTIVATION_ERROR], 422);

        $code = trim($data['customer_code']);
        $customer = Customer::whereRaw('LOWER(customer_code) = ?', [strtolower($code)])->first();
        if (! $customer) return $fail();

        // Normalize phone and compare
        $normalizedPhone = \App\Http\Requests\StoreCustomerRequest::normalizePhone($data['phone']);
        if ($customer->phone !== $normalizedPhone) return $fail();

        // Try verify
        try {
            $ok = ActivationCodeService::verify($customer, $data['activation_code']);
        } catch (\Throwable) {
            return $fail();
        }

        if (! $ok) return $fail();

        // Consume code, set password, revoke old tokens, issue new token
        ActivationCodeService::consume($customer);

        $customer->forceFill(['password' => $data['password']])->save();
        $customer->tokens()->delete();

        $token = $customer->createToken($data['device_name'], ['customer'], now()->addDays(30));

        return response()->json([
            'data' => [
                'token'   => $token->plainTextToken,
                'profile' => new CustomerResource($customer->fresh()),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_code' => ['required', 'string', 'max:20'],
            'password'      => ['required', 'string'],
            'device_name'   => ['required', 'string', 'max:100'],
        ]);

        $genericFail = response()->json(['message' => 'Kode pelanggan atau password salah.'], 401);

        $code = trim($data['customer_code']);
        $customer = Customer::whereRaw('LOWER(customer_code) = ?', [strtolower($code)])->first();
        if (! $customer || ! $customer->password || ! Hash::check($data['password'], $customer->password)) {
            return $genericFail;
        }
        if (! $customer->activated_at) {
            return $genericFail; // not yet activated
        }
        if ($customer->service_status === 'INACTIVE') {
            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi layanan pelanggan.'], 403);
        }
        // SUSPENDED: allowed to login (can see & pay bills)

        // Multi-device: maximum 5 active tokens. Prune oldest tokens if limit reached.
        $existingTokens = $customer->tokens()->orderBy('created_at')->get();
        if ($existingTokens->count() >= 5) {
            $toDeleteCount = ($existingTokens->count() - 5) + 1;
            $customer->tokens()
                ->whereIn('id', $existingTokens->take($toDeleteCount)->pluck('id'))
                ->delete();
        }

        $token = $customer->createToken($data['device_name'], ['customer'], now()->addDays(30));

        return response()->json([
            'data' => [
                'token'   => $token->plainTextToken,
                'profile' => new CustomerResource($customer),
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil.']);
    }
}
