<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    private const NOC_KEYS = ['noc_connection_wa', 'noc_payment_wa', 'noc_installation_wa'];

    public function edit(): View
    {
        $settings = DB::table('settings')
            ->whereIn('key', self::NOC_KEYS)
            ->pluck('value', 'key');

        return view('settings.noc', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'noc_connection_wa'   => ['nullable', 'regex:/^62[0-9]{7,14}$/'],
            'noc_payment_wa'      => ['nullable', 'regex:/^62[0-9]{7,14}$/'],
            'noc_installation_wa' => ['nullable', 'regex:/^62[0-9]{7,14}$/'],
        ], [
            'noc_connection_wa.regex'   => 'Format nomor: 62xxxxxxxxxx',
            'noc_payment_wa.regex'      => 'Format nomor: 62xxxxxxxxxx',
            'noc_installation_wa.regex' => 'Format nomor: 62xxxxxxxxxx',
        ]);

        foreach (self::NOC_KEYS as $key) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $data[$key] ?? null, 'updated_at' => now()]
            );
        }

        return redirect()->route('settings.noc')
            ->with('success', 'Kontak NOC berhasil disimpan.');
    }

    public function editFee(): View
    {
        $defaultFee = DB::table('settings')->where('key', 'marketing_fee_default')->value('value');
        return view('settings.fee', compact('defaultFee'));
    }

    public function updateFee(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'marketing_fee_default' => ['nullable', 'numeric', 'min:0'],
        ], [
            'marketing_fee_default.numeric' => 'Nominal fee bawaan harus berupa angka.',
            'marketing_fee_default.min'     => 'Nominal fee bawaan tidak boleh negatif.',
        ]);

        $val = ($data['marketing_fee_default'] !== null && $data['marketing_fee_default'] !== '')
            ? (int) $data['marketing_fee_default']
            : null;

        DB::table('settings')->updateOrInsert(
            ['key' => 'marketing_fee_default'],
            ['value' => $val, 'updated_at' => now()]
        );

        return redirect()->route('settings.fee')
            ->with('success', 'Pengaturan fee marketing berhasil disimpan.');
    }
}
