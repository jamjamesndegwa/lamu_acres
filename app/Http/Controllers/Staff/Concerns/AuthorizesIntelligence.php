<?php

namespace App\Http\Controllers\Staff\Concerns;

use App\Enums\PublicationStatus;
use App\Models\Locality;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait AuthorizesIntelligence
{
    protected function researcher(Request $request): void
    {
        abort_unless($request->user()->hasPermission('staff.intelligence'), 403);
    }

    protected function editor(Request $request): void
    {
        abort_unless($request->user()->hasPermission('intelligence.publish'), 403);
    }

    protected function guardEdit(Request $request, Model $subject): void
    {
        if (in_array($subject->publication_status, [PublicationStatus::Draft, PublicationStatus::Withdrawn], true)) {
            $this->researcher($request);

            return;
        }

        $this->editor($request);
    }

    protected function localities(): Collection
    {
        return Locality::query()->with('county:id,name')->orderBy('name')->get();
    }
}
