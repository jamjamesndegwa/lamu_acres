<?php

namespace App\Http\Requests\Staff;

use App\Enums\MarketingRole;
use App\Enums\OwnerRelationship;
use App\Http\Requests\Seller\SellerListingRequest;
use App\Models\Property;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Validation\Rule;

class StaffPropertyListingRequest extends SellerListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['relationship']);

        return array_merge($rules, [
            'relationship' => ['nullable', Rule::in(array_map(fn (OwnerRelationship $relationship) => $relationship->value, OwnerRelationship::sellerFacing()))],
            'reference' => ['nullable', 'string', 'max:32', Rule::unique('properties', 'reference')],
            'marketing_role' => ['required', Rule::enum(MarketingRole::class)],
            'seller_name' => ['nullable', 'string', 'max:160'],
            'seller_contact' => ['nullable', 'string', 'max:80'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_relationship' => ['nullable', Rule::enum(OwnerRelationship::class)],
            'photos' => ['nullable', 'array', 'max:12'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.PropertyMediaLibrary::MAX_KILOBYTES],
        ]);
    }
}
