<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaterialController extends Controller
{
    private function checkAccess(): void
    {
        $user = auth()->user();
        if (! $user || (! $user->isSuperAdmin() && ! $user->isAdmin())) {
            abort(403, 'Akses ditolak. Hanya Admin dan Super Admin yang dapat mengelola master Barang.');
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

        $units = Material::UNITS;

        return view('materials.index', compact('pops', 'units'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();
        $popId = $user->isSuperAdmin() ? (int) $request->input('pop_id') : (int) $user->pop_id;

        $request->validate([
            'pop_id'    => $user->isSuperAdmin() ? ['required', 'exists:pops,id'] : ['nullable'],
            'name'      => ['required', 'string', 'max:100', Rule::unique('materials')->where('pop_id', $popId)],
            'unit'      => ['required', 'string', Rule::in(Material::UNITS)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama barang wajib diisi.',
            'name.unique'   => 'Nama barang sudah ada pada POP ini.',
            'unit.required' => 'Satuan barang wajib dipilih.',
            'unit.in'       => 'Satuan barang tidak valid. Pilihan: ' . implode(', ', Material::UNITS),
            'pop_id.required' => 'POP wajib dipilih.',
        ]);

        $material = Material::create([
            'pop_id'    => $popId,
            'name'      => trim($request->input('name')),
            'unit'      => strtolower(trim($request->input('unit'))),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $pop = Pop::find($popId);
        ActivityLogger::log('material.create', "Menambahkan barang {$material->name} ({$material->unit}) di POP {$pop?->code}", $material, $popId);

        return response()->json([
            'message' => 'Barang berhasil ditambahkan.',
            'data'    => $material,
        ], 201);
    }

    public function show(Request $request, Material $material): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $material->pop_id !== $user->pop_id) {
            abort(404);
        }

        $data = $material->load('pop')->toArray();
        $data['is_used'] = $material->isUsed();

        return response()->json($data);
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $material->pop_id !== $user->pop_id) {
            abort(404);
        }

        $request->validate([
            'name'      => ['required', 'string', 'max:100', Rule::unique('materials')->where('pop_id', $material->pop_id)->ignore($material->id)],
            'unit'      => ['required', 'string', Rule::in(Material::UNITS)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Nama barang wajib diisi.',
            'name.unique'   => 'Nama barang sudah ada pada POP ini.',
            'unit.required' => 'Satuan barang wajib dipilih.',
            'unit.in'       => 'Satuan barang tidak valid. Pilihan: ' . implode(', ', Material::UNITS),
        ]);

        $material->update([
            'name'      => trim($request->input('name')),
            'unit'      => strtolower(trim($request->input('unit'))),
            'is_active' => $request->boolean('is_active', true),
        ]);

        ActivityLogger::log('material.update', "Memperbarui barang {$material->name} di POP {$material->pop?->code}", $material, $material->pop_id);

        return response()->json([
            'message' => 'Barang berhasil diperbarui.',
            'data'    => $material,
        ]);
    }

    public function destroy(Request $request, Material $material): JsonResponse
    {
        $this->checkAccess();
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $material->pop_id !== $user->pop_id) {
            abort(404);
        }

        if ($material->isUsed()) {
            return response()->json([
                'message' => "Barang '{$material->name}' tidak dapat dihapus karena sedang digunakan dalam transaksi. Anda dapat menonaktifkannya.",
            ], 422);
        }

        $name = $material->name;
        $popId = $material->pop_id;
        $material->delete();

        ActivityLogger::log('material.delete', "Menghapus barang {$name}", null, $popId);

        return response()->json([
            'message' => "Barang '{$name}' berhasil dihapus.",
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

        $query = Material::query()->visibleTo($user)->with('pop:id,code,name');

        if ($user->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('materials.pop_id', (int) $request->input('pop_id'));
        }

        if ($request->filled('unit')) {
            $query->where('materials.unit', $request->input('unit'));
        }

        if ($request->filled('is_active')) {
            $query->where('materials.is_active', (bool) $request->input('is_active'));
        }

        if ($search !== '') {
            $query->where('materials.name', 'like', "%{$search}%");
        }

        $recordsTotal = Material::visibleTo($user)->count();
        $recordsFiltered = (clone $query)->count();

        $materials = $query->orderBy('name', 'asc')->offset($start)->limit($length)->get();

        $data = $materials->map(function (Material $item) {
            return [
                'id'        => $item->id,
                'name'      => $item->name,
                'unit'      => $item->unit,
                'pop_code'  => $item->pop?->code,
                'pop_name'  => $item->pop?->name,
                'is_active' => $item->is_active,
                'is_used'   => $item->isUsed(),
                'urls'      => [
                    'show'   => route('materials.show', $item),
                    'update' => route('materials.update', $item),
                    'delete' => route('materials.destroy', $item),
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

