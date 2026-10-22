<?php

if (! function_exists('rupiah')) {
    function rupiah(int|float|null $amount): string
    {
        return 'Rp' . number_format((int) $amount, 0, ',', '.');
    }
}

if (! function_exists('tgl_indo')) {
    function tgl_indo(\Carbon\Carbon|string|null $date): string
    {
        if (! $date) {
            return '—';
        }
        $d = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $d->day . ' ' . $months[$d->month] . ' ' . $d->year;
    }
}

if (! function_exists('bulan_indo')) {
    function bulan_indo(\Carbon\Carbon|string|null $date): string
    {
        if (! $date) {
            return '—';
        }
        $d = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        return $months[$d->month] . ' ' . $d->year;
    }
}
