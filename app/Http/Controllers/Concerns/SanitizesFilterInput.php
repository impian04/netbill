<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait SanitizesFilterInput
{
    /**
     * Ambil string pencarian yang disanitasi dari request (mendukung 'q' dan 'search')
     */
    protected function sanitizeSearch(Request $request): ?string
    {
        $raw = $request->input('q') ?? $request->input('search');
        if (is_array($raw)) {
            $raw = $raw['value'] ?? null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim(strip_tags($raw));

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Ambil integer ID yang valid atau null jika bukan numeric
     */
    protected function sanitizeInt(Request $request, string $key): ?int
    {
        $val = $request->input($key);
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return (int) $val;
    }

    /**
     * Ambil tanggal Y-m-d yang valid atau null jika format salah
     */
    protected function sanitizeDate(Request $request, string ...$keys): ?string
    {
        foreach ($keys as $k) {
            $val = $request->input($k);
            if (is_string($val) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($val))) {
                try {
                    $d = Carbon::createFromFormat('Y-m-d', trim($val));
                    if ($d && $d->format('Y-m-d') === trim($val)) {
                        return trim($val);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Ambil periode Y-m yang valid atau null jika format salah
     */
    protected function sanitizePeriod(Request $request, string ...$keys): ?string
    {
        foreach ($keys as $k) {
            $val = $request->input($k);
            if (is_string($val) && preg_match('/^\d{4}-\d{2}$/', trim($val))) {
                try {
                    $d = Carbon::createFromFormat('Y-m', trim($val));
                    if ($d && $d->format('Y-m') === trim($val)) {
                        return trim($val);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Ambil nilai dari whitelist yang diizinkan atau null
     */
    protected function sanitizeWhitelist(Request $request, array $allowed, string ...$keys): ?string
    {
        foreach ($keys as $k) {
            $val = $request->input($k);
            if (is_string($val) && in_array(trim($val), $allowed, true)) {
                return trim($val);
            }
        }

        return null;
    }
}

