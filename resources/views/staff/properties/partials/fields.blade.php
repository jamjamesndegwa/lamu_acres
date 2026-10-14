<form method="POST" action="{{ $route }}" data-turbo-frame="_top" data-confirm="Save this property?" class="space-y-4">
    @csrf
    @if ($property->exists) @method('PUT') @endif
    @if ($parent)
        <input type="hidden" name="parent_id" value="{{ $parent->id }}">
        <p class="text-sm text-stone-600">Portion of {{ $parent->reference }}. {{ number_format($parent->remainingAcreage(), 2) }} acres remain.</p>
    @endif
    <label class="block text-sm font-medium">Reference
        <input name="reference" value="{{ old('reference', $property->reference) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Generated if empty">
    </label>
    <label class="block text-sm font-medium">Title
        <input name="title" value="{{ old('title', $property->title) }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <label class="block text-sm font-medium">Description
        <textarea name="description" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('description', $property->description) }}</textarea>
    </label>
    <label class="block text-sm font-medium">Portion label
        <input name="portion_label" value="{{ old('portion_label', $property->portion_label) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">County
            <select name="county_id" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach ($counties as $county)
                    <option value="{{ $county->id }}" @selected((string) old('county_id', $property->county_id) === (string) $county->id)>{{ $county->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium">Locality
            <select name="locality_id" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach ($counties as $county)
                    @foreach ($county->localities as $locality)
                        <option value="{{ $locality->id }}" @selected((string) old('locality_id', $property->locality_id) === (string) $locality->id)>{{ $county->name }} — {{ $locality->name }}</option>
                    @endforeach
                @endforeach
            </select>
        </label>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Type
            <select name="property_type" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\PropertyType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('property_type', $property->property_type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium">Land use
            <select name="land_use" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\LandUse::cases() as $use)
                    <option value="{{ $use->value }}" @selected(old('land_use', $property->land_use?->value) === $use->value)>{{ $use->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium">Sale or lease
            <select name="purpose" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\ListingPurpose::cases() as $purpose)
                    <option value="{{ $purpose->value }}" @selected(old('purpose', $property->purpose?->value) === $purpose->value)>{{ $purpose->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-sm font-medium">Marketing
            <select name="marketing_role" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\MarketingRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('marketing_role', $property->marketing_role?->value) === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </label>
        <x-measure-field name="acreage" label="Acreage" unit="acres" :value="old('acreage', $property->acreage)" required />
        <x-measure-field name="price_amount" label="Asking price" unit="KSh" :value="old('price_amount', $property->price_amount)" inputmode="numeric" required />
    </div>
    <p class="text-sm text-stone-600">Price per acre: {{ $property->exists && $property->pricePerAcre() ? 'KSh '.number_format($property->pricePerAcre()) : 'Calculated when acreage and price are saved.' }}</p>
    <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-medium">Public latitude
            <input name="public_latitude" value="{{ old('public_latitude', $property->public_latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Public longitude
            <input name="public_longitude" value="{{ old('public_longitude', $property->public_longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Restricted latitude
            <input name="restricted_latitude" value="{{ old('restricted_latitude', $property->restricted_latitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm font-medium">Restricted longitude
            <input name="restricted_longitude" value="{{ old('restricted_longitude', $property->restricted_longitude) }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
    </div>
    @if ($errors->any())
        <p class="text-sm text-red-700">{{ $errors->first() }}</p>
    @endif
    <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save property</button>
</form>
