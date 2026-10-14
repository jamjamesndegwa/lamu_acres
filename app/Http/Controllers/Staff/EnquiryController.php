<?php

namespace App\Http\Controllers\Staff;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AssignEnquiryRequest;
use App\Http\Requests\Staff\StoreEnquiryResponseRequest;
use App\Http\Requests\Staff\UpdateEnquiryStatusRequest;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\Staff\EnquiryDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Enquiry::class);

        $enquiries = Enquiry::query()
            ->visibleToStaff($request->user())
            ->with(['buyer', 'property', 'assignee'])
            ->latest()
            ->paginate(20);

        return view('staff.enquiries.index', ['enquiries' => $enquiries]);
    }

    public function show(Enquiry $enquiry): View
    {
        $this->authorize('view', $enquiry);

        $enquiry->load(['buyer', 'property', 'assignee', 'brokerIntroduction', 'responses.author']);

        return view('staff.enquiries.show', [
            'enquiry' => $enquiry,
            'assignees' => User::permission('workspace.staff')->orderBy('name')->get(),
            'statuses' => EnquiryStatus::cases(),
            'leadStatuses' => LeadStatus::cases(),
        ]);
    }

    public function assign(AssignEnquiryRequest $request, Enquiry $enquiry, EnquiryDesk $desk): RedirectResponse
    {
        $desk->assign($enquiry, $request->assignee());

        return back()->with('status', 'Enquiry assigned.');
    }

    public function update(UpdateEnquiryStatusRequest $request, Enquiry $enquiry, EnquiryDesk $desk): RedirectResponse
    {
        $desk->updateStatus(
            $enquiry,
            $request->enum('status', EnquiryStatus::class),
            $request->enum('lead_status', LeadStatus::class),
        );

        return back()->with('status', 'Enquiry status updated.');
    }

    public function respond(StoreEnquiryResponseRequest $request, Enquiry $enquiry, EnquiryDesk $desk): RedirectResponse
    {
        $desk->respond(
            $enquiry,
            $request->user(),
            $request->string('body')->toString(),
            $request->boolean('visible_to_buyer'),
        );

        return back()->with('status', 'Response recorded.');
    }
}
