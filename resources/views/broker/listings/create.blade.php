<x-crud-page title="Submit property" :close="route('broker.listings.index')" size="xl">
    <p class="mt-3 max-w-3xl text-stone-700">Staff review every submission before it can be published. Seller details and marketing authority stay off the public listing.</p>
    <x-step-list :steps="['Describe the land', 'Set price and location', 'Attach photos and authority', 'Submit for staff review']" />

    <form method="POST" action="{{ route('broker.listings.store') }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Submit this property?" class="mt-6 max-w-3xl space-y-4">
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
        <fieldset class="space-y-2">
            <legend class="text-sm font-medium">Owner’s intended sale mode</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (\App\Enums\SaleMode::cases() as $mode)
                    <label class="flex items-center gap-2 rounded-full border border-sand-200 px-4 py-2 text-sm">
                        <input type="radio" name="sale_mode" value="{{ $mode->value }}" @checked(old('sale_mode', 'whole') === $mode->value) required>
                        {{ $mode->label() }}
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-stone-600">This records the owner’s instructions for staff review. It does not replace marketing authority or seller verification.</p>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-measure-field name="acreage" label="Registered acreage" unit="acres" :value="old('acreage')" required />
            <x-measure-field name="offered_acreage" label="Acreage offered now (partial listings)" unit="acres" :value="old('offered_acreage')" />
            <x-measure-field name="minimum_acreage" label="Minimum portion (partial listings)" unit="acres" :value="old('minimum_acreage')" />
            <x-measure-field name="price_amount" label="Price" unit="KSh" :value="old('price_amount')" inputmode="numeric" required />
            <label class="block text-sm">
                <span class="font-medium">Purpose</span>
                <select name="purpose" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    @foreach (\App\Enums\ListingPurpose::cases() as $purpose)
                        <option value="{{ $purpose->value }}" @selected(old('purpose', request('purpose')) === $purpose->value)>{{ $purpose->label() }}</option>
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
            <span class="font-medium">Owner sale instructions (optional)</span>
            <textarea name="seller_sale_instructions" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('seller_sale_instructions') }}</textarea>
        </label>
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
        <x-upload-manager name="photos[]" label="Photographs" accept="image/*" multiple required />
        <x-upload-manager name="authority" label="Evidence of marketing authority" accept="image/*,.pdf" required />
        @if ($errors->any())
            <x-state kind="error" :message="$errors->first()" />
        @endif
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit for review</button>
    </form>
</x-crud-page>
