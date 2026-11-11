<?php

namespace App\Imports;

use App\Http\Requests\StoreCustomerRequest;
use Carbon\Carbon;

class RowValidator
{
    /** Normalized header keys => display label */
    public const REQUIRED_HEADERS = [
        'id_pelanggan'               => 'ID Pelanggan',
        'nama'                       => 'Nama',
        'nomor_telepon'              => 'Nomor Telepon',
        'alamat'                     => 'Alamat',
        'tanggal_mulai_berlangganan' => 'Tanggal Mulai Berlangganan',
        'jenis_tagihan'              => 'Jenis Tagihan',
        'paket'                      => 'Paket',
        'status_layanan'             => 'Status Layanan',
        'periode'                    => 'Periode',
        'nominal_ditangguhkan'       => 'Nominal Ditangguhkan',
        'status'                     => 'Status',
    ];

    private const VALID_STATUSES         = ['paid', 'unpaid', 'lunas', 'belum bayar'];
    private const VALID_SERVICE_STATUSES = ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'];

    /** Returns array of error strings, empty = valid */
    public static function validate(array $row): array
    {
        $errors = [];

        // ID Pelanggan
        $code = trim($row['id_pelanggan'] ?? '');
        if ($code === '') {
            $errors[] = 'ID Pelanggan kosong';
        } elseif (! preg_match('/^[A-Z0-9\-]+$/i', $code)) {
            $errors[] = 'ID Pelanggan tidak valid: "' . $code . '"';
        }

        // Nama
        if (trim($row['nama'] ?? '') === '') {
            $errors[] = 'Nama kosong';
        }

        // Nomor Telepon (optional — empty allowed)
        $phone = trim((string) ($row['nomor_telepon'] ?? ''));
        if ($phone !== '') {
            if (preg_match('/^[0-9]+(\.[0-9]+)?[eE][\+\-]?[0-9]+$/i', $phone)) {
                $errors[] = 'Nomor telepon rusak oleh Excel. Gunakan template .xlsx atau format kolom sebagai Teks.';
            } else {
                $normalized = StoreCustomerRequest::normalizePhone($phone);
                if (! preg_match('/^62[0-9]{8,13}$/', $normalized)) {
                    $errors[] = 'Nomor Telepon tidak valid: "' . $phone . '" (format: 62xxxxxxxxxx)';
                }
            }
        }

        // Alamat
        if (trim($row['alamat'] ?? '') === '') {
            $errors[] = 'Alamat kosong';
        }

        // Tanggal Mulai Berlangganan
        $tgl = $row['tanggal_mulai_berlangganan'] ?? '';
        if ($tgl === '' || $tgl === null) {
            $errors[] = 'Tanggal Mulai Berlangganan kosong';
        } else {
            try {
                if (! self::parseFlexibleDate($tgl)) {
                    $errors[] = 'Tanggal tidak dapat diparsing: "' . $tgl . '"';
                }
            } catch (\Exception) {
                $errors[] = 'Tanggal tidak valid: "' . $tgl . '"';
            }
        }

        // Paket
        if (trim($row['paket'] ?? '') === '') {
            $errors[] = 'Paket kosong';
        }

        // Jenis Tagihan
        if (trim($row['jenis_tagihan'] ?? '') === '') {
            $errors[] = 'Jenis Tagihan kosong';
        }

        // Status Layanan (optional, default ACTIVE)
        $svcStatus = strtoupper(trim($row['status_layanan'] ?? 'ACTIVE'));
        if ($svcStatus !== '' && ! in_array($svcStatus, self::VALID_SERVICE_STATUSES, true)) {
            $errors[] = 'Status Layanan tidak valid: "' . ($row['status_layanan'] ?? '') . '" (gunakan ACTIVE/PENDING/SUSPENDED/INACTIVE)';
        }

        // Periode
        if (trim((string) ($row['periode'] ?? '')) === '') {
            $errors[] = 'Periode kosong';
        }

        // Nominal Ditangguhkan
        $nominal = self::parseAmount($row['nominal_ditangguhkan'] ?? '');
        if ($nominal === null) {
            $errors[] = 'Nominal tidak valid: "' . ($row['nominal_ditangguhkan'] ?? '') . '"';
        } elseif ($nominal < 0) {
            $errors[] = 'Nominal tidak boleh negatif';
        }

        // Status tagihan
        $status = strtolower(trim($row['status'] ?? ''));
        if (! in_array($status, self::VALID_STATUSES, true)) {
            $errors[] = 'Status tidak valid: "' . ($row['status'] ?? '') . '" (gunakan PAID atau UNPAID)';
        }

        // Jatuh Tempo (optional)
        $dueDateRaw = trim((string) ($row['jatuh_tempo'] ?? $row['due_date'] ?? ''));
        if ($dueDateRaw !== '') {
            try {
                if (! self::parseFlexibleDate($dueDateRaw)) {
                    $errors[] = 'Jatuh Tempo tidak valid: "' . $dueDateRaw . '"';
                }
            } catch (\Exception) {
                $errors[] = 'Jatuh Tempo tidak valid: "' . $dueDateRaw . '"';
            }
        }

        return $errors;
    }

    /** Normalize raw header string to snake_case key */
    public static function normalizeHeader(string $header): string
    {
        $h = strtolower(trim($header));
        $h = preg_replace('/[\s\-\/]+/', '_', $h);
        $h = preg_replace('/[^a-z0-9_]/', '', $h);
        return $h;
    }

    public static function parseAmount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', (string) $value);
        return $clean === '' ? null : (int) $clean;
    }

    public static function parseFlexibleDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }
        if (is_numeric($str) && (float) $str > 20000 && (float) $str < 100000) {
            try {
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $str))->format('Y-m-d');
            } catch (\Throwable) {
                return Carbon::createFromTimestamp(((float) $str - 25569) * 86400)->format('Y-m-d');
            }
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $str, $m)) {
            try {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
            } catch (\Throwable) {}
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $str)->format('Y-m-d');
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    public static function parsePeriod(mixed $value): ?string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $str)) {
            try {
                return Carbon::createFromFormat('Y-m', $str)->startOfMonth()->format('Y-m-d');
            } catch (\Exception) {}
        }
        $idToEn = [
            'januari' => 'January', 'februari' => 'February', 'maret' => 'March',
            'april' => 'April', 'mei' => 'May', 'juni' => 'June',
            'juli' => 'July', 'agustus' => 'August', 'september' => 'September',
            'oktober' => 'October', 'november' => 'November', 'desember' => 'December',
        ];
        $normalized = str_ireplace(array_keys($idToEn), array_values($idToEn), strtolower($str));
        try {
            return Carbon::parse($normalized)->startOfMonth()->format('Y-m-d');
        } catch (\Exception) {}
        return null;
    }

    public static function normalizeStatus(string $value): string
    {
        $v = strtolower(trim($value));
        return in_array($v, ['paid', 'lunas'], true) ? 'PAID' : 'UNPAID';
    }

    public static function normalizeServiceStatus(string $value): string
    {
        $v = strtoupper(trim($value));
        return in_array($v, ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'], true) ? $v : 'ACTIVE';
    }
}
