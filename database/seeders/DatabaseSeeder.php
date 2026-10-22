<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin tunggal
        User::factory()->create([
            'name'     => 'Admin NetBill',
            'email'    => 'admin@netbill.test',
            'password' => bcrypt('password'),
        ]);

        // 10 pelanggan dummy dengan status campuran
        $statuses = ['ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE',
                     'PENDING', 'SUSPENDED', 'INACTIVE', 'ACTIVE', 'ACTIVE'];

        $customers = collect();
        foreach ($statuses as $i => $status) {
            $customers->push(Customer::factory()->create([
                'service_status' => $status,
            ]));
        }

        // 1 pelanggan sudah aktif (password hanya di local)
        if (app()->isLocal()) {
            $activeCustomer = $customers->first();
            $activeCustomer->forceFill([
                'password'      => Hash::make('Password123!'),
                'activated_at'  => now()->subDays(10),
                'phone'         => '628123456789',
                'service_status'=> 'ACTIVE',
            ])->save();
        }

        // Setiap pelanggan mendapat 3 periode tagihan
        $months = collect([
            now()->startOfMonth(),
            now()->subMonth()->startOfMonth(),
            now()->subMonths(2)->startOfMonth(),
        ]);

        $rows = [];
        foreach ($customers as $customer) {
            foreach ($months as $month) {
                $rows[] = [
                    'customer_id'     => $customer->id,
                    'invoice_number'  => 'TMP-' . $customer->id . '-' . $month->format('Ym'),
                    'period'          => $month->format('Y-m-d'),
                    'deferred_amount' => fake()->randomElement([110000, 165000, 220000, 275000, 330000]),
                    'status'          => fake()->randomElement(['PAID', 'UNPAID']),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        DB::table('invoices')->insert($rows);

        // Backfill invoice_number for seeder rows (model boot only triggers on Eloquent create)
        DB::statement("
            UPDATE invoices
            SET invoice_number = CONCAT('INV-', DATE_FORMAT(period, '%Y%m'), '-', LPAD(id, 6, '0'))
            WHERE invoice_number IS NULL OR invoice_number LIKE 'TMP-%'
        ");

        // Backfill paid_at for PAID invoices
        DB::statement("
            UPDATE invoices SET paid_at = created_at
            WHERE status = 'PAID' AND paid_at IS NULL
        ");
    }
}
