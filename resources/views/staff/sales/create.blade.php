<x-workspace-layout heading="Open transaction">
    <form method="POST" action="{{ route('staff.sales.store') }}" data-turbo-frame="_top" data-confirm="Open this sales transaction?" class="mt-6 max-w-xl space-y-3">
        @csrf
        <select name="origin" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2" required>
            @foreach ($origins as $origin)
                <option value="{{ $origin->value }}">{{ $origin->label() }}</option>
            @endforeach
        </select>
        <input name="property_id" placeholder="Property id" required class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="buyer_id" placeholder="Buyer id" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="enquiry_id" placeholder="Enquiry id" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="sourcing_request_id" placeholder="Sourcing request id" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="broker_introduction_id" placeholder="Broker introduction id" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="sale_instruction_id" placeholder="Sale instruction id" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <input name="acreage" placeholder="Acreage" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        <select name="assigned_to" class="w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">Assign later</option>
            @foreach ($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }}</option>
            @endforeach
        </select>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Open transaction</button>
    </form>
</x-workspace-layout>
