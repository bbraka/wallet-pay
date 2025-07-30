<?php

namespace App\Http\Requests\Merchant;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string'],
            'receiver_user_id' => [
                'nullable',
                'integer',
                'different:' . $this->user()->id,
                Rule::exists('users', 'id'),
                'required_without:top_up_provider_id'
            ],
            'top_up_provider_id' => [
                'nullable',
                'integer',
                Rule::exists('top_up_providers', 'id')->where('is_active', true),
                'required_without:receiver_user_id'
            ],
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_user_id.different' => 'Cannot transfer to yourself.',
            'receiver_user_id.required_without' => 'Either receiver user ID or top-up provider ID is required.',
            'top_up_provider_id.required_without' => 'Either top-up provider ID or receiver user ID is required.',
            'top_up_provider_id.exists' => 'Selected top-up provider is not available.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('receiver_user_id') && $this->has('top_up_provider_id')) {
            $this->merge([
                'top_up_provider_id' => null,
            ]);
        }
    }
}