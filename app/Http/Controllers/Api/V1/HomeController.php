<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InvoiceResource;
use App\Models\Announcement;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /** GET /api/v1/home */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        // Aggregate in ONE query
        $stats = Invoice::where('customer_id', $customer->id)
            ->where('status', 'UNPAID')
            ->selectRaw('COUNT(*) as unpaid_count, COALESCE(SUM(deferred_amount), 0) as total_unpaid_amount')
            ->first();

        // Latest UNPAID invoices (max 5)
        $latestUnpaid = Invoice::where('customer_id', $customer->id)
            ->where('status', 'UNPAID')
            ->orderByDesc('period')
            ->limit(5)
            ->get();

        // Active announcements (max 5)
        $announcements = Announcement::active()
            ->orderByDesc('published_at')
            ->limit(5)
            ->get(['id', 'title', 'body', 'published_at', 'expires_at']);

        return response()->json([
            'unpaid_count'        => (int) $stats->unpaid_count,
            'total_unpaid_amount' => (int) $stats->total_unpaid_amount,
            'latest_unpaid'       => InvoiceResource::collection($latestUnpaid),
            'announcements'       => $announcements->map(fn ($a) => [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'published_at' => $a->published_at?->toIso8601String(),
                'expires_at'   => $a->expires_at?->toIso8601String(),
            ]),
        ]);
    }
}
