<?php

namespace App\Http\Requests;

use App\Services\Yandex\Exceptions\InvalidUrlException;
use App\Services\Yandex\YandexUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'max:2048',
                // Domain-level validation lives in one place (YandexUrl); the
                // rule just adapts its exception into a validation error.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        YandexUrl::parse((string) $value);
                    } catch (InvalidUrlException $e) {
                        $fail($e->getMessage());
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'Вставьте ссылку на карточку организации в Яндекс.Картах.',
        ];
    }
}
