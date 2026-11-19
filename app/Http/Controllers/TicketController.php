<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Customer;
use App\Models\Pop;
use App\Models\Ticket;
use App\Models\TicketUpdate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\ActivityLogger;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        $this->authorizeAccess($user);

        if ($request->ajax() || $request->wantsJson()) {
            return $this->dataTable($request, $user);
        }

        // Statistik 30 hari terakhir
        $baseQuery = Ticket::visibleTo($user);

        $stats = [
            'open'           => (clone $baseQuery)->where('status', TicketStatus::OPEN)->count(),
            'in_progress'    => (clone $baseQuery)->whereIn('status', [
                TicketStatus::IN_PROGRESS,
                TicketStatus::WAITING_CUSTOMER,
                TicketStatus::FIELD_VISIT,
            ])->count(),
            'urgent'         => (clone $baseQuery)->where('priority', TicketPriority::URGENT)
                ->whereNotIn('status', [TicketStatus::RESOLVED, TicketStatus::CLOSED])
                ->count(),
            'avg_response'   => $this->formatDuration((clone $baseQuery)
                ->whereNotNull('first_response_at')
                ->where('opened_at', '>=', now()->subDays(30))
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, opened_at, first_response_at)) as avg_diff')
                ->value('avg_diff')),
            'avg_resolution' => $this->formatDuration((clone $baseQuery)
                ->whereNotNull('resolved_at')
                ->where('resolved_at', '>=', now()->subDays(30))
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, opened_at, resolved_at)) as avg_diff')
                ->value('avg_diff')),
        ];

        $pops = ($user->isSuperAdmin() || $user->isNoc())
            ? Pop::where('is_active', true)->orderBy('name')->get()
            : collect([$user->pop]);

        $nocUsers = User::whereIn('role', [Role::NOC, Role::SUPER_ADMIN])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $statuses = TicketStatus::cases();
        $categories = TicketCategory::cases();
        $priorities = TicketPriority::cases();
        $channels = TicketChannel::cases();

        return view('tickets.index', compact(
            'stats',
            'pops',
            'nocUsers',
            'statuses',
            'categories',
            'priorities',
            'channels'
        ));
    }

    private function dataTable(Request $request, User $user): JsonResponse
    {
        $query = Ticket::visibleTo($user)
            ->with(['customer:id,customer_code,name,phone', 'pop:id,code,name', 'assignee:id,name']);

        // Filter status
        if ($request->filled('status')) {
            $st = \App\Enums\TicketStatus::tryFrom($request->status);
            if ($st) {
                $query->where('status', $st->value);
            }
        }

        // Filter kategori
        if ($request->filled('category')) {
            $cat = \App\Enums\TicketCategory::tryFrom($request->category);
            if ($cat) {
                $query->where('category', $cat->value);
            }
        }

        // Filter prioritas
        if ($request->filled('priority')) {
            $pr = \App\Enums\TicketPriority::tryFrom($request->priority);
            if ($pr) {
                $query->where('priority', $pr->value);
            }
        }

        // Filter POP
        if ($request->filled('pop_id') && is_numeric($request->pop_id)) {
            $query->where('pop_id', (int) $request->pop_id);
        }

        // Filter Ditangani Oleh
        if ($request->filled('assigned_to') && is_numeric($request->assigned_to)) {
            $query->where('assigned_to', (int) $request->assigned_to);
        }

        // Pintasan Tiket Saya
        if ($request->boolean('my_tickets')) {
            $query->where('assigned_to', $user->id);
        }

        // Filter rentang tanggal (mendukung 'from'/'to' dan 'start_date'/'end_date')
        $startDate = $request->input('from') ?? $request->input('start_date');
        if ($startDate && is_string($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $query->whereDate('opened_at', '>=', $startDate);
        }
        $endDate = $request->input('to') ?? $request->input('end_date');
        if ($endDate && is_string($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $query->whereDate('opened_at', '<=', $endDate);
        }

        // Pencarian teks (mendukung 'q' dan 'search')
        $rawSearch = $request->input('q') ?? $request->input('search.value') ?? $request->input('search');
        if (is_array($rawSearch)) {
            $rawSearch = $rawSearch['value'] ?? null;
        }
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? trim(strip_tags($rawSearch)) : null;
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('reporter_name', 'like', "%{$search}%")
                    ->orWhere('reporter_phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_code', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $recordsTotal = Ticket::visibleTo($user)->count();
        $recordsFiltered = (clone $query)->count();

        // Ordering & Pagination
        $orderColumnIndex = $request->input('order.0.column', 1);
        $orderDir = $request->input('order.0.dir', 'desc');

        $columns = [
            0 => 'ticket_number',
            1 => 'opened_at',
            2 => 'reporter_name',
            3 => 'pop_id',
            4 => 'category',
            5 => 'priority',
            6 => 'status',
            7 => 'assigned_to',
        ];

        $column = $columns[$orderColumnIndex] ?? 'opened_at';
        $query->orderBy($column, $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $tickets = $query->skip($start)->take($length)->get();

        $data = $tickets->map(function (Ticket $ticket) use ($user) {
            $isEditable = ($user->isSuperAdmin() || $user->isNoc()) && $ticket->status !== TicketStatus::CLOSED;

            return [
                'id'            => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'opened_at'     => $ticket->opened_at?->format('d/m/Y H:i') ?? '-',
                'reporter'      => [
                    'name'          => $ticket->reporter_name,
                    'phone'         => $ticket->reporter_phone,
                    'customer_id'   => $ticket->customer_id,
                    'customer_code' => $ticket->customer?->customer_code,
                ],
                'pop_name'      => $ticket->pop?->name ?? '-',
                'category'      => [
                    'value' => $ticket->category->value,
                    'label' => $ticket->category->label(),
                    'badge' => $ticket->category->badgeColor(),
                ],
                'priority'      => [
                    'value' => $ticket->priority->value,
                    'label' => $ticket->priority->label(),
                    'badge' => $ticket->priority->badgeColor(),
                ],
                'status'        => [
                    'value' => $ticket->status->value,
                    'label' => $ticket->status->label(),
                    'badge' => $ticket->status->badgeColor(),
                ],
                'assignee'      => $ticket->assignee?->name ?? 'Belum Ditugaskan',
                'subject'       => $ticket->subject,
                'is_editable'   => $isEditable,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAccess($user);

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $customers = Customer::with('pop:id,code,name')
            ->where(function ($query) use ($q) {
                $query->where('customer_code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get();

        $data = $customers->map(function (Customer $customer) {
            $openTicketsCount = Ticket::where('customer_id', $customer->id)
                ->whereNotIn('status', [TicketStatus::RESOLVED->value, TicketStatus::CLOSED->value])
                ->count();

            return [
                'id'                 => $customer->id,
                'customer_code'      => $customer->customer_code,
                'name'               => $customer->name,
                'phone'              => $customer->phone ?? '',
                'pop_id'             => $customer->pop_id,
                'pop_name'           => $customer->pop?->name ?? '-',
                'service_status'     => $customer->service_status,
                'open_tickets_count' => $openTicketsCount,
            ];
        });

        return response()->json($data);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->createTicket($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Tiket ' . $ticket->ticket_number . ' berhasil dibuat.',
            'data'    => $ticket,
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $this->authorizeAccess($user);

        if (! $this->canViewTicket($user, $ticket)) {
            abort(403, 'Anda tidak memiliki akses ke tiket ini.');
        }

        $ticket->load([
            'customer:id,customer_code,name,phone,address,package,service_status',
            'pop:id,code,name',
            'creator:id,name',
            'assignee:id,name',
            'updates.user:id,name',
        ]);

        $allowedTransitions = collect($ticket->status->allowedTransitions())->map(fn (TicketStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
        ]);

        $canManage = ($user->isSuperAdmin() || $user->isNoc()) && $ticket->status !== TicketStatus::CLOSED;

        $canRequestFieldVisit = ($user->isSuperAdmin() || $user->isNoc())
            && (bool) $ticket->customer_id
            && in_array($ticket->category, [TicketCategory::CONNECTION, TicketCategory::OTHER], true)
            && $ticket->status === TicketStatus::IN_PROGRESS
            && in_array($ticket->customer?->service_status, ['ACTIVE', 'SUSPENDED'], true)
            && ! WorkOrder::where('ticket_id', $ticket->id)->whereNotIn('status', [WorkOrderStatus::VERIFIED, WorkOrderStatus::CANCELLED])->exists();

        return response()->json([
            'ticket' => [
                'id'                => $ticket->id,
                'ticket_number'     => $ticket->ticket_number,
                'customer_id'       => $ticket->customer_id,
                'customer'          => $ticket->customer,
                'pop_id'            => $ticket->pop_id,
                'pop_name'          => $ticket->pop?->name,
                'reporter_name'     => $ticket->reporter_name,
                'reporter_phone'    => $ticket->reporter_phone,
                'category'          => [
                    'value' => $ticket->category->value,
                    'label' => $ticket->category->label(),
                    'badge' => $ticket->category->badgeColor(),
                ],
                'channel'           => [
                    'value' => $ticket->channel->value,
                    'label' => $ticket->channel->label(),
                ],
                'priority'          => [
                    'value' => $ticket->priority->value,
                    'label' => $ticket->priority->label(),
                    'badge' => $ticket->priority->badgeColor(),
                ],
                'subject'           => $ticket->subject,
                'description'       => $ticket->description,
                'status'            => [
                    'value' => $ticket->status->value,
                    'label' => $ticket->status->label(),
                    'badge' => $ticket->status->badgeColor(),
                ],
                'creator_name'      => $ticket->creator?->name,
                'assignee_id'       => $ticket->assigned_to,
                'assignee_name'     => $ticket->assignee?->name ?? 'Belum Ditugaskan',
                'opened_at'         => $ticket->opened_at?->format('d/m/Y H:i'),
                'first_response_at' => $ticket->first_response_at?->format('d/m/Y H:i') ?? '-',
                'resolved_at'       => $ticket->resolved_at?->format('d/m/Y H:i') ?? '-',
                'closed_at'         => $ticket->closed_at?->format('d/m/Y H:i') ?? '-',
                'resolution_note'   => $ticket->resolution_note,
            ],
            'allowed_transitions'    => $allowedTransitions,
            'can_manage'             => $canManage,
            'can_request_field_visit'=> $canRequestFieldVisit,
            'updates'             => $ticket->updates->map(function ($u) {
                return [
                    'id'          => $u->id,
                    'user_name'   => $u->user?->name ?? 'Sistem',
                    'type'        => $u->type->value,
                    'type_label'  => $u->type->label(),
                    'type_badge'  => $u->type->badgeColor(),
                    'body'        => $u->body,
                    'from_status' => $u->from_status?->label(),
                    'to_status'   => $u->to_status?->label(),
                    'created_at'  => $u->created_at?->format('d/m/Y H:i'),
                ];
            }),
        ]);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Hanya NOC atau Super Admin yang dapat mengubah tiket.');
        }

        $this->ticketService->updateTicket($ticket, $request->validated(), $user);

        return response()->json([
            'success' => true,
            'message' => 'Data tiket berhasil diperbarui.',
            'data'    => $ticket->fresh(),
        ]);
    }

    public function changeStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Hanya NOC atau Super Admin yang dapat mengubah status tiket.');
        }

        $request->validate([
            'status'          => ['required', Rule::enum(TicketStatus::class)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->ticketService->changeStatus(
            $ticket,
            $request->input('status'),
            $request->input('resolution_note'),
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Status tiket berhasil diubah.',
            'data'    => $ticket->fresh(),
        ]);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Hanya NOC atau Super Admin yang dapat menugaskan tiket.');
        }

        $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->ticketService->assignTicket(
            $ticket,
            $request->input('assigned_to'),
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Penugasan tiket berhasil diperbarui.',
            'data'    => $ticket->fresh(),
        ]);
    }

    public function addNote(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Hanya NOC atau Super Admin yang dapat menambahkan catatan.');
        }

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->ticketService->addNote($ticket, $request->input('body'), $user);

        return response()->json([
            'success' => true,
            'message' => 'Catatan berhasil ditambahkan.',
        ]);
    }

    public function requestFieldVisit(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! ($user->isSuperAdmin() || $user->isNoc())) {
            abort(403, 'Hanya NOC atau Super Admin yang dapat meminta kunjungan teknisi.');
        }

        if (! $ticket->customer_id) {
            return response()->json([
                'message' => 'Kunjungan teknisi hanya dapat diajukan untuk tiket pelanggan terdaftar.',
            ], 422);
        }

        if (! in_array($ticket->category, [TicketCategory::CONNECTION, TicketCategory::OTHER], true)) {
            return response()->json([
                'message' => 'Kunjungan teknisi hanya diizinkan untuk tiket berkategori Gangguan Koneksi atau Lainnya.',
            ], 422);
        }

        // Satu tiket hanya boleh punya satu kunjungan aktif (cek lebih dulu agar pesan lebih akurat)
        $hasActiveWo = WorkOrder::where('ticket_id', $ticket->id)
            ->whereNotIn('status', [WorkOrderStatus::VERIFIED, WorkOrderStatus::CANCELLED])
            ->exists();

        if ($hasActiveWo) {
            return response()->json([
                'message' => 'Tiket ini sudah memiliki penugasan kunjungan teknisi yang aktif.',
            ], 422);
        }

        if (! in_array($ticket->status, [TicketStatus::IN_PROGRESS, TicketStatus::FIELD_VISIT], true)) {
            return response()->json([
                'message' => 'Tiket harus berstatus Sedang Diproses (IN_PROGRESS) untuk meminta kunjungan teknisi.',
            ], 422);
        }

        $customer = $ticket->customer;
        if (! $customer || ! in_array($customer->service_status, ['ACTIVE', 'SUSPENDED'], true)) {
            return response()->json([
                'message' => 'Pelanggan harus berstatus Layanan ACTIVE atau SUSPENDED untuk meminta kunjungan teknisi.',
            ], 422);
        }

        $request->validate([
            'admin_note' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'admin_note.required' => 'Ringkasan kendala atau catatan untuk teknisi wajib diisi.',
            'admin_note.min'      => 'Ringkasan kendala minimal 5 karakter.',
        ]);

        $workOrder = DB::transaction(function () use ($ticket, $customer, $request, $user) {
            $wo = WorkOrder::create([
                'type'               => WorkOrderType::REPAIR,
                'customer_id'        => $customer->id,
                'installation_key'   => null,
                'survey_id'          => null,
                'ticket_id'          => $ticket->id,
                'pop_id'             => $customer->pop_id,
                'technician_user_id' => null,
                'status'             => WorkOrderStatus::REQUESTED,
                'admin_note'         => $request->input('admin_note'),
                'assigned_by'        => null,
            ]);

            $ticket->update(['status' => TicketStatus::FIELD_VISIT]);

            TicketUpdate::create([
                'ticket_id'   => $ticket->id,
                'user_id'     => $user->id,
                'type'        => TicketUpdateType::STATUS_CHANGE,
                'from_status' => TicketStatus::IN_PROGRESS,
                'to_status'   => TicketStatus::FIELD_VISIT,
                'body'        => 'Permintaan kunjungan teknisi diajukan ke Admin POP: ' . $request->input('admin_note'),
                'created_at'  => now(),
            ]);

            ActivityLogger::log(
                'ticket.request_field_visit',
                "Mengajukan kunjungan teknisi untuk tiket {$ticket->ticket_number}",
                $wo,
                $customer->pop_id,
                $user->id
            );

            return $wo;
        });

        return response()->json([
            'success'    => true,
            'message'    => "Permintaan kunjungan teknisi berhasil diajukan untuk tiket {$ticket->ticket_number}. Status tiket menjadi Kunjungan Lapangan.",
            'work_order' => $workOrder,
        ]);
    }

    public function destroy(Ticket $ticket): void
    {
        abort(403, 'Tiket tidak boleh dihapus.');
    }

    private function authorizeAccess(User $user): void
    {
        if (! ($user->isSuperAdmin() || $user->isNoc() || $user->isAdmin())) {
            abort(403, 'Anda tidak memiliki hak akses ke halaman Tiket.');
        }
    }

    private function canViewTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isSuperAdmin() || $user->isNoc()) {
            return true;
        }

        if ($user->isAdmin() && (int) $user->pop_id === (int) $ticket->pop_id) {
            return true;
        }

        return false;
    }

    private function formatDuration(?float $minutes): string
    {
        if ($minutes === null || $minutes < 0) {
            return '-';
        }

        if ($minutes < 60) {
            return round($minutes) . ' mnt';
        }

        if ($minutes < 1440) {
            return round($minutes / 60, 1) . ' jam';
        }

        return round($minutes / 1440, 1) . ' hari';
    }
}

