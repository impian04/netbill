<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Hash;

class ActivationCodeService
{
    // Alphabet: no 0, O, 1, I, L to avoid ambiguity
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const CODE_LEN  = 8;
    private const MAX_ATTEMPTS = 5;
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
                'activation_code_hash'   => Hash::make($plain),
                'activation_expires_at'  => now()->addDays(self::EXPIRES_DAYS),
                'activation_attempts'    => 0,
                'activated_at'           => null,
                'password'               => null,
            ])->save();
        });

        // Revoke all existing API tokens — customer must re-activate to log in again
        $customer->tokens()->delete();

        return $plain;
    }

    /**
     * Verify a submitted code.
     * Returns true on success, false on wrong code.
     * Throws \RuntimeException on expired/locked.
     */
    public static function verify(Customer $customer, string $rawCode): bool
    {
        $fresh = $customer->fresh();

        if (! $fresh->activation_code_hash) {
            return false;
        }

        if ($fresh->activation_attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('Kode terkunci. Minta admin buat kode baru.');
        }

        if (! $fresh->activation_expires_at || $fresh->activation_expires_at->isPast()) {
            throw new \RuntimeException('Kode aktivasi sudah kedaluwarsa.');
        }

        // Normalize: uppercase, remove dashes/spaces
        $normalized = strtoupper(preg_replace('/[\s\-]/', '', $rawCode));

        if (! $fresh->activation_code_hash || ! Hash::check($normalized, $fresh->activation_code_hash)) {
            $fresh->withoutEvents(fn () => $fresh->increment('activation_attempts'));
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
                'activation_code_hash'  => null,
                'activation_expires_at' => null,
                'activation_attempts'   => 0,
                'activated_at'          => now(),
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
