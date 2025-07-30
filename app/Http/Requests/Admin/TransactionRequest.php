<?php

namespace App\Http\Requests\Admin;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
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
        $rules = [
            'type' => [
                'required',
                Rule::enum(TransactionType::class),
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];

        // On create, user_id is required
        if ($this->isMethod('POST')) {
            $rules['user_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ];
        }

        return $rules;
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
            'type.required' => 'Please select a transaction type.',
            'type.enum' => 'Invalid transaction type.',
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be greater than 0.',
        ];
    }
}