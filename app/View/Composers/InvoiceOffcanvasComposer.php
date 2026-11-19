<?php

namespace App\View\Composers;

use App\Models\Customer;
use Illuminate\View\View;

class InvoiceOffcanvasComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        if (! isset($data['customers'])) {
            $user = auth()->user();
            $query = Customer::query();
            if ($user) {
                $query->visibleTo($user);
            }
            $view->with('customers', $query->orderBy('name')->get(['id', 'customer_code', 'name']));
        }
    }
}

