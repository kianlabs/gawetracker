<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'work_type' => ['nullable', 'in:remote,onsite,hybrid'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'applied_at' => ['required', 'date'],
            'salary_note' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_info' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'interview_result' => ['nullable', 'string'],
            'status' => ['required', 'in:wishlist,applied,screening,interview,offer,hired,rejected'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company.required' => 'Nama perusahaan wajib diisi.',
            'position.required' => 'Posisi pekerjaan wajib diisi.',
            'applied_at.required' => 'Tanggal melamar wajib diisi.',
            'applied_at.date' => 'Format tanggal melamar tidak valid.',
            'work_type.in' => 'Tipe kerja harus salah satu dari: remote, onsite, atau hybrid.',
            'source_url.url' => 'Tautan lowongan harus berupa URL yang valid.',
            'status.required' => 'Status lamaran wajib dipilih.',
            'status.in' => 'Status yang dipilih tidak valid.',
        ];
    }
}
