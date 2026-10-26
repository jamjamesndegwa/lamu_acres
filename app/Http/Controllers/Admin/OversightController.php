<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ExecutiveReport;
use App\Support\Reporting\ReportingFilters;
use App\Support\Reporting\ReportingWindow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OversightController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = match ($request->route()?->getName()) {
            'admin.oversight.sales' => 'sales',
            'admin.oversight.inventory' => 'inventory',
            'admin.oversight.buyers' => 'buyers',
            'admin.oversight.brokers' => 'brokers',
            'admin.oversight.subscriptions' => 'subscriptions',
            default => abort(404),
        };

        $window = ReportingWindow::fromRequest($request);
        $filters = ReportingFilters::fromRequest($request);
        $report = ExecutiveReport::make($request->user(), $window, $filters);

        return view('admin.oversight.show', [
            'tab' => $tab,
            'window' => $window,
            'filters' => $filters,
            'section' => $report->section($tab),
            'tables' => $report->records($tab),
        ]);
    }
}
