<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function edit(): View
    {
        return view('admin.plans.edit', [
            'plan' => SubscriptionPlan::investorPass(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'interval' => ['required', Rule::in(['year', 'month'])],
            'terms' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
            'checkout_amount' => ['nullable', 'integer', 'min:1'],
            'checkout_policy' => ['nullable', 'string', 'max:2000', 'required_with:checkout_amount'],
        ]);

        $payload = [
            'name' => $data['name'],
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'currency' => strtoupper($data['currency']),
            'interval' => $data['interval'],
            'terms' => $data['terms'],
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->exists('checkout_amount')) {
            $payload['checkout_amount'] = $request->filled('checkout_amount') ? (int) $data['checkout_amount'] : null;
            $payload['checkout_currency'] = $payload['checkout_amount'] ? 'KES' : null;
            $payload['checkout_policy'] = $request->filled('checkout_policy') ? $data['checkout_policy'] : null;
        }

        SubscriptionPlan::investorPass()->update($payload);

        return back()->with('status', 'Investor Pass pricing updated.');
    }
}
