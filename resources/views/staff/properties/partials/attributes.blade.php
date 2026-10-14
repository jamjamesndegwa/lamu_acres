<form method="POST" action="{{ route('staff.properties.details.update', $property) }}" class="mt-6 space-y-6">
    @csrf
    @method('PUT')
    <section>
        <h2 class="text-lg font-semibold text-lamu-900">Highlights</h2>
        <textarea name="highlights" rows="5" class="mt-3 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('highlights', $property->highlights->pluck('body')->implode("\n")) }}</textarea>
    </section>
    <section>
        <h2 class="text-lg font-semibold text-lamu-900">Road access and infrastructure</h2>
        <label for="road_access" class="mt-3 block text-sm font-medium">Road access</label>
        <select id="road_access" name="road_access" class="mt-1 rounded-lg border border-sand-200 px-3 py-2">
            @foreach ($roads as $road)
                <option value="{{ $road->value }}" @selected(old('road_access', $property->road_access?->value) === $road->value)>{{ $road->label() }}</option>
            @endforeach
        </select>
        <label for="infrastructure_note" class="mt-4 block text-sm font-medium">Public infrastructure note</label>
        <textarea id="infrastructure_note" name="infrastructure_note" rows="3" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('infrastructure_note', $property->infrastructure_note) }}</textarea>
        @if ($property->county_id)
            <div class="mt-4 rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">Recorded infrastructure in this county</p>
                @forelse ($infrastructure as $feature)
                    <p class="mt-2 text-stone-700">{{ $feature->name }} · {{ $feature->kind->label() }} · {{ $feature->hasPoint() ? 'Named point stored' : 'Named point not stored' }} · {{ $feature->hasBoundary() ? 'verified boundary stored' : 'boundary not stored' }}</p>
                    <p class="text-xs text-stone-500">Source: {{ $feature->source }}</p>
                @empty
                    <p class="mt-2 text-stone-600">No infrastructure reference is recorded for this county.</p>
                @endforelse
            </div>
        @endif
    </section>
    <section>
        <h2 class="text-lg font-semibold text-lamu-900">Amenities</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach ($amenities as $amenity)
                @php($current = $property->amenityStates->first(fn ($row) => $row->amenity === $amenity)?->state?->value ?? 'unknown')
                <label class="text-sm">
                    <span class="font-medium">{{ $amenity->label() }}</span>
                    <select name="amenities[{{ $amenity->value }}]" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        @foreach (\App\Enums\AmenityState::cases() as $state)
                            <option value="{{ $state->value }}" @selected(old('amenities.'.$amenity->value, $current) === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
    </section>
    <section>
        <h2 class="text-lg font-semibold text-lamu-900">Documentation checklist</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach (['title_search' => 'Title search', 'survey_plan' => 'Survey plan'] as $code => $label)
                @php($current = $property->documentChecks->firstWhere('code', $code)?->state?->value ?? 'unknown')
                <label class="text-sm">
                    <span class="font-medium">{{ $label }}</span>
                    <select name="documents[{{ $code }}]" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                        @foreach ($documentStates as $state)
                            <option value="{{ $state->value }}" @selected(old('documents.'.$code, $current) === $state->value)>{{ $state->label() }}</option>
                        @endforeach
                    </select>
                </label>
            @endforeach
        </div>
    </section>
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save attributes</button>
</form>
