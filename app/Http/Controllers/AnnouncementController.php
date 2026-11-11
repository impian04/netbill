<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    private const SORT_MAP = [
        0 => 'title',
        1 => 'is_active',
        2 => 'published_at',
        3 => 'expires_at',
        4 => 'created_at',
    ];

    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        return view('announcements.index');
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json($announcement);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate($this->rules());
        $announcement = Announcement::create($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pengumuman berhasil ditambahkan.',
                'data'    => $announcement,
            ], 201);
        }

        return redirect()->route('announcements.index')
            ->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function update(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        $data = $request->validate($this->rules());
        $announcement->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pengumuman berhasil diperbarui.',
                'data'    => $announcement,
            ]);
        }

        return redirect()->route('announcements.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        $announcement->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Pengumuman berhasil dihapus.',
            ]);
        }

        return redirect()->route('announcements.index')
            ->with('success', 'Pengumuman dihapus.');
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $orderColIdx = (int) $request->input('order.0.column', 4);
        $orderDir    = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn  = self::SORT_MAP[$orderColIdx] ?? 'created_at';

        $recordsTotal = Cache::remember('dt_announcements_total_count', 60, fn () => Announcement::count());

        $query = Announcement::query();

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('is_active', (bool) $status);
        }

        $recordsFiltered = (clone $query)->count();

        $announcements = $query->orderBy($sortColumn, $orderDir)->offset($start)->limit($length)->get();

        $data = $announcements->map(function (Announcement $a) {
            return [
                'id'           => $a->id,
                'title'        => $a->title,
                'body'         => $a->body,
                'is_active'    => (bool) $a->is_active,
                'published_at' => $a->published_at?->format('Y-m-d H:i'),
                'expires_at'   => $a->expires_at?->format('Y-m-d H:i'),
                'created_at'   => $a->created_at?->format('Y-m-d H:i'),
                'urls'         => [
                    'show'   => route('announcements.show', $a),
                    'update' => route('announcements.update', $a),
                    'delete' => route('announcements.destroy', $a),
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

    private function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:120'],
            'body'         => ['required', 'string', 'max:1000'],
            'is_active'    => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at'   => ['nullable', 'date', 'after_or_equal:published_at'],
        ];
    }
}
