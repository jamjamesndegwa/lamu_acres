<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SystemOperations;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SystemOperationsController extends Controller
{
    public function index(Request $request, SystemOperations $operations): View
    {
        abort_unless($request->user()?->hasPermission('admin.settings'), 403);

        try {
            $report = $operations->snapshot();
        } catch (Throwable $exception) {
            report($exception);
            $report = $operations->unavailable();
        }

        return view('admin.operations.index', $report);
    }
}
