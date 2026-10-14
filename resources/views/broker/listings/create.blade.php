<x-workspace-layout heading="Submit property">
    <p class="mt-3 max-w-3xl text-stone-700">Staff review every submission before it can be published. Seller details and marketing authority stay off the public listing.</p>

    <form method="POST" action="{{ route('broker.listings.store') }}" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-4">
        @csrf
        <label class="block text-sm">
            <span class="font-medium">Title</span>
            <input name="title" value="{{ old('title') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="font-medium">Summary</span>
            <textarea name="summary" rows="3" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('summary') }}</textarea>
        </label>
        <label class="block text-sm">
            <span class="font-medium">Description</span>
            <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('description') }}</textarea>
        </label>
        <label class="block text-sm">
            <span class="font-medium">Locality</span>
            <select name="locality_id" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">Choose a locality</option>
                @foreach ($localities as $locality)
                    <option value="{{ $locality->id }}" @selected((string) old('locality_id') === (string) $locality->id)>{{ $locality->county->name }} — {{ $locality->name }}</option>
                @endforeach
            </select>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Acreage</span>
                <input name="acreage" value="{{ old('acreage') }}" inputmode="decimal" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Price (KSh)</span>
                <input name="price_amount" value="{{ old('price_amount') }}" inputmode="numeric" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Purpose</span>
                <select name="purpose" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    @foreach (\App\Enums\ListingPurpose::cases() as $purpose)
                        <option value="{{ $purpose->value }}" @selected(old('purpose') === $purpose->value)>{{ $purpose->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="font-medium">Property type</span>
                <select name="property_type" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    @foreach (\App\Enums\PropertyType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('property_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="block text-sm">
            <span class="font-medium">Parcel identifier</span>
            <input name="parcel_identifier" value="{{ old('parcel_identifier') }}" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </label>
        <label class="block text-sm">
            <span class="font-medium">Your relationship to the seller</span>
            <select name="marketing_role" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                @foreach (\App\Enums\MarketingRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('marketing_role') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Seller name</span>
                <input name="seller_name" value="{{ old('seller_name') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Seller contact</span>
                <input name="seller_contact" value="{{ old('seller_contact') }}" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
            </label>
        </div>
        <label class="block text-sm">
            <span class="font-medium">Photographs</span>
            <input name="photos[]" type="file" accept="image/*" multiple required class="mt-1 block w-full text-sm">
        </label>
        <label class="block text-sm">
            <span class="font-medium">Evidence of marketing authority</span>
            <input name="authority" type="file" accept="image/*,.pdf" required class="mt-1 block w-full text-sm">
        </label>
        @if ($errors->any())
            <p class="text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit for review</button>
    </form>
</x-workspace-layout>
