<?php

namespace App\Http\Controllers;

use App\Models\MarketingFee;
use App\Models\Pop;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingFeeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $currentUser = auth()->user();

        if (($request->ajax() || $request->expectsJson()) && ! $request->has('_plain')) {
            return $this->dataTableResponse($request, $currentUser);
        }

        $marketingUsers = User::where('role', \App\Enums\Role::MARKETING)
            ->when(! $currentUser->isSuperAdmin(), fn ($q) => $q->where('pop_id', $currentUser->pop_id))
            ->orderBy('name')
            ->get();

        $pops = $currentUser->isSuperAdmin() ? Pop::orderBy('name')->get() : collect([$currentUser->pop])->filter();

        // Distinct months from eligible_at for filter dropdown
        $months = MarketingFee::visibleTo($currentUser)
            ->selectRaw("DATE_FORMAT(eligible_at, '%Y-%m') as month")
            ->distinct()
            ->orderByDesc('month')
            ->pluck('month');

        return view('marketing_fees.index', [
            'marketingUsers' => $marketingUsers,
            'pops'           => $pops,
            'months'         => $months,
        ]);
    }

    public function updateAmount(Request $request, MarketingFee $fee): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        if (! $currentUser->isSuperAdmin() && (int) $fee->pop_id !== (int) $currentUser->pop_id) {
            abort(404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ], [
            'amount.required' => 'Nominal fee wajib diisi.',
            'amount.numeric'  => 'Nominal fee harus berupa angka.',
            'amount.min'      => 'Nominal fee tidak boleh negatif.',
        ]);

        $fee->amount = (int) $validated['amount'];
        $fee->save();

        ActivityLogger::log(
            'marketing_fee.update_amount',
            "Mengubah nominal fee pelanggan {$fee->customer?->customer_code} menjadi Rp " . number_format($fee->amount, 0, ',', '.'),
            $fee,
            $fee->pop_id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Nominal fee berhasil diperbarui.',
            ]);
        }

        return redirect()->route('marketing-fees.index')->with('success', 'Nominal fee berhasil diperbarui.');
    }

    public function bulkPay(Request $request): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        $validated = $request->validate([
            'fee_ids'   => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer'],
            'paid_at'   => ['required', 'date'],
            'paid_note' => ['nullable', 'string', 'max:1000'],
        ], [
            'fee_ids.required' => 'Pilih minimal satu baris fee untuk ditandai dibayar.',
            'paid_at.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

        $fees = MarketingFee::visibleTo($currentUser)
            ->whereIn('id', $validated['fee_ids'])
            ->where('status', 'PENDING')
            ->get();

        if ($fees->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Tidak ada fee berstatus PENDING yang dipilih.'], 422);
            }
            return back()->with('error', 'Tidak ada fee berstatus PENDING yang dipilih.');
        }

        $paidAt = Carbon::parse($validated['paid_at']);
        $paidNote = $validated['paid_note'] ?? null;
        $totalPaidAmount = 0;

        foreach ($fees as $fee) {
            $fee->status = 'PAID';
            $fee->paid_at = $paidAt;
            $fee->paid_note = $paidNote;
            $fee->save();
            $totalPaidAmount += (int) $fee->amount;
        }

        $count = $fees->count();
        $formattedTotal = number_format($totalPaidAmount, 0, ',', '.');

        ActivityLogger::log(
            'marketing_fee.bulk_pay',
            "Menandai dibayar {$count} fee marketing (Total: Rp {$formattedTotal})",
            null,
            $currentUser->pop_id
        );

        $msg = "{$count} fee marketing berhasil ditandai dibayar.";

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('marketing-fees.index')->with('success', $msg);
    }

    public function unpay(Request $request, MarketingFee $fee): JsonResponse|RedirectResponse
    {
        $currentUser = auth()->user();

        // Only super_admin can revert paid status
        if (! $currentUser->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang berhak membatalkan tanda pembayaran fee.');
        }

        $fee->status = 'PENDING';
        $fee->paid_at = null;
        $fee->paid_note = null;
        $fee->save();

        ActivityLogger::log(
            'marketing_fee.unpay',
            "Membatalkan tanda pembayaran fee pelanggan {$fee->customer?->customer_code}",
            $fee,
            $fee->pop_id
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Tanda pembayaran fee berhasil dibatalkan.']);
        }

        return redirect()->route('marketing-fees.index')->with('success', 'Tanda pembayaran fee berhasil dibatalkan.');
    }

    private function dataTableResponse(Request $request, User $currentUser): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = MarketingFee::visibleTo($currentUser)->with(['pop', 'marketingUser', 'customer']);

        // Filters
        if ($request->filled('month')) {
            $query->whereRaw("DATE_FORMAT(eligible_at, '%Y-%m') = ?", [$request->input('month')]);
        }
        if ($request->filled('marketing_user_id')) {
            $query->where('marketing_user_id', $request->input('marketing_user_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($currentUser->isSuperAdmin() && $request->filled('pop_id')) {
            $query->where('pop_id', $request->input('pop_id'));
        }

        // Search
        $rawSearch = $request->input('q') ?? $request->input('search');
        if (is_array($rawSearch)) {
            $rawSearch = ($rawSearch['value'] ?? '') !== '' ? $rawSearch['value'] : ($request->input('q') ?? '');
        }
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? trim(strip_tags($rawSearch)) : '';
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%")
                       ->orWhere('customer_code', 'like', "%{$search}%");
                })->orWhereHas('marketingUser', function ($mq) use ($search) {
                    $mq->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Calculate summary for active filter
        $summaryQuery = clone $query;
        $allMatching = $summaryQuery->get(['status', 'amount']);

        $pendingFees = $allMatching->where('status', 'PENDING');
        $paidFees = $allMatching->where('status', 'PAID');

        $summary = [
            'pending_count'  => $pendingFees->count(),
            'pending_amount' => (int) $pendingFees->sum('amount'),
            'paid_count'     => $paidFees->count(),
            'paid_amount'    => (int) $paidFees->sum('amount'),
            'total_count'    => $allMatching->count(),
            'total_amount'   => (int) $allMatching->sum('amount'),
        ];

        $recordsTotal = (clone $query)->count();
        $recordsFiltered = $recordsTotal;

        $fees = $query->orderByDesc('eligible_at')
            ->skip($start)
            ->take($length)
            ->get();

        $data = $fees->map(function (MarketingFee $f) use ($currentUser) {
            return [
                'id'             => $f->id,
                'eligible_at'    => $f->eligible_at->format('d/m/Y'),
                'pop_code'       => $f->pop?->code,
                'marketing_name' => $f->marketingUser?->name,
                'customer_code'  => $f->customer?->customer_code,
                'customer_name'  => $f->customer?->name,
                'amount'         => $f->amount !== null ? (int) $f->amount : null,
                'amount_format'  => $f->amount !== null ? 'Rp ' . number_format($f->amount, 0, ',', '.') : '<span class="text-muted small">Belum diisi</span>',
                'status'         => $f->status,
                'paid_at'        => $f->paid_at?->format('d/m/Y'),
                'paid_note'      => $f->paid_note,
                'is_paid'        => $f->status === 'PAID',
                'can_unpay'      => $currentUser->isSuperAdmin() && $f->status === 'PAID',
                'urls'           => [
                    'update_amount' => route('marketing-fees.update-amount', $f),
                    'unpay'         => route('marketing-fees.unpay', $f),
                ],
            ];
        });

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
            'summary'         => $summary,
        ]);
    }
}

