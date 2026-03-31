<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'amount'      => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
