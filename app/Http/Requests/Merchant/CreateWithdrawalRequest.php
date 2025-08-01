<?php

namespace App\Http\Requests\Merchant;

use Illuminate\Foundation\Http\FormRequest;

class CreateWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . ($this->user()->wallet_amount ?? 0)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Withdrawal amount is required.',
            'amount.numeric' => 'Withdrawal amount must be a valid number.',
            'amount.min' => 'Withdrawal amount must be at least $0.01.',
            'amount.max' => 'Insufficient balance. Your current balance is $' . number_format($this->user()->wallet_amount ?? 0, 2) . '.',
        ];
    }
}