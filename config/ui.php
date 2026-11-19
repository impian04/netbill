<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pemetaan Status & Badge UI NetBill (Fase 17h)
    |--------------------------------------------------------------------------
    | Semantik warna baku:
    |   selesai / lunas / aktif            = success
    |   menunggu / proses                  = warning
    |   ditolak / batal / mendesak / nonaktif = danger
    |   informasi / baru                   = info
    |   netral / final                     = secondary
    */
    'badges' => [
        'service_status' => [
            'ACTIVE'     => ['label' => 'Aktif',      'color' => 'success'],
            'ISOLATED'   => ['label' => 'Terisolir',  'color' => 'warning'],
            'SUSPENDED'  => ['label' => 'Suspend',    'color' => 'warning'],
            'TERMINATED' => ['label' => 'Berhenti',   'color' => 'danger'],
            'INACTIVE'   => ['label' => 'Nonaktif',   'color' => 'danger'],
        ],

        'invoice_status' => [
            'PAID'      => ['label' => 'Lunas',         'color' => 'success'],
            'UNPAID'    => ['label' => 'Belum Lunas',   'color' => 'warning'],
            'OVERDUE'   => ['label' => 'Jatuh Tempo',   'color' => 'danger'],
            'CANCELLED' => ['label' => 'Dibatalkan',    'color' => 'secondary'],
        ],

        'application_status' => [
            'SUBMITTED'      => ['label' => 'Menunggu Review', 'color' => 'warning'],
            'NEEDS_REVISION' => ['label' => 'Perlu Perbaikan', 'color' => 'warning'],
            'APPROVED'       => ['label' => 'Disetujui',       'color' => 'success'],
            'REJECTED'       => ['label' => 'Ditolak',         'color' => 'danger'],
        ],

        'survey_status' => [
            'SUBMITTED'          => ['label' => 'Menunggu Review',   'color' => 'warning'],
            'RESURVEY_REQUESTED' => ['label' => 'Perlu Survei Ulang', 'color' => 'warning'],
            'ASSIGNED'           => ['label' => 'Ditugaskan WO',     'color' => 'info'],
            'CLOSED'             => ['label' => 'Selesai',           'color' => 'secondary'],
        ],

        'survey_result' => [
            'FEASIBLE'     => ['label' => 'Layak',       'color' => 'success'],
            'NOT_FEASIBLE' => ['label' => 'Tidak Layak', 'color' => 'danger'],
        ],

        'work_order_status' => [
            'REQUESTED'      => ['label' => 'Menunggu Penugasan',    'color' => 'warning'],
            'ASSIGNED'       => ['label' => 'Ditugaskan',             'color' => 'info'],
            'IN_PROGRESS'    => ['label' => 'Dikerjakan',             'color' => 'warning'],
            'SUBMITTED'      => ['label' => 'Menunggu Verifikasi',    'color' => 'warning'],
            'NEEDS_REVISION' => ['label' => 'Perlu Perbaikan',        'color' => 'warning'],
            'VERIFIED'       => ['label' => 'Terverifikasi & Selesai', 'color' => 'success'],
            'CANCELLED'      => ['label' => 'Dibatalkan',             'color' => 'danger'],
        ],

        'work_order_type' => [
            'INSTALLATION' => ['label' => 'Instalasi Baru', 'color' => 'info'],
            'REPAIR'       => ['label' => 'Perbaikan',      'color' => 'warning'],
        ],

        'ticket_status' => [
            'OPEN'             => ['label' => 'Terbuka',            'color' => 'info'],
            'IN_PROGRESS'      => ['label' => 'Diproses',           'color' => 'warning'],
            'WAITING_CUSTOMER' => ['label' => 'Menunggu Pelanggan', 'color' => 'warning'],
            'FIELD_VISIT'      => ['label' => 'Kunjungan Lapangan', 'color' => 'warning'],
            'RESOLVED'         => ['label' => 'Terselesaikan',      'color' => 'success'],
            'CLOSED'           => ['label' => 'Ditutup',            'color' => 'secondary'],
        ],

        'ticket_priority' => [
            'LOW'    => ['label' => 'Rendah',   'color' => 'secondary'],
            'NORMAL' => ['label' => 'Normal',   'color' => 'info'],
            'HIGH'   => ['label' => 'Tinggi',   'color' => 'warning'],
            'URGENT' => ['label' => 'Mendesak', 'color' => 'danger'],
        ],

        'fee_status' => [
            'PENDING'   => ['label' => 'Menunggu Pembayaran', 'color' => 'warning'],
            'PAID'      => ['label' => 'Sudah Dibayar',       'color' => 'success'],
            'CANCELLED' => ['label' => 'Dibatalkan',          'color' => 'secondary'],
        ],

        'role' => [
            'super_admin' => ['label' => 'Super Admin', 'color' => 'danger'],
            'admin'       => ['label' => 'Admin',       'color' => 'info'],
            'marketing'   => ['label' => 'Marketing',   'color' => 'success'],
            'surveyor'    => ['label' => 'Surveyor',    'color' => 'warning'],
            'technician'  => ['label' => 'Teknisi',     'color' => 'info'],
            'noc'         => ['label' => 'NOC',         'color' => 'secondary'],
        ],

        'active_status' => [
            '1'        => ['label' => 'Aktif',    'color' => 'success'],
            '0'        => ['label' => 'Nonaktif', 'color' => 'danger'],
            'true'     => ['label' => 'Aktif',    'color' => 'success'],
            'false'    => ['label' => 'Nonaktif', 'color' => 'danger'],
            'ACTIVE'   => ['label' => 'Aktif',    'color' => 'success'],
            'INACTIVE' => ['label' => 'Nonaktif', 'color' => 'danger'],
        ],

        'photo_stage' => [
            'INSTALASI' => ['label' => 'Instalasi Fisik', 'color' => 'info'],
            'AKTIVASI'  => ['label' => 'Aktivasi',        'color' => 'success'],
            'PERBAIKAN' => ['label' => 'Perbaikan',       'color' => 'warning'],
            'LAINNYA'   => ['label' => 'Lainnya',         'color' => 'secondary'],
        ],
    ],
];

