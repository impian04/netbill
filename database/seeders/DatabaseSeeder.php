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
                    'invoice_number'  => 'DRAFT-' . uniqid() . '-' . bin2hex(random_bytes(4)),
                    'period'          => $month->format('Y-m-d'),
                    'deferred_amount' => fake()->randomElement([110000, 165000, 220000, 275000, 330000]),
                    'status'          => fake()->randomElement(['PAID', 'UNPAID']),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        DB::table('invoices')->insert($rows);

        // Format nomor invoice resmi secara portabel (MySQL, SQLite, dll.)
        Invoice::fixDraftNumbers();

        // Backfill paid_at for PAID invoices
        Invoice::where('status', 'PAID')->whereNull('paid_at')->update(['paid_at' => now()]);
    }
}
