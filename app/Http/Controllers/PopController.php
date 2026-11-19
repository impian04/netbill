<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePopRequest;
use App\Http\Requests\UpdatePopRequest;
use App\Models\Pop;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PopController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        return view('pops.index');
    }

    public function store(StorePopRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $pop = Pop::create($data);

        ActivityLogger::log('pop.create', "Membuat POP baru: {$pop->code} - {$pop->name}", $pop, $pop->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'POP berhasil ditambahkan.',
                'data'    => $pop,
            ], 201);
        }

        return redirect()->route('pops.index')->with('success', 'POP berhasil ditambahkan.');
    }

    public function show(Pop $pop): JsonResponse
    {
        return response()->json([
            'id'            => $pop->id,
            'code'          => $pop->code,
            'name'          => $pop->name,
            'va_code'       => $pop->va_code,
            'is_active'     => $pop->is_active,
            'has_customers' => $pop->customers()->exists(),
        ]);
    }

    public function update(UpdatePopRequest $request, Pop $pop): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $pop->update($data);

        ActivityLogger::log('pop.update', "Memperbarui data POP: {$pop->code} - {$pop->name}", $pop, $pop->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'POP berhasil diperbarui.',
                'data'    => $pop,
            ]);
        }

        return redirect()->route('pops.index')->with('success', 'POP berhasil diperbarui.');
    }

    public function destroy(Request $request, Pop $pop): JsonResponse|RedirectResponse
    {
        if ($pop->customers()->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'POP tidak dapat dihapus karena masih memiliki data pelanggan.'], 422);
            }
            return back()->with('error', 'POP tidak dapat dihapus karena masih memiliki data pelanggan.');
        }

        $code = $pop->code;
        $name = $pop->name;
        $pop->delete();

        ActivityLogger::log('pop.delete', "Menghapus POP {$code} - {$name}");

        if ($request->expectsJson()) {
            return response()->json(['message' => "POP {$code} berhasil dihapus."]);
        }

        return redirect()->route('pops.index')->with('success', "POP {$code} berhasil dihapus.");
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = Pop::withCount('customers');

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('va_code', 'like', "%{$search}%");
            });
        }

        $recordsTotal = Pop::count();
        $recordsFiltered = (clone $query)->count();

        $pops = $query->orderBy('name')->offset($start)->limit($length)->get();

        $data = $pops->map(function (Pop $p) {
            return [
                'id'              => $p->id,
                'code'            => $p->code,
                'name'            => $p->name,
                'va_code'         => $p->va_code ?? '-',
                'customers_count' => $p->customers_count,
                'is_active'       => $p->is_active,
                'urls'            => [
                    'show'   => route('pops.show', $p),
                    'update' => route('pops.update', $p),
                    'delete' => route('pops.destroy', $p),
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

