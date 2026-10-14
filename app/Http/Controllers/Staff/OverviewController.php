<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function tasks(Request $request): View
    {
        return view('staff.overview.tasks', [
            'notifications' => $request->user()->notifications()->latest()->limit(20)->get(),
        ]);
    }

    public function calendar(Request $request): View
    {
        $visits = $request->user()->hasPermission('staff.buyers')
            ? SiteVisit::query()
                ->with(['property', 'buyer'])
                ->where(function ($query): void {
                    $query->where('requested_for', '>=', now()->startOfDay())
                        ->orWhere('confirmed_for', '>=', now()->startOfDay());
                })
                ->orderBy('requested_for')
                ->limit(30)
                ->get()
            : collect();

        return view('staff.overview.calendar', [
            'visits' => $visits,
        ]);
    }
}
