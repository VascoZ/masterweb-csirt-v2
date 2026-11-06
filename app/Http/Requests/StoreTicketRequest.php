<?php

namespace App\Http\Requests;

use App\Enums\IncidentCategory;
use App\Enums\Tlp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Kembali ke form, bukan ke "URL sebelumnya" yang bisa berupa gambar captcha.
     */
    protected $redirectRoute = 'tickets.create';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attachment = config('csirt.ticket_attachment');

        return [
            'reporter_name' => ['required', 'string', 'max:255'],
            'reporter_email' => ['required', 'email', 'max:255'],
            'reporter_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'reporter_organization' => ['nullable', 'string', 'max:255'],
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'incident_at' => ['nullable', 'date', 'before_or_equal:now'],
            'affected_asset' => ['nullable', 'string', 'max:500'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'tlp' => ['required', Rule::enum(Tlp::class)],
            'attachments' => ['nullable', 'array', 'max:' . $attachment['max_files']],
            'attachments.*' => ['file', 'max:' . $attachment['max_kb'], 'mimes:' . implode(',', $attachment['mimes'])],
            'agreement' => ['accepted'],
            'captcha' => ['required', 'captcha'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reporter_name' => 'nama',
            'reporter_email' => 'email',
            'reporter_phone' => 'nomor telepon',
            'reporter_organization' => 'instansi/organisasi',
            'category' => 'jenis insiden',
            'incident_at' => 'waktu kejadian',
            'affected_asset' => 'aset terdampak',
            'description' => 'kronologi',
            'attachments.*' => 'lampiran',
            'agreement' => 'persetujuan',
        ];
    }

    public function messages(): array
    {
        return ['captcha.captcha' => 'Kode captcha tidak sesuai.'];
    }
}
