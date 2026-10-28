<?php

namespace App\Http\Controllers\Staff\Concerns;

use App\Services\Intelligence\PublicationDesk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait WithdrawsIntelligencePublication
{
    protected function withdrawPublication(Request $request, Model $subject, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $desk->withdraw($subject, $request->user());

        return back()->with('status', 'Withdrawn. Public preview and premium access are closed until this is revised and published again.');
    }
}
