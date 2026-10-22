<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    /** GET /api/v1/support-contacts */
    public function index(): JsonResponse
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['noc_connection_wa', 'noc_payment_wa', 'noc_installation_wa'])
            ->pluck('value', 'key');

        return response()->json([
            'data' => [
                [
                    'category' => 'connection',
                    'label'    => 'Masalah Koneksi',
                    'phone'    => $rows['noc_connection_wa'] ?? null,
                ],
                [
                    'category' => 'payment',
                    'label'    => 'Masalah Pembayaran',
                    'phone'    => $rows['noc_payment_wa'] ?? null,
                ],
                [
                    'category' => 'installation',
                    'label'    => 'Bantuan Pemasangan/Pemasaran',
                    'phone'    => $rows['noc_installation_wa'] ?? null,
                ],
            ],
        ]);
    }
}
