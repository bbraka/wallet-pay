<?php

namespace App\Http\Requests\Merchant;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'lte:max_amount'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'receiver_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.before_or_equal' => 'Start date must be before or equal to end date.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'min_amount.lte' => 'Minimum amount must be less than or equal to maximum amount.',
            'max_amount.gte' => 'Maximum amount must be greater than or equal to minimum amount.',
        ];
    }
}