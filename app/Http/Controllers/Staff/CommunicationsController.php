<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationsController extends Controller
{
    public function templates(): View
    {
        return view('staff.messages.templates', [
            'templates' => MessageTemplate::query()->orderBy('event')->get(),
        ]);
    }

    public function updateTemplate(Request $request, MessageTemplate $messageTemplate): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $messageTemplate->update($data);

        return back()->with('status', 'Template saved.');
    }

    public function deliveries(): View
    {
        return view('staff.messages.deliveries', [
            'deliveries' => MessageDelivery::query()->with('recipient')->latest()->paginate(30),
        ]);
    }
}
