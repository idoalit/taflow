<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuidanceMessageRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['message' => 'pesan', 'attachment' => 'lampiran'];
    }
}
