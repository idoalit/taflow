<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreResearchLogbookRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'log_date' => ['required', 'date', 'before_or_equal:today'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'description' => ['required', 'string', 'min:10'],
            'attachment' => ['nullable', 'file', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'log_date' => 'tanggal',
            'progress' => 'progres penelitian',
            'description' => 'deskripsi kegiatan',
            'attachment' => 'lampiran',
        ];
    }
}
