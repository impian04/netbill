<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Material;
use App\Models\Odc;
use App\Models\Odp;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SurveyorQueueController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        if (! $user || ! $user->isSurveyor()) {
            abort(403, 'Akses khusus Surveyor.');
        }

        $popId = $user->pop_id;

        $search = trim(strip_tags((string) ($request->input('q') ?? $request->input('search', ''))));

        $query = Customer::query()
            ->where('customers.pop_id', $popId)
            ->where('customers.service_status', 'PENDING')
            ->whereDoesntHave('survey')
            // Keamanan data: HANYA kolom yang diperlukan, TANPA KTP, tagihan, marketing, fee
            ->select([
                'id',
                'customer_code',
                'name',
                'address',
                'phone',
                'latitude',
                'longitude',
                'odc_id',
                'odp_id',
                'pop_id',
                'created_at',
            ])
            ->with([
                'odc:id,name',
                'odp:id,name,odc_id',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('customers.customer_code', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('customers.phone', 'like', "%{$search}%")
                    ->orWhere('customers.address', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest('customers.id')->paginate(15)->withQueryString();

        // Master data ODC, ODP, Barang aktif di POP surveyor untuk form survei
        $odcs = Odc::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name']);
        $odps = Odp::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name', 'odc_id']);
        $materials = Material::where('pop_id', $popId)->active()->orderBy('name')->get(['id', 'name', 'unit']);

        return view('surveyor.queue', [
            'customers' => $customers,
            'odcs'      => $odcs,
            'odps'      => $odps,
            'materials' => $materials,
            'search'    => $search,
        ]);
    }
}

