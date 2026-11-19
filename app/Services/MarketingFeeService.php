<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MarketingFee;
use Illuminate\Support\Facades\DB;

class MarketingFeeService
{
    /**
     * Generate marketing fee if customer is eligible (first time ACTIVE and has referral).
     */
    public static function processEligibility(Customer $customer): ?MarketingFee
    {
        // Only customers referred by a marketing user are eligible
        if (! $customer->referred_by_user_id) {
            return null;
        }

        // Only create once per customer
        if (MarketingFee::where('customer_id', $customer->id)->exists()) {
            return null;
        }

        $defaultFee = DB::table('settings')->where('key', 'marketing_fee_default')->value('value');
        $amount = ($defaultFee !== null && $defaultFee !== '' && is_numeric($defaultFee)) ? (int) $defaultFee : null;

        return MarketingFee::create([
            'marketing_user_id' => $customer->referred_by_user_id,
            'customer_id'       => $customer->id,
            'pop_id'            => $customer->pop_id,
            'amount'            => $amount,
            'status'            => 'PENDING',
            'eligible_at'       => now(),
        ]);
    }
}

