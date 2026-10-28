<x-crud-page title="Create sale" :close="route('staff.sales.index')" size="xl">
    <p class="mt-3 max-w-2xl text-sm text-stone-600">Link the buyer, the property, and the enquiry or referral that opened the deal. Opening a transaction does not complete a sale.</p>
    <form
        method="POST"
        action="{{ route('staff.sales.store') }}"
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
                        <option value="{{ $origin->value }}">{{ $origin->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-stone-700">Property
                <select name="property_id" data-sales-create-field required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                    <option value="">Select a property</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->title }} · {{ $property->status->label() }}</option>
                    @endforeach
                </select>
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
                        <option value="{{ $enquiry->id }}">Enquiry {{ $enquiry->id }}{{ $enquiry->property ? ' · '.$enquiry->property->title : '' }}</option>
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
            <label class="block text-sm font-medium text-stone-700">Sourcing request id
                <input name="sourcing_request_id" placeholder="Sourcing request id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-stone-700">Sale instruction id
                <input name="sale_instruction_id" data-sales-create-suggest="sale_instruction_id" placeholder="Sale instruction id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm font-medium text-stone-700">Acreage
                <input name="acreage" data-sales-create-suggest="acreage" placeholder="Acreage" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
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
