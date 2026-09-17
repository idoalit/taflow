<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreThesisProposalRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'background' => ['required', 'string', 'min:50'],
            'problem_formulation' => ['required', 'string', 'min:20'],
            'objectives' => ['required', 'string', 'min:20'],
            'scope' => ['nullable', 'string'],
            'methodology' => ['nullable', 'string'],
            'roadmap_alignment' => ['nullable', 'string'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul proposal',
            'background' => 'latar belakang',
            'problem_formulation' => 'rumusan masalah',
            'objectives' => 'tujuan penelitian',
            'scope' => 'ruang lingkup',
            'methodology' => 'metodologi',
            'roadmap_alignment' => 'kesesuaian roadmap',
            'document' => 'dokumen proposal',
        ];
    }
}
