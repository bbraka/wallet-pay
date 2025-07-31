<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Only allow admins
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
            'top_up_provider_id' => [
                'required',
                'integer',
                Rule::exists('top_up_providers', 'id')->where('is_active', true),
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . Order::MAX_TOP_UP_AMOUNT,
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'provider_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get the validation messages.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'user_id.required' => 'Please select a user.',
            'user_id.exists' => 'The selected user does not exist.',
            'top_up_provider_id.required' => 'Please select a top-up provider.',
            'top_up_provider_id.exists' => 'The selected provider is not available.',
            'amount.max' => 'The amount cannot exceed $' . number_format(Order::MAX_TOP_UP_AMOUNT, 2),
        ];
    }
}