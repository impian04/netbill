<?php

namespace App\Http\Controllers;

use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdpController extends Controller
{
    private function checkAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            abort(403, 'Akses ditolak. Hanya Admin dan Super Admin yang dapat mengelola master ODP.');
        }
    }

    public function index(Request $request): View|JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        $pops = $user->isSuperAdmin()
            ? Pop::where('is_active', true)->orderBy('name')->get()
            : collect([$user->pop]);

        $odcs = Odc::visibleTo($user)->active()->orderBy('name')->get(['id', 'pop_id', 'name']);

        return view('odps.index', compact('pops', 'odcs'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();
        $popId = $user->isSuperAdmin() ? (int) $request->input('pop_id') : (int) $user->pop_id;

        $request->validate([
            'pop_id'    => $user->isSuperAdmin() ? ['required', 'exists:pops,id'] : ['nullable'],
            'odc_id'    => ['required', 'exists:odcs,id'],
            'name'      => ['required', 'string', 'max:100', Rule::unique('odps')->where('pop_id', $popId)],
            'latitude'  => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude' => ['nullable', 'numeric', 'between:94.0,141.5'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required'     => 'Nama ODP wajib diisi.',
            'name.unique'       => 'Nama ODP sudah digunakan pada POP ini.',
            'odc_id.required'   => 'ODC induk wajib dipilih.',
            'odc_id.exists'     => 'ODC yang dipilih tidak valid.',
            'pop_id.required'   => 'POP wajib dipilih.',
            'latitude.between'  => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5).',
            'longitude.between' => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5).',
        ]);

        // Pastikan ODC milik POP terkait
        $odc = Odc::where('id', $request->input('odc_id'))->where('pop_id', $popId)->first();
        if (! $odc) {
            return response()->json([
                'message' => 'ODC yang dipilih bukan milik POP terkait.',
                'errors'  => ['odc_id' => ['ODC yang dipilih bukan milik POP terkait.']],
            ], 422);
        }

        $odp = Odp::create([
            'pop_id'    => $popId,
            'odc_id'    => $odc->id,
            'name'      => trim($request->input('name')),
            'latitude'  => $request->input('latitude') !== null && $request->input('latitude') !== '' ? (float) $request->input('latitude') : null,
            'longitude' => $request->input('longitude') !== null && $request->input('longitude') !== '' ? (float) $request->input('longitude') : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $pop = Pop::find($popId);
        ActivityLogger::log('odp.create', "Menambahkan ODP {$odp->name} di ODC {$odc->name} (POP {$pop?->code})", $odp, $popId);

        return response()->json([
            'message' => 'ODP berhasil ditambahkan.',
            'data'    => $odp,
        ], 201);
    }

    public function show(Request $request, Odp $odp): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odp->pop_id !== $user->pop_id) {
            abort(404);
        }

        $data = $odp->load(['pop', 'odc'])->toArray();
        $data['coordinates'] = ($odp->latitude !== null && $odp->longitude !== null)
            ? "{$odp->latitude}, {$odp->longitude}"
            : '';
        $data['is_used'] = $odp->isUsed();

        return response()->json($data);
    }

    public function update(Request $request, Odp $odp): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odp->pop_id !== $user->pop_id) {
            abort(404);
        }

        $request->validate([
            'odc_id'    => ['required', 'exists:odcs,id'],
            'name'      => ['required', 'string', 'max:100', Rule::unique('odps')->where('pop_id', $odp->pop_id)->ignore($odp->id)],
            'latitude'  => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude' => ['nullable', 'numeric', 'between:94.0,141.5'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required'     => 'Nama ODP wajib diisi.',
            'name.unique'       => 'Nama ODP sudah digunakan pada POP ini.',
            'odc_id.required'   => 'ODC induk wajib dipilih.',
            'odc_id.exists'     => 'ODC yang dipilih tidak valid.',
            'latitude.between'  => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5).',
            'longitude.between' => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5).',
        ]);

        $odc = Odc::where('id', $request->input('odc_id'))->where('pop_id', $odp->pop_id)->first();
        if (! $odc) {
            return response()->json([
                'message' => 'ODC yang dipilih bukan milik POP terkait.',
                'errors'  => ['odc_id' => ['ODC yang dipilih bukan milik POP terkait.']],
            ], 422);
        }

        $odp->update([
            'odc_id'    => $odc->id,
            'name'      => trim($request->input('name')),
            'latitude'  => $request->input('latitude') !== null && $request->input('latitude') !== '' ? (float) $request->input('latitude') : null,
            'longitude' => $request->input('longitude') !== null && $request->input('longitude') !== '' ? (float) $request->input('longitude') : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLogger::log('odp.update', "Memperbarui ODP {$odp->name} di POP {$odp->pop?->code}", $odp, $odp->pop_id);

        return response()->json([
            'message' => 'ODP berhasil diperbarui.',
            'data'    => $odp,
        ]);
    }

    public function destroy(Request $request, Odp $odp): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odp->pop_id !== $user->pop_id) {
            abort(404);
        }

        if ($odp->isUsed()) {
            return response()->json([
                'message' => "ODP '{$odp->name}' tidak dapat dihapus karena sedang digunakan oleh pelanggan. Anda dapat menonaktifkannya.",
            ], 422);
        }

        $name = $odp->name;
        $popId = $odp->pop_id;
        $odp->delete();

        ActivityLogger::log('odp.delete', "Menghapus ODP {$name}", null, $popId);

        return response()->json([
            'message' => "ODP '{$name}' berhasil dihapus.",
        ]);
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $user = auth()->user();
        $draw = $request->integer('draw', 1);
        $start = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);

        $query = Odp::query()->visibleTo($user)->with(['pop:id,code,name', 'odc:id,name'])->withCount('customers');

        if ($user->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('odps.pop_id', (int) $request->input('pop_id'));
        }

        if ($request->filled('odc_id')) {
            $query->where('odps.odc_id', (int) $request->input('odc_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('odps.is_active', (bool) $request->input('is_active'));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('odps.name', 'like', "%{$search}%")
                  ->orWhereHas('odc', fn ($oq) => $oq->where('name', 'like', "%{$search}%"));
            });
        }

        $recordsTotal = Odp::visibleTo($user)->count();
        $recordsFiltered = (clone $query)->count();

        $odps = $query->orderBy('name', 'asc')->offset($start)->limit($length)->get();

        $data = $odps->map(function (Odp $item) {
            $isUsed = ($item->customers_count > 0);
            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'odc_id'          => $item->odc_id,
                'odc_name'        => $item->odc?->name,
                'pop_code'        => $item->pop?->code,
                'pop_name'        => $item->pop?->name,
                'latitude'        => $item->latitude,
                'longitude'       => $item->longitude,
                'coordinates'     => ($item->latitude !== null && $item->longitude !== null) ? "{$item->latitude}, {$item->longitude}" : '—',
                'customers_count' => $item->customers_count,
                'is_active'       => $item->is_active,
                'is_used'         => $isUsed,
                'urls'            => [
                    'show'   => route('odps.show', $item),
                    'update' => route('odps.update', $item),
                    'delete' => route('odps.destroy', $item),
                ],
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }
}

