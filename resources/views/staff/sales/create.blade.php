<x-crud-page title="Create sale" :close="route('staff.sales.index')" size="xl">
    <p class="mt-3 max-w-2xl text-sm text-stone-600">Choose the listed property and link a buyer or supporting records when you have them. Opening a transaction does not complete a sale.</p>
    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <p class="font-medium">This transaction could not be opened.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form
        method="POST"
        action="{{ route('staff.sales.store') }}"
        data-turbo="false"
        data-turbo-frame="_top"
        data-confirm="Open this sales transaction?"
        data-sales-create
        data-context-url="{{ route('staff.sales.create.context') }}"
        class="mt-6 grid gap-6 lg:grid-cols-5"
    >
        @csrf
        <div class="space-y-3 lg:col-span-2">
            <label class="block text-sm font-medium text-stone-700">Origin
                <select name="origin" data-sales-create-field data-sales-create-suggest="origin" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2" required>
                    @foreach ($origins as $origin)
                        <option value="{{ $origin->value }}" @selected(old('origin', \App\Enums\SalesOrigin::Listed->value) === $origin->value)>{{ $origin->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Property
                <select name="property_id" data-sales-create-field required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">Select a property</option>
                    @foreach ($properties as $property)
                        @php($sellerOptions = $property->sellerDeskOptions())
                        <option
                            value="{{ $property->id }}"
                            data-remaining-acreage="{{ $property->remainingAcreage() }}"
                            data-offered-acreage="{{ $property->offered_acreage }}"
                            data-minimum-acreage="{{ $property->minimum_acreage }}"
                            data-sellers="{{ e(json_encode($sellerOptions)) }}"
                            data-preferred-seller-id="{{ $property->preferredSellerUserId() }}"
                        >{{ $property->title }} · {{ $property->status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Seller
                <select data-sales-create-seller data-sales-create-suggest="seller_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2" aria-label="Seller on this property">
                    <option value="">Select a property first</option>
                </select>
                <p class="mt-1 text-xs text-stone-500">Filled from verified owners on the selected listing. Choose another owner if several are linked.</p>
            </label>
            <label class="block text-sm font-medium text-stone-700">Buyer
                <select name="buyer_id" data-sales-create-field class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">Link later</option>
                    @foreach ($buyers as $buyer)
                        <option value="{{ $buyer->id }}">{{ $buyer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Originating enquiry
                <select name="enquiry_id" data-sales-create-suggest="enquiry_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">None</option>
                    @foreach ($enquiries as $enquiry)
                        <option value="{{ $enquiry->id }}" data-property-id="{{ $enquiry->property_id }}">Enquiry {{ $enquiry->id }}{{ $enquiry->property ? ' · '.$enquiry->property->title : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Originating referral
                <select name="broker_introduction_id" data-sales-create-suggest="broker_introduction_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">None</option>
                    @foreach ($introductions as $introduction)
                        <option value="{{ $introduction->id }}">{{ $introduction->reference }}{{ $introduction->buyer_name ? ' · '.$introduction->buyer_name : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Sourcing request
                <select name="sourcing_request_id" data-sales-create-suggest="sourcing_request_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">None</option>
                    @foreach ($sourcingRequests as $sourcingRequest)
                        <option value="{{ $sourcingRequest->id }}">{{ $sourcingRequest->reference }} · {{ $sourcingRequest->buyer?->name ?? 'Buyer' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Sale instruction
                <select name="sale_instruction_id" data-sales-create-suggest="sale_instruction_id" data-sales-create-field class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">None</option>
                    @foreach ($saleInstructions as $instruction)
                        <option value="{{ $instruction->id }}" data-property-id="{{ $instruction->property_id }}">#{{ $instruction->id }} · {{ $instruction->property?->title ?? 'Property' }}{{ $instruction->seller ? ' · '.$instruction->seller->name : '' }} · {{ $instruction->kind }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Acreage
                <select name="acreage" data-sales-create-suggest="acreage" data-sales-create-acreage class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">Default (remaining on property)</option>
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Agent
                <select name="assigned_to" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">Assign later</option>
                    @foreach ($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Open transaction</button>
        </div>
        <div class="lg:col-span-3">
            <h2 class="text-sm font-semibold text-lamu-900">Deal intelligence</h2>
            <p class="mt-1 text-xs text-stone-500">Updates when you change property, buyer, or origin. Suggested links apply only to empty fields.</p>
            <div class="mt-3">
                @include('staff.sales.partials.create-context', ['context' => [
                    'property' => null,
                    'sellers' => [],
                    'sale_instructions' => [],
                    'buyer_offers' => [],
                    'property_offers' => [],
                    'matching_offers' => [],
                    'open_transactions' => [],
                    'linkable_enquiries' => [],
                    'linkable_introductions' => [],
                    'alerts' => [],
                    'suggestions' => [],
                ]])
            </div>
        </div>
    </form>
</x-crud-page>
