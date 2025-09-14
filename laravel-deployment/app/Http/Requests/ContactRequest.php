<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'service' => 'required|string|in:technical_issue,billing_dispute,account_locked,support,complaint,general_inquiry,partnership,sales_inquiry',
            'message' => 'required|string|max:2000',
            'priority' => 'sometimes|string|in:low,medium,high,urgent',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'service.required' => 'Please select a service.',
            'message.required' => 'Message is required.',
        ];
    }
}