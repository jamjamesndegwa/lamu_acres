@php
    $input = 'mt-1 w-full rounded-lg border border-sand-200 px-3 py-2';
    $purpose = old('purpose', $property->purpose?->value ?? 'sale');
    $saved = fn (string $section, mixed $value) => $property->exists && $property->purpose?->value === $section ? $value : null;
    $characteristics = [
        'property_type' => ['Property type', \App\Enums\PropertyType::cases(), $property->property_type?->value, true],
        'land_use' => ['Land use', \App\Enums\LandUse::cases(), $property->land_use?->value, true],
        'tenure' => ['Tenure', \App\Enums\LandTenure::cases(), $property->tenure?->value, true],
        'ownership_structure' => ['Ownership structure', \App\Enums\OwnershipStructure::cases(), $property->ownership_structure?->value, true],
        'occupation_status' => ['Occupation', \App\Enums\OccupationStatus::cases(), $property->occupation_status?->value, false],
    ];
    $parcel = [
        'parcel_reference_type' => ['Reference type', \App\Enums\ParcelReferenceType::cases(), $property->parcel_reference_type?->value, true],
        'subdivision_status' => ['Subdivision status', \App\Enums\SubdivisionStatus::cases(), $property->subdivision_status?->value, false],
        'boundary_status' => ['Boundary beacons', \App\Enums\BoundaryStatus::cases(), $property->boundary_status?->value, false],
    ];
    $labels = \App\Models\Property::listingTermLabels();
@endphp

<p class="mt-3 max-w-3xl text-sm text-stone-600">You can save a draft at any time. Fields marked <span class="font-semibold">*</span> are needed before you can submit for review. Staff verify ownership and authority before anything is published, and private details below are never shown publicly.</p>

@if ($errors->any())
    <div class="mt-4 max-w-3xl">
        <x-state kind="error" :message="'Please check the highlighted fields. '.$errors->first()" />
    </div>
@endif

