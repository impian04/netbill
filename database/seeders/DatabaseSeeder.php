<?php

namespace Database\Seeders;

use App\Enums\PhotoStage;
use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Models\Survey;
use App\Models\SurveyItem;
use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use App\Services\PrivateFileService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder akun demo yang lemah hanya berjalan jika app()->isLocal()
        if (! app()->isLocal()) {
            $this->command->warn('Seeder demo dilewati: APP_ENV bukan local (APP_ENV=' . app()->environment() . ').');
            return;
        }

        $this->command->info('Menjalankan seeder demo (APP_ENV=local)...');

        // 1. POP Demo (KTU, EP, BKN)
        $popKtu = Pop::updateOrCreate(
            ['code' => 'KTU'],
            ['name' => 'Kota Tua', 'va_code' => '01', 'is_active' => true, 'last_customer_number' => 0]
        );

        $popEp = Pop::updateOrCreate(
            ['code' => 'EP'],
            ['name' => 'Empat Puluh', 'va_code' => '02', 'is_active' => true, 'last_customer_number' => 0]
        );

        $popBkn = Pop::updateOrCreate(
            ['code' => 'BKN'],
            ['name' => 'Bangkinang', 'va_code' => '03', 'is_active' => true, 'last_customer_number' => 0]
        );

        $demoPops = [$popKtu, $popEp, $popBkn];

        // 2. Akun Staf per Role (hanya local)
        $password = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'superadmin@netbill.test'],
            ['name' => 'Super Admin', 'password' => $password, 'role' => Role::SUPER_ADMIN, 'pop_id' => null, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'admin.ktu@netbill.test'],
            ['name' => 'Admin KTU', 'password' => $password, 'role' => Role::ADMIN, 'pop_id' => $popKtu->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'marketing.ktu@netbill.test'],
            ['name' => 'Marketing KTU', 'password' => $password, 'role' => Role::MARKETING, 'pop_id' => $popKtu->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'surveyor.ktu@netbill.test'],
            ['name' => 'Surveyor KTU', 'password' => $password, 'role' => Role::SURVEYOR, 'pop_id' => $popKtu->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'technician.ktu@netbill.test'],
            ['name' => 'Teknisi KTU', 'password' => $password, 'role' => Role::TECHNICIAN, 'pop_id' => $popKtu->id, 'is_active' => true]
        );

        // Akun Staf POP EP (Fase 17i)
        User::updateOrCreate(
            ['email' => 'admin.ep@netbill.test'],
            ['name' => 'Admin EP', 'password' => $password, 'role' => Role::ADMIN, 'pop_id' => $popEp->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'marketing.ep@netbill.test'],
            ['name' => 'Marketing EP', 'password' => $password, 'role' => Role::MARKETING, 'pop_id' => $popEp->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'marketing2.ep@netbill.test'],
            ['name' => 'Marketing 2 EP', 'password' => $password, 'role' => Role::MARKETING, 'pop_id' => $popEp->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'surveyor.ep@netbill.test'],
            ['name' => 'Surveyor EP', 'password' => $password, 'role' => Role::SURVEYOR, 'pop_id' => $popEp->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'technician.ep@netbill.test'],
            ['name' => 'Teknisi EP', 'password' => $password, 'role' => Role::TECHNICIAN, 'pop_id' => $popEp->id, 'is_active' => true]
        );

        User::updateOrCreate(
            ['email' => 'noc@netbill.test'],
            ['name' => 'Staff NOC', 'password' => $password, 'role' => Role::NOC, 'pop_id' => null, 'is_active' => true]
        );

        // Cetak akun demo ke konsol
        $this->command->newLine();
        $this->command->info('╔══════════════════════════════════════════════════════════════╗');
        $this->command->info('║          AKUN DEMO NETBILL (password: password)              ║');
        $this->command->info('╠══════════════════════╦══════════════════════════════════════╣');
        $this->command->info('║ Role                 ║ Email                                ║');
        $this->command->info('╠══════════════════════╬══════════════════════════════════════╣');
        $this->command->info('║ super_admin          ║ superadmin@netbill.test              ║');
        $this->command->info('║ admin (POP: KTU)     ║ admin.ktu@netbill.test               ║');
        $this->command->info('║ marketing (POP: KTU) ║ marketing.ktu@netbill.test           ║');
        $this->command->info('║ surveyor (POP: KTU)  ║ surveyor.ktu@netbill.test            ║');
        $this->command->info('║ technician (POP: KTU)║ technician.ktu@netbill.test          ║');
        $this->command->info('║ noc                  ║ noc@netbill.test                     ║');
        $this->command->info('╚══════════════════════╩══════════════════════════════════════╝');
        $this->command->newLine();

        // 3. Master ODC, ODP, dan Barang per POP
        $odcMap = [];
        $odpMap = [];

        foreach ($demoPops as $pop) {
            // ODC per POP
            $odc1 = Odc::updateOrCreate(
                ['pop_id' => $pop->id, 'name' => "ODC-{$pop->code}-01"],
                ['latitude' => 0.5101000, 'longitude' => 101.4402000, 'is_active' => true]
            );
            $odc2 = Odc::updateOrCreate(
                ['pop_id' => $pop->id, 'name' => "ODC-{$pop->code}-02"],
                ['latitude' => 0.5142000, 'longitude' => 101.4455000, 'is_active' => true]
            );
            $odcMap[$pop->code] = [$odc1, $odc2];

            // ODP per ODC
            $odp1 = Odp::updateOrCreate(
                ['pop_id' => $pop->id, 'name' => "ODP-{$pop->code}-01-01"],
                ['odc_id' => $odc1->id, 'latitude' => 0.5105000, 'longitude' => 101.4408000, 'is_active' => true]
            );
            $odp2 = Odp::updateOrCreate(
                ['pop_id' => $pop->id, 'name' => "ODP-{$pop->code}-01-02"],
                ['odc_id' => $odc1->id, 'latitude' => 0.5109000, 'longitude' => 101.4412000, 'is_active' => true]
            );
            $odp3 = Odp::updateOrCreate(
                ['pop_id' => $pop->id, 'name' => "ODP-{$pop->code}-02-01"],
                ['odc_id' => $odc2->id, 'latitude' => 0.5145000, 'longitude' => 101.4460000, 'is_active' => true]
            );
            $odpMap[$pop->code] = [$odp1, $odp2, $odp3];

            // Barang per POP
            $materialsData = [
                ['name' => 'Kabel Dropcore 1 Core', 'unit' => 'meter'],
                ['name' => 'ONT ZTE F609', 'unit' => 'unit'],
                ['name' => 'Patchcord SC-UPC 3M', 'unit' => 'pcs'],
                ['name' => 'Konektor Fast Connector SC-UPC', 'unit' => 'pcs'],
                ['name' => 'Kabel Fiber Optic 24 Core', 'unit' => 'roll'],
                ['name' => 'Toolkit Fiber Optic', 'unit' => 'set'],
            ];

            foreach ($materialsData as $m) {
                Material::updateOrCreate(
                    ['pop_id' => $pop->id, 'name' => $m['name']],
                    ['unit' => $m['unit'], 'is_active' => true]
                );
            }
        }

        // 4. Pelanggan demo berkode JCN
        $statuses = [
            'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE', 'ACTIVE',
            'PENDING', 'SUSPENDED', 'INACTIVE', 'ACTIVE', 'ACTIVE',
        ];

        $customers = collect();
        $popCounters = ['KTU' => 0, 'EP' => 0, 'BKN' => 0];

        foreach ($statuses as $i => $status) {
            $pop = $demoPops[$i % count($demoPops)];
            $popCounters[$pop->code]++;
            $seq = $popCounters[$pop->code];
            $customerCode = sprintf('JCN-%s-%04d', $pop->code, $seq);

            // Pasangkan ODC dan ODP untuk sebagian pelanggan demo
            $odcId = null;
            $odpId = null;
            if (isset($odcMap[$pop->code]) && $i % 2 === 0) {
                $odc = $odcMap[$pop->code][0];
                $odcId = $odc->id;
                $odp = $odpMap[$pop->code][0];
                $odpId = $odp->id;
            }

            $customer = Customer::create([
                'pop_id'         => $pop->id,
                'odc_id'         => $odcId,
                'odp_id'         => $odpId,
                'customer_code'  => $customerCode,
                'name'           => fake()->name(),
                'phone'          => '628' . fake()->numerify('#########'),
                'address'        => fake()->address(),
                'subscribed_at'  => now()->subMonths(6)->format('Y-m-d'),
                'package'        => fake()->randomElement(['10 Mbps', '20 Mbps', '30 Mbps', '50 Mbps']),
                'billing_type'   => 'Bulanan',
                'service_status' => $status,
            ]);

            $customers->push($customer);
        }

        // Update last_customer_number on POPs
        foreach ($demoPops as $pop) {
            $pop->update(['last_customer_number' => $popCounters[$pop->code]]);
        }

        // 1 pelanggan demo aktif untuk uji API login mobile
        $activeCustomer = $customers->first();
        $activeCustomer->forceFill([
            'password'       => Hash::make('Password123!'),
            'activated_at'   => now()->subDays(10),
            'phone'          => '628123456789',
            'service_status' => 'ACTIVE',
        ])->save();

        // 4. Tagihan demo (3 periode per pelanggan)
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

        Invoice::fixDraftNumbers();
        Invoice::where('status', 'PAID')->whereNull('paid_at')->update(['paid_at' => now()]);

        // 5. Pendaftaran marketing demo dengan berbagai status
        $marketingUser = User::where('email', 'marketing.ktu@netbill.test')->first();
        if ($marketingUser) {
            \App\Models\CustomerApplication::create([
                'pop_id'            => $popKtu->id,
                'marketing_user_id' => $marketingUser->id,
                'name'              => 'Ahmad Yani (Calon Baru)',
                'phone'             => '6281299887711',
                'address'           => 'Jl. Flamboyan No. 12, RT 01/RW 04, Kel. Sukamaju',
                'latitude'          => 0.5100000,
                'longitude'         => 101.4400000,
                'package'           => '20 Mbps',
                'billing_type'      => 'Bulanan',
                'notes'             => 'Pemasangan minta hari Sabtu sore.',
                'status'            => \App\Enums\ApplicationStatus::SUBMITTED,
            ]);

            \App\Models\CustomerApplication::create([
                'pop_id'            => $popKtu->id,
                'marketing_user_id' => $marketingUser->id,
                'name'              => 'Dewi Sartika (Perlu Revisi)',
                'phone'             => '6281299887722',
                'address'           => 'Jl. Melati No. 8',
                'latitude'          => 0.5120000,
                'longitude'         => 101.4420000,
                'package'           => '30 Mbps',
                'billing_type'      => 'Bulanan',
                'notes'             => 'Alamat belum jelas patokannya.',
                'status'            => \App\Enums\ApplicationStatus::NEEDS_REVISION,
                'review_note'       => 'Mohon lengkapi patokan alamat dan nomor rumah yang jelas.',
                'reviewed_by'       => User::where('email', 'admin.ktu@netbill.test')->value('id'),
                'reviewed_at'       => now()->subDays(1),
            ]);

            \App\Models\CustomerApplication::create([
                'pop_id'            => $popKtu->id,
                'marketing_user_id' => $marketingUser->id,
                'name'              => 'Bambang Sudibyo (Ditolak)',
                'phone'             => '6281299887733',
                'address'           => 'Jl. Hutan Lindung KM 15',
                'latitude'          => 0.5200000,
                'longitude'         => 101.4600000,
                'package'           => '50 Mbps',
                'billing_type'      => 'Bulanan',
                'notes'             => 'Di luar jangkauan tiang ODP.',
                'status'            => \App\Enums\ApplicationStatus::REJECTED,
                'review_note'       => 'Lokasi berjarak lebih dari 800 meter dari ODP terdekat, belum tercover fiber optic.',
                'reviewed_by'       => User::where('email', 'admin.ktu@netbill.test')->value('id'),
                'reviewed_at'       => now()->subDays(2),
            ]);
        }

        // 6. Data Survei Demo (Surveyor KTU)
        $surveyorUser = User::where('email', 'surveyor.ktu@netbill.test')->first();
        $adminKtu = User::where('email', 'admin.ktu@netbill.test')->first();
        $ktuOdc = Odc::where('pop_id', $popKtu->id)->first();
        $ktuOdp = Odp::where('pop_id', $popKtu->id)->first();
        $ktuMaterials = Material::where('pop_id', $popKtu->id)->get()->keyBy('name');

        if ($surveyorUser && $adminKtu && $ktuOdc && $ktuOdp) {
            // Pelanggan 1 di KTU: Survei SUBMITTED (Layak)
            $pendingCust1 = Customer::create([
                'pop_id'         => $popKtu->id,
                'customer_code'  => 'JCN-KTU-SV01',
                'name'           => 'Surya Kencana (Survei Submitted)',
                'phone'          => '6281211112222',
                'address'        => 'Jl. Sudirman No. 45, Kota Tua',
                'subscribed_at'  => now()->format('Y-m-d'),
                'package'        => '20 Mbps',
                'billing_type'   => 'Bulanan',
                'service_status' => 'PENDING',
                'latitude'       => 0.5108000,
                'longitude'      => 101.4410000,
            ]);

            $survey1 = Survey::create([
                'customer_id'      => $pendingCust1->id,
                'pop_id'           => $popKtu->id,
                'surveyor_user_id' => $surveyorUser->id,
                'odc_id'           => $ktuOdc->id,
                'odp_id'           => $ktuOdp->id,
                'latitude'         => 0.5108000,
                'longitude'        => 101.4410000,
                'result'           => SurveyResult::FEASIBLE,
                'result_note'      => 'Jarak ke ODP 85 meter, tiang instalasi memadai.',
                'status'           => SurveyStatus::SUBMITTED,
            ]);

            if (isset($ktuMaterials['Kabel Dropcore 1 Core'])) {
                SurveyItem::create(['survey_id' => $survey1->id, 'material_id' => $ktuMaterials['Kabel Dropcore 1 Core']->id, 'quantity' => 100]);
            }
            if (isset($ktuMaterials['ONT ZTE F609'])) {
                SurveyItem::create(['survey_id' => $survey1->id, 'material_id' => $ktuMaterials['ONT ZTE F609']->id, 'quantity' => 1]);
            }
            if (isset($ktuMaterials['Konektor Fast Connector SC-UPC'])) {
                SurveyItem::create(['survey_id' => $survey1->id, 'material_id' => $ktuMaterials['Konektor Fast Connector SC-UPC']->id, 'quantity' => 2]);
            }

            // Pelanggan 2 di KTU: Survei RESURVEY_REQUESTED
            $pendingCust2 = Customer::create([
                'pop_id'         => $popKtu->id,
                'customer_code'  => 'JCN-KTU-SV02',
                'name'           => 'Hendra Wijaya (Minta Survei Ulang)',
                'phone'          => '6281233334444',
                'address'        => 'Jl. Ahmad Yani No. 10B, Kota Tua',
                'subscribed_at'  => now()->format('Y-m-d'),
                'package'        => '30 Mbps',
                'billing_type'   => 'Bulanan',
                'service_status' => 'PENDING',
                'latitude'       => 0.5120000,
                'longitude'      => 101.4430000,
            ]);

            $survey2 = Survey::create([
                'customer_id'      => $pendingCust2->id,
                'pop_id'           => $popKtu->id,
                'surveyor_user_id' => $surveyorUser->id,
                'odc_id'           => $ktuOdc->id,
                'odp_id'           => $ktuOdp->id,
                'latitude'         => 0.5120000,
                'longitude'        => 101.4430000,
                'result'           => SurveyResult::FEASIBLE,
                'result_note'      => 'Jarak ODP 160 meter.',
                'status'           => SurveyStatus::RESURVEY_REQUESTED,
                'review_note'      => 'Mohon periksa jalur penyeberangan kabel dropcore karena terhalang kabel PLN tegangan tinggi.',
                'reviewed_by'      => $adminKtu->id,
                'reviewed_at'      => now()->subHours(5),
            ]);

            if (isset($ktuMaterials['Kabel Dropcore 1 Core'])) {
                SurveyItem::create(['survey_id' => $survey2->id, 'material_id' => $ktuMaterials['Kabel Dropcore 1 Core']->id, 'quantity' => 170]);
            }

            // Pelanggan 3 di KTU: Survei CLOSED (Dibatalkan)
            $pendingCust3 = Customer::create([
                'pop_id'         => $popKtu->id,
                'customer_code'  => 'JCN-KTU-SV03',
                'name'           => 'Rudi Hartono (Dibatalkan)',
                'phone'          => '6281255556666',
                'address'        => 'Jl. Pelosok Ujung No. 99, Kota Tua',
                'subscribed_at'  => now()->format('Y-m-d'),
                'package'        => '10 Mbps',
                'billing_type'   => 'Bulanan',
                'service_status' => 'INACTIVE',
            ]);

            Survey::create([
                'customer_id'      => $pendingCust3->id,
                'pop_id'           => $popKtu->id,
                'surveyor_user_id' => $surveyorUser->id,
                'odc_id'           => $ktuOdc->id,
                'odp_id'           => $ktuOdp->id,
                'latitude'         => 0.5250000,
                'longitude'        => 101.4550000,
                'result'           => SurveyResult::NOT_FEASIBLE,
                'result_note'      => 'Jarak ODP terdekat > 900 meter, redaman tidak memungkinkan.',
                'status'           => SurveyStatus::CLOSED,
                'review_note'      => 'Dibatalkan: Jarak ODP melebihi standar jangkauan fiber optic dan tidak ada tiang sambung.',
                'reviewed_by'      => $adminKtu->id,
                'reviewed_at'      => now()->subDays(1),
            ]);

            // Pelanggan 4 di KTU: PENDING murni (masuk ke antrean survei)
            Customer::create([
                'pop_id'         => $popKtu->id,
                'customer_code'  => 'JCN-KTU-SV04',
                'name'           => 'Rina Marlina (Antrean Baru)',
                'phone'          => '6281277778888',
                'address'        => 'Jl. Teratai Indah No. 5, Kota Tua',
                'subscribed_at'  => now()->format('Y-m-d'),
                'package'        => '20 Mbps',
                'billing_type'   => 'Bulanan',
                'service_status' => 'PENDING',
                'latitude'       => 0.5115000,
                'longitude'      => 101.4425000,
            ]);

            // 7. Data Work Order Demo (Teknisi KTU)
            $technicianUser = User::where('email', 'technician.ktu@netbill.test')->first();
            if ($technicianUser) {
                // WO 1: ASSIGNED (Baru ditugaskan)
                $custWo1 = Customer::create([
                    'pop_id'         => $popKtu->id,
                    'customer_code'  => 'JCN-KTU-WO01',
                    'name'           => 'Budi Prasetyo (WO Baru Ditugaskan)',
                    'phone'          => '6281311112222',
                    'address'        => 'Jl. Cempaka Putih No. 12, Kota Tua',
                    'subscribed_at'  => now()->format('Y-m-d'),
                    'package'        => '30 Mbps',
                    'billing_type'   => 'Bulanan',
                    'service_status' => 'PENDING',
                    'odc_id'         => $ktuOdc->id,
                    'odp_id'         => $ktuOdp->id,
                    'latitude'       => 0.5103000,
                    'longitude'      => 101.4409000,
                ]);

                $surveyWo1 = Survey::create([
                    'customer_id'      => $custWo1->id,
                    'pop_id'           => $popKtu->id,
                    'surveyor_user_id' => $surveyorUser->id,
                    'odc_id'           => $ktuOdc->id,
                    'odp_id'           => $ktuOdp->id,
                    'latitude'         => 0.5103000,
                    'longitude'        => 101.4409000,
                    'result'           => SurveyResult::FEASIBLE,
                    'result_note'      => 'Layak pasang, tiang aman.',
                    'status'           => SurveyStatus::ASSIGNED,
                ]);

                WorkOrder::create([
                    'customer_id'        => $custWo1->id,
                    'survey_id'          => $surveyWo1->id,
                    'pop_id'             => $popKtu->id,
                    'technician_user_id' => $technicianUser->id,
                    'status'             => WorkOrderStatus::ASSIGNED,
                    'scheduled_date'     => now()->addDay()->format('Y-m-d'),
                    'admin_note'         => 'Pemasangan pagi sebelum jam 11.',
                    'assigned_by'        => $adminKtu->id,
                ]);

                // WO 2: SUBMITTED (Menunggu Verifikasi Admin)
                $custWo2 = Customer::create([
                    'pop_id'         => $popKtu->id,
                    'customer_code'  => 'JCN-KTU-WO02',
                    'name'           => 'Siti Rahma (WO Menunggu Verifikasi)',
                    'phone'          => '6281333334444',
                    'address'        => 'Jl. Mawar Melati No. 88, Kota Tua',
                    'subscribed_at'  => now()->format('Y-m-d'),
                    'package'        => '20 Mbps',
                    'billing_type'   => 'Bulanan',
                    'service_status' => 'PENDING',
                    'odc_id'         => $ktuOdc->id,
                    'odp_id'         => $ktuOdp->id,
                    'latitude'       => 0.5106000,
                    'longitude'      => 101.4415000,
                ]);

                $surveyWo2 = Survey::create([
                    'customer_id'      => $custWo2->id,
                    'pop_id'           => $popKtu->id,
                    'surveyor_user_id' => $surveyorUser->id,
                    'odc_id'           => $ktuOdc->id,
                    'odp_id'           => $ktuOdp->id,
                    'latitude'         => 0.5106000,
                    'longitude'        => 101.4415000,
                    'result'           => SurveyResult::FEASIBLE,
                    'result_note'      => 'Layak pasang.',
                    'status'           => SurveyStatus::ASSIGNED,
                ]);

                $wo2 = WorkOrder::create([
                    'customer_id'        => $custWo2->id,
                    'survey_id'          => $surveyWo2->id,
                    'pop_id'             => $popKtu->id,
                    'technician_user_id' => $technicianUser->id,
                    'status'             => WorkOrderStatus::SUBMITTED,
                    'scheduled_date'     => now()->format('Y-m-d'),
                    'completed_on'       => now()->format('Y-m-d'),
                    'admin_note'         => 'Prioritas pelanggan baru.',
                    'technician_notes'   => 'Instalasi selesai, redaman -19.4 dBm, sinyal sangat stabil.',
                    'assigned_by'        => $adminKtu->id,
                ]);

                // Buat berkas gambar nyata kecil (GD, 800x600 dengan teks "DEMO") lewat PrivateFileService (Fase 17f)
                PrivateFileService::createDemoPhoto('work_orders/demo_instalasi.jpg', 'DEMO INSTALASI');
                PrivateFileService::createDemoPhoto('work_orders/demo_aktivasi.jpg', 'DEMO AKTIVASI');

                WorkOrderPhoto::create([
                    'work_order_id' => $wo2->id,
                    'path'          => 'work_orders/demo_instalasi.jpg',
                    'stage'         => PhotoStage::INSTALASI,
                    'caption'       => 'Pemasangan kabel dropcore ke ODP tiang No. 4',
                    'uploaded_by'   => $technicianUser->id,
                ]);

                WorkOrderPhoto::create([
                    'work_order_id' => $wo2->id,
                    'path'          => 'work_orders/demo_aktivasi.jpg',
                    'stage'         => PhotoStage::AKTIVASI,
                    'caption'       => 'Indikator ONT ZTE menyala hijau dan internet lancar',
                    'uploaded_by'   => $technicianUser->id,
                ]);
            }

            // 6. Demo Tiket Komplain NOC (Fase 17a)
            $nocUser = User::where('email', 'noc@netbill.test')->first();
            $customerSample = Customer::where('pop_id', $popKtu->id)->first();
            $customerEp = Customer::where('pop_id', $popEp->id)->first();

            if ($nocUser && $customerSample && Ticket::count() === 0) {
                // Tiket 1: OPEN (Urgent, Gangguan Koneksi)
                $t1 = Ticket::createWithNumber([
                    'customer_id'    => $customerSample->id,
                    'pop_id'         => $customerSample->pop_id,
                    'reporter_name'  => $customerSample->name,
                    'reporter_phone' => $customerSample->phone ?? '081234567890',
                    'category'       => TicketCategory::CONNECTION,
                    'channel'        => TicketChannel::WHATSAPP,
                    'priority'       => TicketPriority::URGENT,
                    'subject'        => 'Lampu LOS Merah Berkedip pada ONT',
                    'description'    => 'Koneksi internet terputus sejak pagi, indikator LOS menyala merah berkedip.',
                    'status'         => TicketStatus::OPEN,
                    'created_by'     => $nocUser->id,
                    'opened_at'      => now()->subHours(2),
                ]);

                // Tiket 2: IN_PROGRESS (Normal, Pembayaran)
                $t2 = Ticket::createWithNumber([
                    'customer_id'       => $customerEp ? $customerEp->id : $customerSample->id,
                    'pop_id'            => $customerEp ? $customerEp->pop_id : $customerSample->pop_id,
                    'reporter_name'     => $customerEp ? $customerEp->name : 'Pelanggan EP',
                    'reporter_phone'    => '082211334455',
                    'category'          => TicketCategory::PAYMENT,
                    'channel'           => TicketChannel::PHONE,
                    'priority'          => TicketPriority::NORMAL,
                    'subject'           => 'Konfirmasi Pembayaran Tagihan Belum Terverifikasi',
                    'description'       => 'Pelanggan telah transfer via bank namun status layanan masih isolir.',
                    'status'            => TicketStatus::IN_PROGRESS,
                    'created_by'        => $nocUser->id,
                    'assigned_to'       => $nocUser->id,
                    'opened_at'         => now()->subHours(5),
                    'first_response_at' => now()->subHours(4),
                ]);
                TicketUpdate::create([
                    'ticket_id'   => $t2->id,
                    'user_id'     => $nocUser->id,
                    'type'        => TicketUpdateType::STATUS_CHANGE,
                    'from_status' => TicketStatus::OPEN,
                    'to_status'   => TicketStatus::IN_PROGRESS,
                    'body'        => 'Sedang dicek ke mutasi rekening koran bank oleh bagian NOC.',
                    'created_at'  => now()->subHours(4),
                ]);

                // Tiket 3: RESOLVED (High, Instalasi)
                $t3 = Ticket::createWithNumber([
                    'customer_id'       => $customerSample->id,
                    'pop_id'            => $customerSample->pop_id,
                    'reporter_name'     => $customerSample->name,
                    'reporter_phone'    => $customerSample->phone ?? '081234567890',
                    'category'          => TicketCategory::INSTALLATION,
                    'channel'           => TicketChannel::APP,
                    'priority'          => TicketPriority::HIGH,
                    'subject'           => 'Permintaan Relokasi Posisi Router WiFi',
                    'description'       => 'Pelanggan meminta pemindahan posisi ONT dari lantai 1 ke lantai 2.',
                    'status'            => TicketStatus::RESOLVED,
                    'created_by'        => $nocUser->id,
                    'assigned_to'       => $nocUser->id,
                    'opened_at'         => now()->subDays(1),
                    'first_response_at' => now()->subDays(1)->addMinutes(15),
                    'resolved_at'       => now()->subHours(1),
                    'resolution_note'   => 'Teknisi telah merelokasi kabel dan ONT ke lantai 2, koneksi lancar.',
                ]);
                TicketUpdate::create([
                    'ticket_id'   => $t3->id,
                    'user_id'     => $nocUser->id,
                    'type'        => TicketUpdateType::STATUS_CHANGE,
                    'from_status' => TicketStatus::IN_PROGRESS,
                    'to_status'   => TicketStatus::RESOLVED,
                    'body'        => 'Relokasi selesai dilaksanakan dengan baik.',
                    'created_at'  => now()->subHours(1),
                ]);

                // Tiket 4: CLOSED
                $t4 = Ticket::createWithNumber([
                    'customer_id'       => null,
                    'pop_id'            => $popKtu->id,
                    'reporter_name'     => 'Bapak H. Rahman',
                    'reporter_phone'    => '081399887766',
                    'category'          => TicketCategory::OTHER,
                    'channel'           => TicketChannel::WALK_IN,
                    'priority'          => TicketPriority::LOW,
                    'subject'           => 'Tanya Paket Internet untuk Ruko Baru',
                    'description'       => 'Pelapor datang ke kantor menanyakan ketersediaan port fiber di Jl. Sudirman.',
                    'status'            => TicketStatus::CLOSED,
                    'created_by'        => $nocUser->id,
                    'assigned_to'       => $nocUser->id,
                    'opened_at'         => now()->subDays(3),
                    'first_response_at' => now()->subDays(3)->addMinutes(10),
                    'resolved_at'       => now()->subDays(2),
                    'closed_at'         => now()->subDays(1),
                    'resolution_note'   => 'Informasi paket dan jadwal survei telah disampaikan ke pelapor.',
                ]);
            }
        }
    }
}
