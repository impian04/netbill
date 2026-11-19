<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Pop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        $users = User::orderBy('name')->get(['id', 'name', 'role']);
        $pops  = Pop::orderBy('name')->get(['id', 'code', 'name']);
        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('activity_logs.index', compact('users', 'pops', 'actions'));
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 25)));

        $query = ActivityLog::with(['user:id,name,role', 'pop:id,code,name']);

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($popId = $request->input('pop_id')) {
            $query->where('pop_id', $popId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($startDate = $request->input('start_date')) {
            try {
                $query->where('created_at', '>=', Carbon::parse($startDate)->startOfDay());
            } catch (\Throwable) {}
        }

        if ($endDate = $request->input('end_date')) {
            try {
                $query->where('created_at', '<=', Carbon::parse($endDate)->endOfDay());
            } catch (\Throwable) {}
        }

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        $recordsTotal = ActivityLog::count();
        $recordsFiltered = (clone $query)->count();

        $logs = $query->orderByDesc('created_at')->offset($start)->limit($length)->get();

        $data = $logs->map(function (ActivityLog $log) {
            return [
                'id'          => $log->id,
                'created_at'  => $log->created_at?->format('d/m/Y H:i:s'),
                'user_name'   => $log->user ? $log->user->name : 'Sistem / Anonim',
                'user_role'   => $log->user ? $log->user->role->label() : '-',
                'pop_name'    => $log->pop ? $log->pop->code : 'Semua / -',
                'action'      => $log->action,
                'description' => $log->description ?? '-',
                'ip'          => $log->ip ?? '-',
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

