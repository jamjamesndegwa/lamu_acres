<?php

namespace App\Http\Requests\Broker;

use App\Models\BrokerIntroduction;
use App\Models\Property;
use App\Services\Sales\SaleModeTerms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreBrokerOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canUseBrokerFeatures() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'broker_introduction_id' => ['required', 'integer', Rule::exists('broker_introductions', 'id')],
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'requested_acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $introduction = BrokerIntroduction::query()
                ->where('broker_id', $this->user()?->id)
                ->find($this->integer('broker_introduction_id'));

            if ($introduction === null) {
                $validator->errors()->add('broker_introduction_id', 'This introduction is not available to your account.');

                return;
            }

            if ($introduction->buyer_id === null) {
                $validator->errors()->add('broker_introduction_id', 'The buyer must be linked before submitting an offer.');

                return;
            }

            $property = Property::query()->find($this->integer('property_id'));

            if (! $property instanceof Property || ! $property->isPubliclyListed()) {
                $validator->errors()->add('property_id', 'Choose a published listing.');

                return;
            }

            if (! BrokerIntroduction::query()->whereKey($introduction->id)->activeForBuyer($introduction->buyer, $property)->exists()) {
                $validator->errors()->add('broker_introduction_id', 'This introduction is not authorized for the selected property.');

                return;
            }

            try {
                app(SaleModeTerms::class)->validatePurchaseAcreage(
                    $property,
                    $this->filled('requested_acreage') ? (float) $this->input('requested_acreage') : null,
                );
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        }];
    }
}
