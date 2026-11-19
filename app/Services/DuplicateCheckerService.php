<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Customer;
use App\Models\CustomerApplication;
use App\Models\User;

class DuplicateCheckerService
{
    /**
     * Calculate Haversine distance in meters between two lat/lng points.
     */
    public static function haversineDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000.0; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check duplicates for an application.
     * Returns an array of warning strings.
     *
     * @param CustomerApplication $app
     * @param User|null $currentUser
     * @return array<string>
     */
    public static function check(CustomerApplication $app, ?User $currentUser = null): array
    {
        $warnings = [];
        $hasCrossPopMatch = false;

        $userCanSeePop = function (int $popId) use ($currentUser): bool {
            if (! $currentUser) {
                return false;
            }
            if ($currentUser->isSuperAdmin()) {
                return true;
            }
            return (int) $currentUser->pop_id === $popId;
        };

        // 1. Phone match
        if ($app->phone) {
            // Check Customers
            $custMatches = Customer::where('phone', $app->phone)
                ->when($app->customer_id, fn ($q) => $q->where('id', '!=', $app->customer_id))
                ->get();

            foreach ($custMatches as $cust) {
                if ($userCanSeePop($cust->pop_id)) {
                    $warnings[] = "Nomor telepon sama dengan pelanggan {$cust->customer_code} ({$cust->name}).";
                } else {
                    $hasCrossPopMatch = true;
                }
            }

            // Check Applications
            $appMatches = CustomerApplication::where('phone', $app->phone)
                ->where('id', '!=', $app->id)
                ->where('status', '!=', ApplicationStatus::REJECTED->value)
                ->get();

            foreach ($appMatches as $otherApp) {
                if ($userCanSeePop($otherApp->pop_id)) {
                    $warnings[] = "Nomor telepon sama dengan pendaftaran #{$otherApp->id} ({$otherApp->name}).";
                } else {
                    $hasCrossPopMatch = true;
                }
            }
        }

        // 2. Similar name on SAME POP
        if ($app->name && $app->pop_id) {
            $normalizedName = strtolower(trim($app->name));

            // Same POP Customers
            $samePopCustomers = Customer::where('pop_id', $app->pop_id)
                ->when($app->customer_id, fn ($q) => $q->where('id', '!=', $app->customer_id))
                ->get();

            foreach ($samePopCustomers as $cust) {
                $custName = strtolower(trim($cust->name));
                if (self::isNameSimilar($normalizedName, $custName)) {
                    if ($userCanSeePop($cust->pop_id)) {
                        $warnings[] = "Nama mirip dengan pelanggan {$cust->customer_code} ({$cust->name}) pada POP yang sama.";
                    } else {
                        $hasCrossPopMatch = true;
                    }
                }
            }

            // Same POP Applications
            $samePopApps = CustomerApplication::where('pop_id', $app->pop_id)
                ->where('id', '!=', $app->id)
                ->where('status', '!=', ApplicationStatus::REJECTED->value)
                ->get();

            foreach ($samePopApps as $otherApp) {
                $otherName = strtolower(trim($otherApp->name));
                if (self::isNameSimilar($normalizedName, $otherName)) {
                    if ($userCanSeePop($otherApp->pop_id)) {
                        $warnings[] = "Nama mirip dengan pendaftaran #{$otherApp->id} ({$otherApp->name}) pada POP yang sama.";
                    } else {
                        $hasCrossPopMatch = true;
                    }
                }
            }
        }

        // 3. Coordinates within 30 meters
        if ($app->latitude !== null && $app->longitude !== null) {
            // Check Customers with coordinates
            $coordCustomers = Customer::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->when($app->customer_id, fn ($q) => $q->where('id', '!=', $app->customer_id))
                ->get();

            foreach ($coordCustomers as $cust) {
                $dist = self::haversineDistanceMeters($app->latitude, $app->longitude, (float) $cust->latitude, (float) $cust->longitude);
                if ($dist <= 30.0) {
                    if ($userCanSeePop($cust->pop_id)) {
                        $rounded = round($dist, 1);
                        $warnings[] = "Koordinat berjarak {$rounded} m dari pelanggan {$cust->customer_code} ({$cust->name}).";
                    } else {
                        $hasCrossPopMatch = true;
                    }
                }
            }

            // Check Applications with coordinates
            $coordApps = CustomerApplication::whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('id', '!=', $app->id)
                ->where('status', '!=', ApplicationStatus::REJECTED->value)
                ->get();

            foreach ($coordApps as $otherApp) {
                $dist = self::haversineDistanceMeters($app->latitude, $app->longitude, (float) $otherApp->latitude, (float) $otherApp->longitude);
                if ($dist <= 30.0) {
                    if ($userCanSeePop($otherApp->pop_id)) {
                        $rounded = round($dist, 1);
                        $warnings[] = "Koordinat berjarak {$rounded} m dari pendaftaran #{$otherApp->id} ({$otherApp->name}).";
                    } else {
                        $hasCrossPopMatch = true;
                    }
                }
            }
        }

        if ($hasCrossPopMatch) {
            $warnings[] = 'Kemungkinan sudah terdaftar di POP lain.';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * Determine if two names are significantly similar.
     */
    private static function isNameSimilar(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        // Metaphone or soundex match
        if (soundex($a) === soundex($b)) {
            return true;
        }

        // Levenshtein distance for close typos
        $len = max(strlen($a), strlen($b));
        if ($len >= 4 && levenshtein($a, $b) <= 2) {
            return true;
        }

        return false;
    }
}

