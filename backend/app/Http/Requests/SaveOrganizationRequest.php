<?php

namespace App\Http\Requests;

use App\Services\Yandex\YandexUrl;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

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
                'required', 'string', 'max:2048',
                // Доменную проверку держим в одном месте (YandexUrl), тут только
                // превращаем её исключение в ошибку валидации поля.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        YandexUrl::parse((string) $value);
                    } catch (InvalidArgumentException $e) {
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
