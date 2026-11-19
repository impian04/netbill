<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use App\Enums\TicketStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Survey;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;

class MenuService
{
    /**
     * Dapatkan nama route landing page sesuai peran pengguna.
     */
    public static function getLandingRoute(Role|string $role): string
    {
        $roleKey = $role instanceof Role ? $role->value : $role;
        $routes = config('menu.landing_routes', []);

        return $routes[$roleKey] ?? 'portal';
    }

    /**
     * Dapatkan URL landing page sesuai peran pengguna.
     */
    public static function getLandingUrl(Role|string $role): string
    {
        return route(self::getLandingRoute($role));
    }

    /**
     * Cek apakah peran ini memiliki landing page mandiri selain /portal.
     */
    public static function shouldRedirectFromPortal(Role|string $role): bool
    {
        return self::getLandingRoute($role) !== 'portal';
    }

    /**
     * Hitung badge dinamis sesuai hak akses peran pengguna (query ringan).
     */
    public static function calculateBadges(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $badges = [
            'unassigned_surveys'      => 0,
            'pending_work_orders'     => 0,
            'survey_queue'            => 0,
            'technician_active_tasks' => 0,
            'open_tickets'            => 0,
        ];

        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isNoc()) {
            $badges['open_tickets'] = Ticket::visibleTo($user)
                ->where('status', TicketStatus::OPEN)
                ->count();
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            $badges['unassigned_surveys'] = Survey::visibleTo($user)
                ->where('status', SurveyStatus::SUBMITTED)
                ->where('result', SurveyResult::FEASIBLE)
                ->count();

            $badges['pending_work_orders'] = WorkOrder::visibleTo($user)
                ->whereIn('status', [WorkOrderStatus::SUBMITTED, WorkOrderStatus::REQUESTED])
                ->count();
        } elseif ($user->isSurveyor()) {
            $badges['survey_queue'] = Customer::where('pop_id', $user->pop_id)
                ->where('service_status', 'PENDING')
                ->whereDoesntHave('survey')
                ->count();
        } elseif ($user->isTechnician()) {
            $badges['technician_active_tasks'] = WorkOrder::where('technician_user_id', $user->id)
                ->whereIn('status', [
                    WorkOrderStatus::ASSIGNED,
                    WorkOrderStatus::IN_PROGRESS,
                    WorkOrderStatus::NEEDS_REVISION,
                ])
                ->count();
        }

        return $badges;
    }

    /**
     * Ambil struktur sections dan items menu untuk pengguna yang sedang aktif.
     */
    public static function getMenuSections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $roleKey = $user->role->value;
        $menuConfig = config("menu.items.{$roleKey}", []);
        $sections = [];

        if (isset($menuConfig['inherits'])) {
            $baseConfig = config("menu.items.{$menuConfig['inherits']}", []);
            $sections = $baseConfig;
            if (! empty($menuConfig['additional_sections'])) {
                $sections = array_merge($sections, $menuConfig['additional_sections']);
            }
        } else {
            $sections = $menuConfig;
        }

        $badges = self::calculateBadges($user);

        // Enrich items dengan URL, is_active, dan badge_count
        return array_map(function ($section) use ($badges) {
            $section['items'] = array_map(function ($item) use ($badges) {
                $item['url'] = route($item['route']);
                $item['is_active'] = request()->routeIs($item['active_pattern']);
                $badgeKey = $item['badge_key'] ?? null;
                $item['badge_count'] = $badgeKey ? ($badges[$badgeKey] ?? 0) : 0;
                return $item;
            }, $section['items'] ?? []);

            return $section;
        }, $sections);
    }

    /**
     * Dapatkan daftar nama route yang dimiliki oleh suatu peran (untuk keperluan testing).
     */
    public static function getAllRoutesForRole(Role|string $role): array
    {
        $roleKey = $role instanceof Role ? $role->value : $role;
        $menuConfig = config("menu.items.{$roleKey}", []);
        $sections = [];

        if (isset($menuConfig['inherits'])) {
            $baseConfig = config("menu.items.{$menuConfig['inherits']}", []);
            $sections = array_merge($baseConfig, $menuConfig['additional_sections'] ?? []);
        } else {
            $sections = $menuConfig;
        }

        $routes = [];
        foreach ($sections as $sec) {
            foreach ($sec['items'] ?? [] as $item) {
                if (! empty($item['route'])) {
                    $routes[] = $item['route'];
                }
            }
        }

        return array_unique($routes);
    }
}

