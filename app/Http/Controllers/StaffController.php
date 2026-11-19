<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Pop;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ExternalUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax() || $request->expectsJson()) {
            return $this->dataTableResponse($request);
        }

        $pops = Pop::where('is_active', true)->orderBy('name')->get();
        $roles = Role::cases();

        return view('staff.index', compact('pops', 'roles'));
    }

    public function store(StoreStaffRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $role = Role::from($data['role']);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make(Str::random(32)),
            'phone'     => $data['phone'] ?? null,
            'role'      => $role,
            'pop_id'    => $role->requiresPop() ? (int) $data['pop_id'] : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $token = Password::broker()->createToken($user);
        $setUrl = ExternalUrl::route('password.set', ['token' => $token, 'email' => $user->email]);

        ActivityLogger::log('staff.create', "Membuat akun staf: {$user->name} ({$user->email}, {$role->label()})", $user, $user->pop_id);

        if ($request->expectsJson()) {
            return response()->json([
                'message'          => 'Akun staf berhasil dibuat.',
                'set_password_url' => $setUrl,
                'data'             => $user,
            ], 201);
        }

        return redirect()->route('staff.index')
            ->with('success', 'Akun staf berhasil dibuat.')
            ->with('set_password_url', $setUrl);
    }

    public function show(User $staff): JsonResponse
    {
        return response()->json([
            'id'        => $staff->id,
            'name'      => $staff->name,
            'email'     => $staff->email,
            'phone'     => $staff->phone,
            'role'      => $staff->role->value,
            'pop_id'    => $staff->pop_id,
            'is_active' => $staff->is_active,
            'is_self'   => $staff->id === auth()->id(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $role = isset($data['role']) ? Role::from($data['role']) : $staff->role;
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $staff->is_active;
        $popId = $role->requiresPop() ? (int) ($data['pop_id'] ?? $staff->pop_id) : null;

        $staff->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'phone'     => $data['phone'] ?? null,
            'role'      => $role,
            'pop_id'    => $popId,
            'is_active' => $isActive,
        ]);

        ActivityLogger::log('staff.update', "Memperbarui data staf: {$staff->name} ({$staff->email})", $staff, $staff->pop_id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Data staf berhasil diperbarui.',
                'data'    => $staff,
            ]);
        }

        return redirect()->route('staff.index')->with('success', 'Data staf berhasil diperbarui.');
    }

    public function resetAccess(Request $request, User $staff): JsonResponse|RedirectResponse
    {
        $token  = Password::broker()->createToken($staff);
        $setUrl = ExternalUrl::route('password.set', ['token' => $token, 'email' => $staff->email]);

        ActivityLogger::log('staff.reset_access', "Membuat tautan reset akses untuk: {$staff->name} ({$staff->email})", $staff, $staff->pop_id);

        if ($request->expectsJson()) {
            return response()->json([
                'message'          => 'Tautan set password berhasil dibuat.',
                'set_password_url' => $setUrl,
            ]);
        }

        return redirect()->route('staff.index')
            ->with('success', 'Tautan set password berhasil dibuat.')
            ->with('set_password_url', $setUrl);
    }

    private function dataTableResponse(Request $request): JsonResponse
    {
        $draw   = $request->integer('draw', 1);
        $start  = max(0, $request->integer('start', 0));
        $length = min(100, max(1, $request->integer('length', 15)));

        $query = User::with('pop:id,code,name');

        $rawSearch = $request->input('search');
        $search = is_array($rawSearch) ? trim((string) ($rawSearch['value'] ?? '')) : trim((string) $rawSearch);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($popId = $request->input('pop_id')) {
            $query->where('pop_id', $popId);
        }

        $recordsTotal = User::count();
        $recordsFiltered = (clone $query)->count();

        $users = $query->orderBy('name')->offset($start)->limit($length)->get();

        $data = $users->map(function (User $u) {
            return [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'phone'      => $u->phone ?? '-',
                'role'       => $u->role->value,
                'role_label' => $u->role->label(),
                'role_badge' => $u->role->badgeColor(),
                'pop_name'   => $u->pop ? "{$u->pop->code} - {$u->pop->name}" : 'Semua POP',
                'is_active'  => $u->is_active,
                'is_self'    => $u->id === auth()->id(),
                'urls'       => [
                    'show'         => route('staff.show', $u),
                    'update'       => route('staff.update', $u),
                    'reset_access' => route('staff.reset-access', $u),
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

