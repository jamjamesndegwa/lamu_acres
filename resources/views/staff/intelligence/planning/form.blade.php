<x-workspace-layout heading="New planning record">
    <form method="POST" action="{{ route('staff.planning.store') }}" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-4">
        @csrf
        <label class="block text-sm">Title <input name="title" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Area <input name="area" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Instrument status
            <select name="instrument_status" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\PlanningInstrumentStatus::cases() as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm">Permitted uses <textarea name="permitted_uses" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea></label>
        <label class="block text-sm">Restrictions <textarea name="restrictions" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea></label>
        <label class="block text-sm">Effective date <input type="date" name="effective_on" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Source document <input name="source_document" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Source URL <input name="source_url" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Map reference <input name="map_reference" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></label>
        <label class="block text-sm">Notes <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea></label>
        <label class="block text-sm">Locality
            <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"><option value="">Not linked</option>@foreach ($localities as $locality)<option value="{{ $locality->id }}">{{ $locality->name }}</option>@endforeach</select>
        </label>
        <input type="file" name="document">
        @if ($errors->any()) <p class="text-sm text-red-700">{{ $errors->first() }}</p> @endif
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save draft</button>
    </form>
</x-workspace-layout>
