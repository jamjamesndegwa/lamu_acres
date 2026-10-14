<?php

namespace App\Http\Requests\Staff;

use App\Models\Property;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePropertyVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property && ($this->user()?->can('manageMedia', $property) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'video_url' => ['required', 'string', 'max:255', 'starts_with:https://'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $url = (string) $this->input('video_url');
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $allowed = ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com'];

            if (! in_array($host, $allowed, true)) {
                $validator->errors()->add('video_url', 'Use an https link from YouTube or Vimeo.');
            }
        });
    }
}
