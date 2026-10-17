<x-workspace-layout :heading="$enquiry->property->title">
    <p class="mt-3 text-sm text-stone-600">
        {{ $enquiry->status->label() }} · {{ $enquiry->lead_status->label() }}
        @if ($enquiry->brokerIntroduction)
            · Broker introduction {{ $enquiry->brokerIntroduction->reference }}
        @endif
    </p>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">Buyer</h2>
        <dl class="mt-3 grid gap-2 text-sm text-stone-700 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-lamu-900">Name</dt>
                <dd>{{ $enquiry->buyer->name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-lamu-900">Email</dt>
                <dd>{{ $enquiry->buyer->email }}</dd>
            </div>
            <div>
                <dt class="font-medium text-lamu-900">Phone</dt>
                <dd>{{ $enquiry->buyer->phone ?: 'No phone recorded' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-lamu-900">Looking for</dt>
                <dd>{{ $enquiry->buyer->buyerProfile?->looking_for ?: 'No profile note' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="font-medium text-lamu-900">Preferred locality</dt>
                <dd>{{ $enquiry->buyer->buyerProfile?->preferred_locality ?: 'Not recorded' }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
        <h2 class="font-semibold text-lamu-900">{{ $enquiry->purchase_request ? 'Purchase request' : 'Enquiry' }}</h2>
        @if ($enquiry->purchase_request)
            <p class="mt-2 text-sm font-medium text-lamu-900">Proposed price: KSh {{ number_format($enquiry->requested_price) }} · Requested area: {{ $enquiry->requested_acreage ? $enquiry->requested_acreage.' acres' : 'Full offered area' }}</p>
            @if ($enquiry->salesTransaction)
                <p class="mt-1 text-sm text-stone-600">Sales case <a class="font-medium text-lamu-800 underline" href="{{ route('staff.sales.show', $enquiry->salesTransaction) }}">{{ $enquiry->salesTransaction->reference }}</a> · {{ $enquiry->salesTransaction->stage->label() }}</p>
            @endif
        @endif
        <p class="mt-3 whitespace-pre-line text-sm text-stone-700">{{ $enquiry->message }}</p>
        <dl class="mt-4 grid gap-2 text-sm text-stone-600 sm:grid-cols-2">
            <div>
                <dt class="font-medium text-lamu-900">Campaign</dt>
                <dd>{{ $enquiry->campaign ?: 'None recorded' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-lamu-900">Referring URL</dt>
                <dd class="break-all">{{ $enquiry->referrer_url ?: 'None recorded' }}</dd>
            </div>
        </dl>
    </section>

    @can('assign', $enquiry)
        <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Assignment</h2>
            <form method="POST" action="{{ route('staff.enquiries.assign', $enquiry) }}" data-confirm="Assign this enquiry?" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="assigned_to" class="block text-sm font-medium">Staff member</label>
                    <select id="assigned_to" name="assigned_to" required class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected($enquiry->assigned_to === $assignee->id)>{{ $assignee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Assign</button>
            </form>
        </section>
    @endcan

    @can('updateLead', $enquiry)
        <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Lead status</h2>
            <form method="POST" action="{{ route('staff.enquiries.update', $enquiry) }}" data-confirm="Update this enquiry status?" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                @method('PUT')
                <div>
                    <label for="status" class="block text-sm font-medium">Enquiry status</label>
                    <select id="status" name="status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($enquiry->status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="lead_status" class="block text-sm font-medium">Lead status</label>
                    <select id="lead_status" name="lead_status" class="mt-1 rounded-lg border border-sand-200 bg-white px-3 py-2">
                        @foreach ($leadStatuses as $status)
                            <option value="{{ $status->value }}" @selected($enquiry->lead_status === $status)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save status</button>
            </form>
        </section>
    @endcan

    <section class="mt-6 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">History</h2>
        <div class="mt-4 space-y-3">
            @forelse ($enquiry->responses as $response)
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-sm font-medium text-lamu-900">{{ $response->author->name }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $response->body }}</p>
                    <p class="mt-3 text-xs text-stone-500">
                        {{ $response->created_at->toDayDateTimeString() }}
                        · {{ $response->visible_to_buyer ? 'Visible to buyer' : 'Internal note' }}
                    </p>
                </article>
            @empty
                <p class="text-sm text-stone-600">No replies yet.</p>
            @endforelse
        </div>
    </section>

    @can('respond', $enquiry)
        <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="font-semibold text-lamu-900">Add a response</h2>
            <form method="POST" action="{{ route('staff.enquiries.respond', $enquiry) }}" data-confirm="Record this response?" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="body" class="block text-sm font-medium">Response</label>
                    <textarea id="body" name="body" rows="4" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('body') }}</textarea>
                    @error('body')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="visible_to_buyer" value="1" @checked(old('visible_to_buyer', true))>
                    Share this response with the buyer
                </label>
                <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record response</button>
            </form>
        </section>
    @endcan
</x-workspace-layout>
