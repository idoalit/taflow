<?php

namespace App\Http\Requests\Thesis;

use Illuminate\Foundation\Http\FormRequest;

class StoreExamRequestRequest extends FormRequest
{
    /**
     * Supporting documents are a repeating name + file pair in the design.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'student_notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*.name' => ['required', 'string', 'max:255'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_notes' => 'catatan mahasiswa',
            'documents' => 'dokumen pendukung',
            'documents.*.name' => 'nama dokumen',
            'documents.*.file' => 'berkas dokumen',
        ];
    }
}
