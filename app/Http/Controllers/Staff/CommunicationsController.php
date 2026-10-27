<?php

namespace App\Http\Controllers\Staff;

use App\Enums\MessageChannel;
use App\Http\Controllers\Controller;
use App\Models\MessageCampaign;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Services\Communications\BulkSmsPreflight;
use App\Services\Communications\CampaignDesk;
use App\Services\Communications\MailPreflight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public function deliveries(BulkSmsPreflight $smsPreflight, MailPreflight $mailPreflight): View
    {
        $queue = config('queue.default');
        $pendingJobs = $queue === 'database' && $this->tableExists('jobs')
            ? (int) DB::table('jobs')->count()
            : null;
        $failedJobs = $queue === 'database' && $this->tableExists('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : null;

        return view('staff.messages.deliveries', [
            'deliveries' => MessageDelivery::query()->with(['recipient', 'campaign'])->latest()->paginate(30),
            'uncertainCount' => MessageDelivery::query()->where('status', 'uncertain')->count(),
            'smsConfigured' => $smsPreflight->configured(),
            'mailStatus' => $mailPreflight->inspect(),
            'pendingJobs' => $pendingJobs,
            'failedJobs' => $failedJobs,
        ]);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    public function campaigns(): View
    {
        return view('staff.messages.campaigns', [
            'campaigns' => MessageCampaign::query()->with('author')->latest()->paginate(20),
        ]);
    }

    public function createCampaign(): View
    {
        return view('staff.messages.campaigns-create');
    }

    public function storeCampaign(Request $request, CampaignDesk $campaigns): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', Rule::in([MessageChannel::Sms->value, MessageChannel::Email->value])],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $channel = MessageChannel::from($data['channel']);

        $campaign = $campaigns->create(
            $request->user(),
            $channel,
            $data['subject'],
            $data['body'],
        );

        return redirect()
            ->route('staff.messages.campaigns.show', $campaign)
            ->with('status', 'Campaign draft saved.');
    }

    public function showCampaign(MessageCampaign $messageCampaign): View
    {
        return view('staff.messages.campaign-show', [
            'campaign' => $messageCampaign->load(['author', 'deliveries' => fn ($query) => $query->latest()->limit(20)]),
        ]);
    }

    public function queueCampaign(MessageCampaign $messageCampaign, CampaignDesk $campaigns): RedirectResponse
    {
        $campaigns->queue($messageCampaign);

        return redirect()
            ->route('staff.messages.campaigns.show', $messageCampaign)
            ->with('status', 'Campaign queued for delivery.');
    }
}
