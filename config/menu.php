<?php

use App\Enums\Role;

return [
    /*
    |--------------------------------------------------------------------------
    | Landing Routes Per Role
    |--------------------------------------------------------------------------
    |
    | Menentukan route awal tempat pengguna diarahkan setelah login atau saat
    | mengakses /portal jika perannya sudah memiliki menu operasional mandiri.
    |
    */
    'landing_routes' => [
        Role::SUPER_ADMIN->value => 'dashboard',
        Role::ADMIN->value       => 'dashboard',
        Role::MARKETING->value   => 'marketing.applications.index',
        Role::SURVEYOR->value    => 'surveyor.queue.index',
        Role::TECHNICIAN->value  => 'technician.tasks.index',
        Role::NOC->value         => 'tickets.index',
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Per Role
    |--------------------------------------------------------------------------
    |
    | Struktur menu per peran untuk sidebar dan navigasi offcanvas.
    | Setiap item memuat: label, route, icon, active_pattern, dan badge_key.
    |
    */
    'items' => [
        Role::MARKETING->value => [
            [
                'section' => 'Pendaftaran',
                'items'   => [
                    [
                        'label'          => 'Pendaftaran Saya',
                        'route'          => 'marketing.applications.index',
                        'icon'           => 'bi-person-lines-fill',
                        'active_pattern' => 'marketing.applications.*',
                    ],
                ],
            ],
        ],

        Role::SURVEYOR->value => [
            [
                'section' => 'Survei Lapangan',
                'items'   => [
                    [
                        'label'          => 'Antrean Survei',
                        'route'          => 'surveyor.queue.index',
                        'icon'           => 'bi-hourglass-split',
                        'active_pattern' => 'surveyor.queue.*',
                        'badge_key'      => 'survey_queue',
                    ],
                    [
                        'label'          => 'Survei Saya',
                        'route'          => 'surveyor.surveys.index',
                        'icon'           => 'bi-clipboard2-check',
                        'active_pattern' => 'surveyor.surveys.*',
                    ],
                ],
            ],
        ],

        Role::TECHNICIAN->value => [
            [
                'section' => 'Instalasi & Aktivasi',
                'items'   => [
                    [
                        'label'          => 'Tugas Saya',
                        'route'          => 'technician.tasks.index',
                        'icon'           => 'bi-tools',
                        'active_pattern' => 'technician.tasks.*',
                        'badge_key'      => 'technician_active_tasks',
                    ],
                ],
            ],
        ],

        Role::ADMIN->value => [
            [
                'section' => 'Menu',
                'items'   => [
                    [
                        'label'          => 'Dashboard',
                        'route'          => 'dashboard',
                        'icon'           => 'bi-speedometer2',
                        'active_pattern' => 'dashboard',
                    ],
                    [
                        'label'          => 'Pendaftaran Baru',
                        'route'          => 'customer-applications.index',
                        'icon'           => 'bi-clipboard-check',
                        'active_pattern' => 'customer-applications.*',
                    ],
                    [
                        'label'          => 'Pelanggan',
                        'route'          => 'customers.index',
                        'icon'           => 'bi-people',
                        'active_pattern' => 'customers.*',
                    ],
                    [
                        'label'          => 'Survei',
                        'route'          => 'surveys.index',
                        'icon'           => 'bi-clipboard2-check',
                        'active_pattern' => 'surveys.*',
                        'badge_key'      => 'unassigned_surveys',
                    ],
                    [
                        'label'          => 'Penugasan',
                        'route'          => 'work-orders.index',
                        'icon'           => 'bi-tools',
                        'active_pattern' => 'work-orders.*',
                        'badge_key'      => 'pending_work_orders',
                    ],
                    [
                        'label'          => 'Tiket',
                        'route'          => 'tickets.index',
                        'icon'           => 'bi-ticket-detailed',
                        'active_pattern' => 'tickets.*',
                        'badge_key'      => 'open_tickets',
                    ],
                    [
                        'label'          => 'Tagihan',
                        'route'          => 'invoices.index',
                        'icon'           => 'bi-receipt',
                        'active_pattern' => 'invoices.*',
                    ],
                    [
                        'label'          => 'Marketing',
                        'route'          => 'marketing-profiles.index',
                        'icon'           => 'bi-person-badge',
                        'active_pattern' => 'marketing-profiles.*',
                    ],
                    [
                        'label'          => 'Fee Marketing',
                        'route'          => 'marketing-fees.index',
                        'icon'           => 'bi-cash-stack',
                        'active_pattern' => 'marketing-fees.*',
                    ],
                    [
                        'label'          => 'Import Data',
                        'route'          => 'imports.index',
                        'icon'           => 'bi-upload',
                        'active_pattern' => 'imports.*',
                    ],
                ],
            ],
            [
                'section' => 'Master Jaringan & Logistik',
                'items'   => [
                    [
                        'label'          => 'Master ODC',
                        'route'          => 'odcs.index',
                        'icon'           => 'bi-hdd-network',
                        'active_pattern' => 'odcs.*',
                    ],
                    [
                        'label'          => 'Master ODP',
                        'route'          => 'odps.index',
                        'icon'           => 'bi-diagram-3',
                        'active_pattern' => 'odps.*',
                    ],
                    [
                        'label'          => 'Master Barang',
                        'route'          => 'materials.index',
                        'icon'           => 'bi-box-seam',
                        'active_pattern' => 'materials.*',
                    ],
                ],
            ],
        ],

        Role::SUPER_ADMIN->value => [
            // Mewarisi menu admin, plus administrasi di bawah
            'inherits' => Role::ADMIN->value,
            'additional_sections' => [
                [
                    'section' => 'Administrasi',
                    'items'   => [
                        [
                            'label'          => 'Pengumuman',
                            'route'          => 'announcements.index',
                            'icon'           => 'bi-megaphone',
                            'active_pattern' => 'announcements.*',
                        ],
                        [
                            'label'          => 'Pengaturan Fee',
                            'route'          => 'settings.fee',
                            'icon'           => 'bi-gear',
                            'active_pattern' => 'settings.fee*',
                        ],
                        [
                            'label'          => 'Kontak NOC',
                            'route'          => 'settings.noc',
                            'icon'           => 'bi-telephone',
                            'active_pattern' => 'settings.noc*',
                        ],
                        [
                            'label'          => 'Manajemen POP',
                            'route'          => 'pops.index',
                            'icon'           => 'bi-geo-alt',
                            'active_pattern' => 'pops.*',
                        ],
                        [
                            'label'          => 'Manajemen Staf',
                            'route'          => 'staff.index',
                            'icon'           => 'bi-person-badge',
                            'active_pattern' => 'staff.*',
                        ],
                        [
                            'label'          => 'Catatan Aktivitas',
                            'route'          => 'activity-logs.index',
                            'icon'           => 'bi-journal-text',
                            'active_pattern' => 'activity-logs.*',
                        ],
                    ],
                ],
            ],
        ],

        Role::NOC->value => [
            [
                'section' => 'Operasional NOC',
                'items'   => [
                    [
                        'label'          => 'Tiket',
                        'route'          => 'tickets.index',
                        'icon'           => 'bi-ticket-detailed',
                        'active_pattern' => 'tickets.*',
                        'badge_key'      => 'open_tickets',
                    ],
                    [
                        'label'          => 'Cari Pelanggan',
                        'route'          => 'noc.customers.index',
                        'icon'           => 'bi-search',
                        'active_pattern' => 'noc.customers.*',
                    ],
                ],
            ],
        ],
    ],
];

