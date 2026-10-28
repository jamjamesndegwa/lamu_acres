<?php

namespace App\Http\Controllers\Communications;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Http\Controllers\Controller;
use App\Models\MessageDelivery;
use App\Services\Communications\BulkSmsPreflight;
use App\Services\Communications\ExternalCommunicationsRegister;
use App\Services\Communications\MailPreflight;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExternalCommunicationsController extends Controller
{
    public function __construct(private ExternalCommunicationsRegister $register) {}

    public function index(Request $request): View
    {
        $viewer = $request->user();

        if ($viewer === null || ! $this->register->canAccessRegister($viewer)) {
            throw new AccessDeniedHttpException;
        }

        $deliveries = $this->register
            ->query($viewer, $request)
            ->paginate(25)
            ->withQueryString();

        return view('communications.external.index', [
            'deliveries' => $deliveries,
            'counts' => $this->register->summaryCounts($viewer, $request),
            'channels' => $this->register->permittedChannels($viewer),
            'events' => MessageEvent::cases(),
            'stalledQueueWarning' => $this->register->stalledQueueWarning(),
            'smsConfigured' => app(BulkSmsPreflight::class)->configured(),
            'mailStatus' => app(MailPreflight::class)->inspect(),
            'filters' => $request->only(['channel', 'status', 'event', 'kind', 'from', 'to', 'user_id', 'q']),
            'register' => $this->register,
            'indexRoute' => $request->routeIs('admin.*') ? 'admin.communications.external.index' : 'staff.messages.deliveries',
            'showRoute' => $request->routeIs('admin.*') ? 'admin.communications.external.show' : 'staff.messages.deliveries.show',
        ]);
    }

    public function show(Request $request, MessageDelivery $messageDelivery): View
    {
        $viewer = $request->user();

        if ($viewer === null || ! $this->register->canViewDelivery($viewer, $messageDelivery)) {
            throw new NotFoundHttpException;
        }

        if (! in_array($messageDelivery->channel, [MessageChannel::Email, MessageChannel::Sms], true)) {
            throw new NotFoundHttpException;
        }

        $messageDelivery->loadMissing(['recipient', 'campaign']);

        return view('communications.external.show', [
            'delivery' => $messageDelivery,
            'register' => $this->register,
            'stalledQueueWarning' => $messageDelivery->status === 'queued' ? $this->register->stalledQueueWarning() : null,
            'indexRoute' => $request->routeIs('admin.*') ? 'admin.communications.external.index' : 'staff.messages.deliveries',
        ]);
    }
}
