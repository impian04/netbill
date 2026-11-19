<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\MarketingProfile;
use App\Models\Pop;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingProfileController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $currentUser = auth()->user();

        if (($request->ajax() || $request->expectsJson()) && ! $request->has('_plain')) {
            return $this->dataTableResponse($request, $currentUser);
        }

        $pops = $currentUser->isSuperAdmin() ? Pop::orderBy('name')->get() : collect([$currentUser->pop])->filter();

        return view('marketing_profiles.index', compact('pops'));
    }

    public function show(User $user): JsonResponse
    {
        $currentUser = auth()->user();

        if ($user->role !== Role::MARKETING) {
            abort(404);
        }

        if (! $currentUser->isSuperAdmin() && (int) $user->pop_id !== (int) $currentUser->pop_id) {
            abort(404);
        }

        $profile = $user->marketingProfile;

        return response()->json([
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'pop_code' => $user->pop?->code,
                'pop_name' => $user->pop?->name,
            ],
            'profile' => [
                'has_profile'           => (bool) $profile,
                'payout_method'         => $profile?->payout_method ?? 'BANK',
                'provider_name'         => $profile?->provider_name ?? '',
                'masked_account_number' => $profile?->maskedAccountNumber() ?? '',
                'account_holder'        => $profile?->account_holder ?? '',
                'notes'                 => $profile?->notes ?? '',
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if ($user->role !== Role::MARKETING) {
            abort(404);
        }

        if (! $currentUser->isSuperAdmin() && (int) $user->pop_id !== (int) $currentUser->pop_id) {
            abort(404);
        }

        $profile = $user->marketingProfile;

        $validated = $request->validate([
            'payout_method'  => ['required', 'string', 'in:BANK,EWALLET,LAINNYA'],
            'provider_name'  => ['required', 'string', 'max:100'],
            'account_number' => [$profile ? 'nullable' : 'required', 'string', 'max:50'],
            'account_holder' => ['required', 'string', 'max:255'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ], [
            'payout_method.required'  => 'Metode pembayaran wajib dipilih.',
            'provider_name.required'  => 'Nama bank/penyedia e-wallet wajib diisi.',
            'account_number.required' => 'Nomor rekening/e-wallet wajib diisi.',
            'account_holder.required' => 'Nama pemilik rekening/e-wallet wajib diisi.',
        ]);

        $payload = [
            'payout_method'  => $validated['payout_method'],
            'provider_name'  => $validated['provider_name'],
            'account_holder' => $validated['account_holder'],
            'notes'          => $validated['notes'] ?? null,
        ];

        // Only update account_number if provided (not empty)
        if (! empty($validated['account_number'])) {
            $payload['account_number'] = trim($validated['account_number']);
        }

        MarketingProfile::updateOrCreate(
            ['user_id' => $user->id],
            $payload
        );

        // Security: Log strictly without account number
        ActivityLogger::log(
            'marketing_profile.update',
            "Memperbarui data rekening pembayaran marketing {$user->name} ({$validated['provider_name']})",
            $user,
            $user->pop_id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data pembayaran marketing berhasil disimpan.',
            ]);
        }

        return redirect()->route('marketing-profiles.index')
            ->with('success', 'Data pembayaran marketing berhasil disimpan.');
    }

    private function dataTableResponse(Request $request, User $currentUser): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = User::where('role', Role::MARKETING)->with(['pop', 'marketingProfile']);

        if (! $currentUser->isSuperAdmin()) {
            $query->where('pop_id', $currentUser->pop_id);
        } elseif ($request->filled('pop_id')) {
            $query->where('pop_id', $request->input('pop_id'));
        }

        $recordsTotal = (clone $query)->count();

        // Search
        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        $users = $query->orderBy('name')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $users->map(function (User $u) {
            $profile = $u->marketingProfile;
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'phone'          => $u->phone,
                'pop_code'       => $u->pop?->code,
                'is_active'      => $u->is_active,
                'payout_method'  => $profile?->payout_method ?? '-',
                'provider_name'  => $profile?->provider_name ?? '-',
                'masked_account' => $profile ? $profile->maskedAccountNumber() : '<span class="text-muted small">Belum diisi</span>',
                'account_holder' => $profile?->account_holder ?? '-',
                'has_profile'    => (bool) $profile,
                'urls'           => [
                    'show'   => route('marketing-profiles.show', $u),
                    'update' => route('marketing-profiles.update', $u),
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

