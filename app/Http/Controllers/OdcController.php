<?php

namespace App\Http\Controllers;

use App\Models\Odc;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OdcController extends Controller
{
    private function checkAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            abort(403, 'Akses ditolak. Hanya Admin dan Super Admin yang dapat mengelola master ODC.');
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

        return view('odcs.index', compact('pops'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();
        $popId = $user->isSuperAdmin() ? (int) $request->input('pop_id') : (int) $user->pop_id;

        $request->validate([
            'pop_id'    => $user->isSuperAdmin() ? ['required', 'exists:pops,id'] : ['nullable'],
            'name'      => ['required', 'string', 'max:100', Rule::unique('odcs')->where('pop_id', $popId)],
            'latitude'  => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude' => ['nullable', 'numeric', 'between:94.0,141.5'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama ODC wajib diisi.',
            'name.unique'   => 'Nama ODC sudah digunakan pada POP ini.',
            'pop_id.required' => 'POP wajib dipilih.',
            'latitude.between' => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5).',
            'longitude.between' => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5).',
        ]);

        $odc = Odc::create([
            'pop_id'    => $popId,
            'name'      => trim($request->input('name')),
            'latitude'  => $request->input('latitude') !== null && $request->input('latitude') !== '' ? (float) $request->input('latitude') : null,
            'longitude' => $request->input('longitude') !== null && $request->input('longitude') !== '' ? (float) $request->input('longitude') : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $pop = Pop::find($popId);
        ActivityLogger::log('odc.create', "Menambahkan ODC {$odc->name} di POP {$pop?->code}", $odc, $popId);

        return response()->json([
            'message' => 'ODC berhasil ditambahkan.',
            'data'    => $odc,
        ], 201);
    }

    public function show(Request $request, Odc $odc): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odc->pop_id !== $user->pop_id) {
            abort(404);
        }

        $data = $odc->load('pop')->toArray();
        $data['coordinates'] = ($odc->latitude !== null && $odc->longitude !== null)
            ? "{$odc->latitude}, {$odc->longitude}"
            : '';
        $data['is_used'] = $odc->isUsed();

        return response()->json($data);
    }

    public function update(Request $request, Odc $odc): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odc->pop_id !== $user->pop_id) {
            abort(404);
        }

        $request->validate([
            'name'      => ['required', 'string', 'max:100', Rule::unique('odcs')->where('pop_id', $odc->pop_id)->ignore($odc->id)],
            'latitude'  => ['nullable', 'numeric', 'between:-11.5,6.5'],
            'longitude' => ['nullable', 'numeric', 'between:94.0,141.5'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama ODC wajib diisi.',
            'name.unique'   => 'Nama ODC sudah digunakan pada POP ini.',
            'latitude.between' => 'Latitude harus berada di wilayah Indonesia (-11.5 s/d 6.5).',
            'longitude.between' => 'Longitude harus berada di wilayah Indonesia (94.0 s/d 141.5).',
        ]);

        $odc->update([
            'name'      => trim($request->input('name')),
            'latitude'  => $request->input('latitude') !== null && $request->input('latitude') !== '' ? (float) $request->input('latitude') : null,
            'longitude' => $request->input('longitude') !== null && $request->input('longitude') !== '' ? (float) $request->input('longitude') : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLogger::log('odc.update', "Memperbarui ODC {$odc->name} di POP {$odc->pop?->code}", $odc, $odc->pop_id);

        return response()->json([
            'message' => 'ODC berhasil diperbarui.',
            'data'    => $odc,
        ]);
    }

    public function destroy(Request $request, Odc $odc): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $odc->pop_id !== $user->pop_id) {
            abort(404);
        }

        if ($odc->isUsed()) {
            return response()->json([
                'message' => "ODC '{$odc->name}' tidak dapat dihapus karena sedang digunakan oleh ODP atau pelanggan. Anda dapat menonaktifkannya.",
            ], 422);
        }

        $name = $odc->name;
        $popId = $odc->pop_id;
        $odc->delete();

        ActivityLogger::log('odc.delete', "Menghapus ODC {$name}", null, $popId);

        return response()->json([
            'message' => "ODC '{$name}' berhasil dihapus.",
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

        $query = Odc::query()->visibleTo($user)->with('pop:id,code,name')->withCount(['odps', 'customers']);

        if ($user->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('odcs.pop_id', (int) $request->input('pop_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('odcs.is_active', (bool) $request->input('is_active'));
        }

        if ($search !== '') {
            $query->where('odcs.name', 'like', "%{$search}%");
        }

        $recordsTotal = Odc::visibleTo($user)->count();
        $recordsFiltered = (clone $query)->count();

        $odcs = $query->orderBy('name', 'asc')->offset($start)->limit($length)->get();

        $data = $odcs->map(function (Odc $item) {
            $isUsed = ($item->odps_count > 0 || $item->customers_count > 0);
            return [
                'id'              => $item->id,
                'name'            => $item->name,
                'pop_code'        => $item->pop?->code,
                'pop_name'        => $item->pop?->name,
                'latitude'        => $item->latitude,
                'longitude'       => $item->longitude,
                'coordinates'     => ($item->latitude !== null && $item->longitude !== null) ? "{$item->latitude}, {$item->longitude}" : '—',
                'odps_count'      => $item->odps_count,
                'customers_count' => $item->customers_count,
                'is_active'       => $item->is_active,
                'is_used'         => $isUsed,
                'urls'            => [
                    'show'   => route('odcs.show', $item),
                    'update' => route('odcs.update', $item),
                    'delete' => route('odcs.destroy', $item),
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

