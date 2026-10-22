<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class ActivationCodeService
{
    // Alphabet: no 0, O, 1, I, L to avoid ambiguity
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LEN  = 8;
    public const MAX_ATTEMPTS = 100;
    private const TEMP_LOCK_MINUTES = 15;
    private const EXPIRES_DAYS = 7;

    /**
     * Generate a new activation code for the customer.
     * If already active, this resets their password (requires re-activation).
     * Returns the plaintext code — display ONCE, never log or store plaintext.
     */
    public static function generate(Customer $customer): string
    {
        $plain = self::randomCode();

        $customer->withoutEvents(function () use ($customer, $plain) {
            $customer->forceFill([
                'activation_code_hash'    => Hash::make($plain),
                'activation_expires_at'   => now()->addDays(self::EXPIRES_DAYS),
                'activation_attempts'     => 0,
                'activation_locked_until' => null,
                'activated_at'            => null,
                'password'                => null,
            ])->save();
        });

        // Revoke all existing API tokens — customer must re-activate to log in again
        $customer->tokens()->delete();

        return $plain;
    }

    /**
     * Verify a submitted code.
     * Returns true on success, false on wrong code / expired / locked.
     */
    public static function verify(Customer $customer, string $rawCode): bool
    {
        $fresh = $customer->fresh();

        if (! $fresh->activation_code_hash) {
            return false;
        }

        if ($fresh->activation_attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if ($fresh->activation_locked_until && $fresh->activation_locked_until->isFuture()) {
            return false;
        }

        if (! $fresh->activation_expires_at || $fresh->activation_expires_at->isPast()) {
            return false;
        }

        // Normalize: uppercase, remove dashes/spaces
        $normalized = strtoupper(preg_replace('/[\s\-]/', '', $rawCode));

        if (! Hash::check($normalized, $fresh->activation_code_hash)) {
            $fresh->withoutEvents(function () use ($fresh) {
                $newAttempts = $fresh->activation_attempts + 1;
                $updates = ['activation_attempts' => $newAttempts];

                // Setiap 5x salah (< 20): kunci sementara 15 menit
                if ($newAttempts < self::MAX_ATTEMPTS && $newAttempts % 5 === 0) {
                    $updates['activation_locked_until'] = now()->addMinutes(self::TEMP_LOCK_MINUTES);
                }

                $fresh->forceFill($updates)->save();
            });

            return false;
        }

        return true;
    }

    /**
     * Consume the code: clear hash fields, set activated_at.
     * Call only after verify() returns true.
     */
    public static function consume(Customer $customer): void
    {
        $customer->withoutEvents(function () use ($customer) {
            $customer->forceFill([
                'activation_code_hash'    => null,
                'activation_expires_at'   => null,
                'activation_attempts'     => 0,
                'activation_locked_until' => null,
                'activated_at'            => now(),
            ])->save();
        });
    }

    private static function randomCode(): string
    {
        $alphabet = self::ALPHABET;
        $len      = strlen($alphabet);
        $code     = '';
        for ($i = 0; $i < self::CODE_LEN; $i++) {
            $code .= $alphabet[random_int(0, $len - 1)];
        }
        return $code;
    }

    /** Format plaintext code as XXXX-XXXX for display */
    public static function format(string $plain): string
    {
        return substr($plain, 0, 4) . '-' . substr($plain, 4, 4);
    }
}
