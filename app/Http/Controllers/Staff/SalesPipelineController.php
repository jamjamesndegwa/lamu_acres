<?php

namespace App\Http\Controllers\Staff;

use App\Enums\SalesOrigin;
use App\Enums\SalesOutcome;
use App\Enums\SalesStage;
use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\SalesPipeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesPipelineController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeDesk($request);

        return view('staff.sales.index', [
            'transactions' => $this->query($request)->latest()->paginate(20),
            'heading' => 'Sales pipeline',
        ]);
    }

    public function completion(Request $request): View
    {
        $this->authorizeDesk($request);

        return view('staff.sales.index', [
            'transactions' => $this->query($request)->where('stage', SalesStage::CompletionPending)->latest()->paginate(20),
            'heading' => 'Completion queue',
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeDesk($request);

        return view('staff.sales.create', [
            'origins' => SalesOrigin::cases(),
            'agents' => User::permission('staff.sales')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SalesPipeline $pipeline): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'origin' => ['required', Rule::enum(SalesOrigin::class)],
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')],
            'buyer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'enquiry_id' => ['nullable', 'integer', Rule::exists('enquiries', 'id')],
            'sourcing_request_id' => ['nullable', 'integer', Rule::exists('sourcing_requests', 'id')],
            'broker_introduction_id' => ['nullable', 'integer', Rule::exists('broker_introductions', 'id')],
            'sale_instruction_id' => ['nullable', 'integer', Rule::exists('sale_instructions', 'id')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'acreage' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $transaction = $pipeline->open($request->user(), $data);

        return redirect()->route('staff.sales.show', $transaction)->with('status', 'Transaction '.$transaction->reference.' opened.');
    }

    public function show(Request $request, SalesTransaction $salesTransaction): View
    {
        $this->authorizeDesk($request);
        $salesTransaction->load([
            'property', 'buyer', 'agent', 'offers', 'milestones', 'documents', 'timeline.actor', 'closingReviews.reviewer', 'evidence', 'agreements.broker', 'audits',
        ]);

        return view('staff.sales.show', [
            'transaction' => $salesTransaction,
            'stages' => $salesTransaction->stage->transitions(),
            'outcomes' => SalesOutcome::cases(),
            'agents' => User::permission('staff.sales')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, SalesTransaction $salesTransaction, SalesPipeline $pipeline): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'stage' => ['nullable', Rule::enum(SalesStage::class)],
            'outcome' => ['nullable', Rule::enum(SalesOutcome::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'agreed_price' => ['nullable', 'integer', 'min:0'],
        ]);

        $version = (int) $data['lock_version'];

        if (filled($data['assigned_to'] ?? null)) {
            $salesTransaction = $pipeline->assign($salesTransaction, User::query()->findOrFail($data['assigned_to']), $request->user(), $version);
            $version = $salesTransaction->lock_version;
        }

        if (filled($data['agreed_price'] ?? null)) {
            $salesTransaction = $pipeline->agreePrice($salesTransaction, (int) $data['agreed_price'], $request->user(), $version);
            $version = $salesTransaction->lock_version;
        }

        if (filled($data['outcome'] ?? null)) {
            $pipeline->outcome($salesTransaction, SalesOutcome::from($data['outcome']), $request->user(), $version, (string) ($data['reason'] ?? ''));
        } elseif (filled($data['stage'] ?? null)) {
            $pipeline->advance($salesTransaction, SalesStage::from($data['stage']), $request->user(), $version);
        }

        return back()->with('status', 'Transaction updated.');
    }

    public function offer(Request $request, SalesTransaction $salesTransaction, SalesPipeline $pipeline): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
            'seller_response' => ['nullable', Rule::in(['accept', 'decline', 'counter'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $pipeline->recordOffer($salesTransaction, $request->user(), (int) $data['lock_version'], $data);

        return back()->with('status', 'Offer recorded. The sale is not completed.');
    }

    public function milestone(Request $request, SalesTransaction $salesTransaction, SalesPipeline $pipeline): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $pipeline->milestone($salesTransaction, $request->user(), $data['name'], $data['due_on'] ?? null, $data['notes'] ?? null);

        return back()->with('status', 'Milestone added.');
    }

    public function document(Request $request, SalesTransaction $salesTransaction, SalesPipeline $pipeline): RedirectResponse
    {
        $this->authorizeDesk($request);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:160'],
            'reference' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $pipeline->document($salesTransaction, $request->user(), $data['label'], $data['reference'], $data['note'] ?? null);

        return back()->with('status', 'Document reference added.');
    }

    private function query(Request $request)
    {
        return SalesTransaction::query()->with(['property:id,title,slug', 'buyer:id,name', 'agent:id,name']);
    }

    private function authorizeDesk(Request $request): void
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);
    }
}
