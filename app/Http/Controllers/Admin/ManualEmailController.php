<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MessageDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Communications\MailPreflight;
use App\Services\Communications\MessageDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualEmailController extends Controller
{
    public function create(Request $request): View
    {
        $selectedUser = null;
        $userId = $request->integer('user_id');

        if ($userId > 0) {
            $selectedUser = User::query()->find($userId);
        }

        $search = trim((string) $request->query('q', ''));
        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'email']);

        return view('admin.communications.manual-email', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'search' => $search,
            'mailStatus' => app(MailPreflight::class)->inspect(),
        ]);
    }

    public function store(Request $request, MessageDesk $desk): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'destination_email' => ['nullable', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = User::query()->findOrFail((int) $data['user_id']);

        $delivery = $desk->sendStaffManualEmail(
            $recipient,
            $data['subject'],
            $data['body'],
            filled($data['destination_email'] ?? null) ? $data['destination_email'] : null,
        );

        if ($delivery->status === MessageDeliveryStatus::Failed) {
            return back()
                ->withInput()
                ->withErrors(['subject' => $delivery->error ?? 'The message could not be sent.']);
        }

        return redirect()
            ->route('admin.communications.external.show', $delivery)
            ->with('status', 'Email sent to '.$delivery->destination.'.');
    }
}
