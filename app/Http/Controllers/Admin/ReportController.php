<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ExecutiveReport;
use App\Support\Reporting\ReportingFilters;
use App\Support\Reporting\ReportingWindow;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = match ($request->route()?->getName()) {
            'admin.reports.sales' => 'sales',
            'admin.reports.commissions' => 'commissions',
            'admin.reports.financial' => 'financial',
            'admin.reports.audit' => 'audit',
            default => abort(404),
        };

        $window = ReportingWindow::fromRequest($request);
        $filters = ReportingFilters::fromRequest($request);
        $report = ExecutiveReport::make($request->user(), $window, $filters);

        return view('admin.reports.show', [
            'tab' => $tab,
            'window' => $window,
            'filters' => $filters,
            'section' => $report->reportSection($tab),
            'tables' => $report->reportTables($tab),
            'intro' => match ($tab) {
                'sales' => 'Open and completed sales from the sales records. Agreed prices are the amounts already stored on each sale.',
                'commissions' => 'Commission agreements and payments already on file. This report does not calculate a commission.',
                'financial' => 'Agency fee agreements, collections, and broker settlements from recorded receipts and payments. Initiated receipts and payouts are excluded from collected and paid totals.',
                'audit' => 'Events written when a sale, property, referral, subscription, or research record changed.',
            },
        ]);
    }
}
