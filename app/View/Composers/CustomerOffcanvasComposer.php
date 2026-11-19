<?php

namespace App\View\Composers;

use App\Models\Customer;
use App\Models\Odc;
use App\Models\Odp;
use App\Models\Pop;
use Illuminate\View\View;

class CustomerOffcanvasComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();
        $user = auth()->user();

        if (! isset($data['pops'])) {
            $view->with('pops', Pop::where('is_active', true)->orderBy('name')->get());
        }

        if (! isset($data['packages'])) {
            $view->with(
                'packages',
                Customer::select('package')
                    ->whereNotNull('package')
                    ->where('package', '!=', '')
                    ->distinct()
                    ->orderBy('package')
                    ->pluck('package')
            );
        }

        if (! isset($data['billingTypes'])) {
            $view->with(
                'billingTypes',
                Customer::select('billing_type')
                    ->whereNotNull('billing_type')
                    ->where('billing_type', '!=', '')
                    ->distinct()
                    ->orderBy('billing_type')
                    ->pluck('billing_type')
            );
        }

        if (! isset($data['odcs'])) {
            $query = Odc::active()->orderBy('name');
            if ($user && ! $user->isSuperAdmin() && $user->pop_id) {
                $query->where('pop_id', $user->pop_id);
            }
            $view->with('odcs', $query->get(['id', 'pop_id', 'name']));
        }

        if (! isset($data['odps'])) {
            $query = Odp::active()->orderBy('name');
            if ($user && ! $user->isSuperAdmin() && $user->pop_id) {
                $query->where('pop_id', $user->pop_id);
            }
            $view->with('odps', $query->get(['id', 'pop_id', 'odc_id', 'name']));
        }
    }
}

