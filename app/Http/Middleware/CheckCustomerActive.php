<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomerActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        if ($customer && $customer->service_status === 'INACTIVE') {
            return response()->json(['message' => 'Akun Anda tidak aktif. Hubungi layanan pelanggan.'], 403);
        }

        return $next($request);
    }
}
