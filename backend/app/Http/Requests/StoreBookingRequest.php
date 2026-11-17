<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email:rfc', 'max:254'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Заполните это поле.',
            'customer_email.email' => 'Введите корректный email.',
            'max' => 'Допустимо не более :max символов.',
            'slot_id.exists' => 'Слот не существует.',
            'slot_id.integer' => 'Некорректный ID слота.',
        ];
    }
}
