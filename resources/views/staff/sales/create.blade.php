<x-workspace-layout heading="Create sale">
    <p class="mt-3 max-w-2xl text-sm text-stone-600">Link the buyer, the property, and the enquiry or referral that opened the deal. Opening a transaction does not complete a sale.</p>
    <form method="POST" action="{{ route('staff.sales.store') }}" data-turbo-frame="_top" data-confirm="Open this sales transaction?" class="mt-6 max-w-xl space-y-3">
        @csrf
        <label class="block text-sm font-medium text-stone-700">Origin
            <select name="origin" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2" required>
                @foreach ($origins as $origin)
                    <option value="{{ $origin->value }}">{{ $origin->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-stone-700">Property
            <select name="property_id" required class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Select a property</option>
                @foreach ($properties as $property)
                    <option value="{{ $property->id }}">{{ $property->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-stone-700">Buyer
            <select name="buyer_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">Link later</option>
                @foreach ($buyers as $buyer)
                    <option value="{{ $buyer->id }}">{{ $buyer->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-stone-700">Originating enquiry
            <select name="enquiry_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">None</option>
                @foreach ($enquiries as $enquiry)
                    <option value="{{ $enquiry->id }}">Enquiry {{ $enquiry->id }}{{ $enquiry->property ? ' · '.$enquiry->property->title : '' }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium text-stone-700">Originating referral
            <select name="broker_introduction_id" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
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
            <input name="sale_instruction_id" placeholder="Sale instruction id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium text-stone-700">Acreage
            <input name="acreage" placeholder="Acreage" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
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
    </form>
</x-workspace-layout>