<form method="POST" action="{{ $action }}" data-turbo-frame="_top" data-seller-listing-form @if ($bulkSave ?? false) data-owner-bulk-save @endif @if ($staffEntry ?? false) enctype="multipart/form-data" @endif class="mt-6 max-w-3xl space-y-10">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-lamu-900">1. Listing type</h2>
        <fieldset>
            <legend class="text-sm font-medium">Do you want to sell or lease this land? *</legend>
            <div class="mt-2 flex flex-wrap gap-3">
                @foreach (\App\Enums\ListingPurpose::cases() as $option)
                    <label class="flex items-center gap-2 rounded-full border border-sand-200 px-4 py-2 text-sm">
                        <input type="radio" name="purpose" value="{{ $option->value }}" @checked($purpose === $option->value) required data-purpose-choice>
                        {{ $option === \App\Enums\ListingPurpose::Sale ? 'Sell the land' : 'Lease the land' }}
                    </label>
                @endforeach
            </div>
            @error('purpose')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </fieldset>
        @if ($staffEntry ?? false)
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="font-medium">Marketing role *</span>
                    <select name="marketing_role" required class="{{ $input }}">
                        @foreach (\App\Enums\MarketingRole::cases() as $role)
                            <option value="{{ $role->value }}" @selected(old('marketing_role', $marketingRole ?? \App\Enums\MarketingRole::AuthorizedMarketer->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @error('marketing_role')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">Internal reference</span>
                    <input name="reference" value="{{ old('reference') }}" maxlength="32" class="{{ $input }}" placeholder="Generated if empty">
                    @error('reference')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
            </div>
        @else
            <label class="block text-sm">
                <span class="font-medium">Your relationship to the land *</span>
                <select name="relationship" required class="{{ $input }}">
                    @foreach (\App\Enums\OwnerRelationship::sellerFacing() as $option)
                        <option value="{{ $option->value }}" @selected(old('relationship', $relationship) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-stone-500">Co-owners and authorised representatives will be asked for written authority from the other owners.</span>
                @error('relationship')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
        @endif
    </section>

    @if ($staffEntry ?? false)
        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-lamu-900">Owner contact</h2>
            <p class="text-sm text-stone-600">Link a landowner’s seller account if one exists. If you leave this blank, the listing is recorded as <strong>Lamu Acres</strong> agency inventory. Ownership documents are not required at registration.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="font-medium">Linked seller account</span>
                    <select name="owner_user_id" class="{{ $input }}">
                        <option value="">Lamu Acres inventory (no linked owner)</option>
                        @foreach ($owners ?? [] as $owner)
                            <option value="{{ $owner->id }}" @selected((string) old('owner_user_id') === (string) $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                        @endforeach
                    </select>
                    @error('owner_user_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">Owner relationship</span>
                    <select name="owner_relationship" class="{{ $input }}">
                        @foreach (\App\Enums\OwnerRelationship::sellerFacing() as $option)
                            <option value="{{ $option->value }}" @selected(old('owner_relationship', $relationship) === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="font-medium">Owner name (if no account)</span>
                    <input name="seller_name" value="{{ old('seller_name') }}" maxlength="160" class="{{ $input }}">
                    @error('seller_name')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm">
                    <span class="font-medium">Owner contact</span>
                    <input name="seller_contact" value="{{ old('seller_contact') }}" maxlength="80" class="{{ $input }}">
                    @error('seller_contact')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
            </div>
        </section>
    @endif

    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-lamu-900">2. Land identity and location</h2>
        <label class="block text-sm">
            <span class="font-medium">Listing title *</span>
            <input name="title" value="{{ old('title', $property->title) }}" required maxlength="160" class="{{ $input }}">
            @error('title')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm">
            <span class="font-medium">Short summary *</span>
            <textarea name="summary" rows="2" maxlength="500" class="{{ $input }}">{{ old('summary', $property->summary) }}</textarea>
            @error('summary')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>
        <label class="block text-sm">
            <span class="font-medium">Full description *</span>
            <textarea name="description" rows="5" maxlength="5000" class="{{ $input }}">{{ old('description', $property->description) }}</textarea>
            @error('description')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">County *</span>
                <select name="county_id" data-county-select class="{{ $input }}">
                    <option value="">Choose a county</option>
                    @foreach ($counties as $county)
                        <option value="{{ $county->id }}" @selected((string) old('county_id', $property->county_id) === (string) $county->id)>{{ $county->name }}</option>
                    @endforeach
                </select>
                @error('county_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">
                <span class="font-medium">Locality *</span>
                <select name="locality_id" data-locality-select class="{{ $input }}">
                    <option value="">Choose a locality</option>
                    @foreach ($counties as $county)
                        @foreach ($county->localities as $locality)
                            <option value="{{ $locality->id }}" data-county="{{ $county->id }}" @selected((string) old('locality_id', $property->locality_id) === (string) $locality->id)>{{ $locality->name }}@if ($locality->parent) ({{ $locality->parent->name }})@endif</option>
                        @endforeach
                    @endforeach
                </select>
                @error('locality_id')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">
                <span class="font-medium">Nearest landmark</span>
                <input name="landmark" value="{{ old('landmark', $property->landmark) }}" maxlength="160" class="{{ $input }}">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Road access</span>
                <select name="road_access" class="{{ $input }}">
                    @foreach (\App\Enums\RoadAccess::cases() as $option)
                        <option value="{{ $option->value }}" @selected(old('road_access', $property->road_access?->value ?? 'unknown') === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="block text-sm">
            <span class="font-medium">How to reach the land</span>
            <textarea name="access_description" rows="2" maxlength="1000" class="{{ $input }}">{{ old('access_description', $property->access_description) }}</textarea>
        </label>
        <x-property-location-map
            :public-lat="old('public_latitude', $property->public_latitude)"
            :public-lng="old('public_longitude', $property->public_longitude)"
            :restricted-lat="old('restricted_latitude', $property->restricted_latitude)"
            :restricted-lng="old('restricted_longitude', $property->restricted_longitude)"
        />
        @error('public_latitude')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @error('public_longitude')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @error('restricted_latitude')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @error('restricted_longitude')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror

        @php($saleMode = old('sale_mode', $property->purpose?->value === 'sale' ? $property->saleMode()->value : 'whole'))
        <h3 class="pt-2 font-medium text-lamu-900">Parcel</h3>
        <fieldset class="space-y-2" data-purpose-section="sale" @if ($purpose !== 'sale') hidden @endif>
            <legend class="text-sm font-medium">How do you want to sell? *</legend>
            <div class="flex flex-wrap gap-3">
                @foreach (\App\Enums\SaleMode::cases() as $mode)
                    <label class="flex items-center gap-2 rounded-full border border-sand-200 px-4 py-2 text-sm">
                        <input type="radio" name="sale_mode" value="{{ $mode->value }}" @checked($saleMode === $mode->value) data-sale-mode-choice>
                        {{ $mode->label() }}
                    </label>
                @endforeach
            </div>
            @error('sale_mode')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
            <p class="text-xs text-stone-600">Whole-parcel listings require buyers to purchase all land currently offered. Partial listings let buyers request a specific acreage above your minimum.</p>
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Title or land reference number *</span>
                <input name="parcel_identifier" value="{{ old('parcel_identifier', $property->parcel_identifier) }}" maxlength="80" class="{{ $input }}">
                <span class="mt-1 block text-stone-500">Kept private. Used to check ownership and avoid duplicate listings.</span>
                @error('parcel_identifier')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">
                <span class="font-medium">Survey or plan reference</span>
                <input name="survey_reference" value="{{ old('survey_reference', $property->survey_reference) }}" maxlength="80" class="{{ $input }}">
            </label>
            @foreach ($parcel as $name => [$label, $options, $current, $required])
                <label class="block text-sm">
                    <span class="font-medium">{{ $label }}{{ $required ? ' *' : '' }}</span>
                    <select name="{{ $name }}" class="{{ $input }}">
                        <option value="">Not stated</option>
                        @foreach ($options as $option)
                            <option value="{{ $option->value }}" @selected(old($name, $current) === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error($name)<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
            @endforeach
            <x-measure-field name="acreage" label="Registered parcel size *" unit="acres" :value="old('acreage', $property->acreage)" />
            <div data-partial-sale-fields @if ($saleMode !== 'partial') hidden @endif class="contents">
                <x-measure-field name="offered_acreage" label="Total acreage available for sale now" unit="acres" :value="old('offered_acreage', $property->offered_acreage ?? $property->acreage)" />
                <x-measure-field name="minimum_acreage" label="Minimum purchase size *" unit="acres" :value="old('minimum_acreage', $property->minimum_acreage)" />
            </div>
        </div>
        @foreach (['acreage', 'offered_acreage', 'minimum_acreage'] as $name)
            @error($name)<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @endforeach
    </section>

    <section class="space-y-4">
        <h2 class="text-lg font-semibold text-lamu-900">3. Ownership and land characteristics</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($characteristics as $name => [$label, $options, $current, $required])
                <label class="block text-sm">
                    <span class="font-medium">{{ $label }}{{ $required ? ' *' : '' }}</span>
                    <select name="{{ $name }}" class="{{ $input }}">
                        <option value="">Not stated</option>
                        @foreach ($options as $option)
                            <option value="{{ $option->value }}" @selected(old($name, $current) === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error($name)<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
        <fieldset>
            <legend class="text-sm font-medium">Services and features</legend>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                @foreach (\App\Enums\Amenity::cases() as $amenity)
                    @php($current = $property->amenityStates->first(fn ($row) => $row->amenity === $amenity)?->state)
                    <label class="block text-sm">
                        <span class="font-medium">{{ $amenity->label() }}</span>
                        @if ($current === \App\Enums\AmenityState::Verified)
                            <span class="mt-1 block text-stone-600">Verified by staff</span>
                        @else
                            <select name="amenities[{{ $amenity->value }}]" class="{{ $input }}">
                                @foreach ([\App\Enums\AmenityState::Unknown, \App\Enums\AmenityState::Available, \App\Enums\AmenityState::Unavailable] as $state)
                                    <option value="{{ $state->value }}" @selected(old('amenities.'.$amenity->value, $current?->value ?? 'unknown') === $state->value)>{{ $state->label() }}</option>
                                @endforeach
                            </select>
                        @endif
                    </label>
                @endforeach
            </div>
        </fieldset>
        @foreach (\App\Http\Requests\Seller\SellerListingRequest::SHARED_TERMS as $key)
            <label class="block text-sm">
                <span class="font-medium">{{ $labels[$key] ?? $key }}</span>
                <textarea name="terms[{{ $key }}]" rows="2" maxlength="1000" class="{{ $input }}">{{ old('terms.'.$key, $property->listingTerm($key)) }}</textarea>
                @error('terms.'.$key)<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
        @endforeach
    </section>

    <section class="space-y-4" data-purpose-section="sale" @if ($purpose !== 'sale') hidden @endif>
        <h2 class="text-lg font-semibold text-lamu-900">4. Sale terms</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Price basis *</span>
                <select name="sale[price_basis]" class="{{ $input }}">
                    @foreach (\App\Enums\PriceBasis::forPurpose(\App\Enums\ListingPurpose::Sale) as $basis)
                        <option value="{{ $basis->value }}" @selected(old('sale.price_basis', $saved('sale', $property->price_basis?->value)) === $basis->value)>{{ $basis->label() }}</option>
                    @endforeach
                </select>
                @error('sale.price_basis')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
            <x-measure-field name="sale[amount]" label="Asking price (total, or per acre) *" unit="KSh" :value="old('sale.amount', $saved('sale', $property->price_rate))" inputmode="numeric" />
            <label class="block text-sm">
                <span class="font-medium">Available from</span>
                <input type="date" name="sale[available_from]" value="{{ old('sale.available_from', $saved('sale', $property->available_from?->toDateString())) }}" class="{{ $input }}">
            </label>
            <label class="mt-6 flex items-center gap-2 text-sm">
                <input type="hidden" name="sale[negotiable]" value="0">
                <input type="checkbox" name="sale[negotiable]" value="1" @checked(old('sale.negotiable', $saved('sale', $property->price_negotiable)))>
                Price is negotiable
            </label>
        </div>
        @error('sale.amount')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @foreach (\App\Http\Requests\Seller\SellerListingRequest::SALE_TERMS as $key)
            <label class="block text-sm">
                <span class="font-medium">{{ $labels[$key] ?? $key }}</span>
                <textarea name="sale[{{ $key }}]" rows="2" maxlength="1000" class="{{ $input }}">{{ old('sale.'.$key, $saved('sale', $property->listingTerm($key))) }}</textarea>
            </label>
        @endforeach
    </section>

    <section class="space-y-4" data-purpose-section="lease" @if ($purpose !== 'lease') hidden @endif>
        <h2 class="text-lg font-semibold text-lamu-900">4. Lease terms</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium">Rent basis *</span>
                <select name="lease[price_basis]" class="{{ $input }}">
                    @foreach (\App\Enums\PriceBasis::forPurpose(\App\Enums\ListingPurpose::Lease) as $basis)
                        <option value="{{ $basis->value }}" @selected(old('lease.price_basis', $saved('lease', $property->price_basis?->value)) === $basis->value)>{{ $basis->label() }}</option>
                    @endforeach
                </select>
                @error('lease.price_basis')<span class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
            </label>
            <x-measure-field name="lease[amount]" label="Asking rent *" unit="KSh" :value="old('lease.amount', $saved('lease', $property->price_rate))" inputmode="numeric" />
            <x-measure-field name="lease[min_months]" label="Minimum lease term *" unit="months" :value="old('lease.min_months', $saved('lease', $property->lease_min_months))" inputmode="numeric" />
            <x-measure-field name="lease[max_months]" label="Maximum lease term" unit="months" :value="old('lease.max_months', $saved('lease', $property->lease_max_months))" inputmode="numeric" />
            <label class="block text-sm">
                <span class="font-medium">Available from</span>
                <input type="date" name="lease[available_from]" value="{{ old('lease.available_from', $saved('lease', $property->available_from?->toDateString())) }}" class="{{ $input }}">
            </label>
            <label class="mt-6 flex items-center gap-2 text-sm">
                <input type="hidden" name="lease[negotiable]" value="0">
                <input type="checkbox" name="lease[negotiable]" value="1" @checked(old('lease.negotiable', $saved('lease', $property->price_negotiable)))>
                Rent is negotiable
            </label>
        </div>
        @foreach (['lease.amount', 'lease.min_months', 'lease.max_months'] as $name)
            @error($name)<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
        @endforeach
        @foreach (\App\Http\Requests\Seller\SellerListingRequest::LEASE_TERMS as $key)
            <label class="block text-sm">
                <span class="font-medium">{{ $labels[$key] ?? $key }}</span>
                <textarea name="lease[{{ $key }}]" rows="2" maxlength="1000" class="{{ $input }}">{{ old('lease.'.$key, $saved('lease', $property->listingTerm($key))) }}</textarea>
            </label>
        @endforeach
        <p class="text-sm text-stone-600">Lamu Acres records confirmed leases but does not collect rent or manage tenancies.</p>
    </section>

    @if ($staffEntry ?? false)
        <section class="space-y-4">
            <h2 class="text-lg font-semibold text-lamu-900">Photographs</h2>
            <p class="text-sm text-stone-600">At least one photograph is required (same as a seller submission). Location metadata is stripped from copies shown to buyers.</p>
            <x-upload-manager name="photos[]" label="Select photographs * (JPEG, PNG or WebP, up to 5 MB each)" accept="image/jpeg,image/png,image/webp" multiple required />
            @error('photos')<span class="block text-sm text-red-700">{{ $message }}</span>@enderror
            @foreach ($errors->get('photos.*') as $messages)
                @foreach ($messages as $message)
                    <span class="block text-sm text-red-700">{{ $message }}</span>
                @endforeach
            @endforeach
        </section>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        @if ($bulkSave ?? false)
            <p data-owner-save-progress role="status" aria-live="polite" class="text-sm text-stone-700"></p>
            <ul data-owner-save-errors role="alert" class="space-y-1 text-sm text-red-700"></ul>
            <p class="text-sm text-stone-600">This saves all the details above and uploads the photographs and documents you have selected on this page. Files already uploaded are kept.</p>
        @endif
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">{{ $submitLabel }}</button>
        @if ($staffEntry ?? false)
            <span class="text-sm text-stone-600">The listing is published immediately after you save. No seller approval queue.</span>
        @else
            <span class="text-sm text-stone-600">Saving does not submit the listing. You will review and submit it on the next page.</span>
        @endif
    </div>
</form>

<script>
    (function () {
        const form = document.querySelector('[data-seller-listing-form]');
        if (!form) {
            return;
        }
        const sections = form.querySelectorAll('[data-purpose-section]');
        const showPurpose = () => {
            const chosen = form.querySelector('[data-purpose-choice]:checked')?.value;
            sections.forEach((section) => {
                section.hidden = section.dataset.purposeSection !== chosen;
            });
        };
        form.querySelectorAll('[data-purpose-choice]').forEach((input) => input.addEventListener('change', showPurpose));

        const county = form.querySelector('[data-county-select]');
        const locality = form.querySelector('[data-locality-select]');
        const filterLocalities = () => {
            locality.querySelectorAll('option[data-county]').forEach((option) => {
                option.hidden = county.value !== '' && option.dataset.county !== county.value;
            });
            if (locality.selectedOptions[0]?.hidden) {
                locality.value = '';
            }
        };
        county?.addEventListener('change', filterLocalities);
        if (county && locality) {
            filterLocalities();
        }

        const partialFields = form.querySelector('[data-partial-sale-fields]');
        const showSaleMode = () => {
            const chosen = form.querySelector('[data-sale-mode-choice]:checked')?.value;
            if (partialFields) {
                partialFields.hidden = chosen !== 'partial';
            }
        };
        form.querySelectorAll('[data-sale-mode-choice]').forEach((input) => input.addEventListener('change', showSaleMode));
        showSaleMode();
    })();
</script>
