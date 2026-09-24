<?php

namespace App\Http\Requests;

use App\Models\Izin;
use App\Support\IzinType;
use App\Traits\JenisIzinRulesTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIzinRequest extends FormRequest
{
    use JenisIzinRulesTrait;

    private ?Izin $cachedIzin = null;

    private bool $izinResolved = false;

    public function authorize(): bool
    {
        $izin = $this->resolvedIzin();

        if ($izin === null) {
            abort(404);
        }

        return $this->user()->can('update', $izin);
    }

    /**
     * The Izin bound to this request, resolved at most once and shared by
     * authorize(), rules(), and the controller action (spec REQ-005, AC-021).
     *
     * Returns null when the route uuid matches no record. authorize() converts
     * that into a 404 rather than a policy denial, so the response stays
     * indistinguishable from a missing resource (spec REQ-012, SEC-005).
     */
    public function resolvedIzin(): ?Izin
    {
        if (! $this->izinResolved) {
            $this->izinResolved = true;
            $this->cachedIzin = Izin::where('uuid', $this->route('izin'))->first();
        }

        return $this->cachedIzin;
    }

    public function rules(): array
    {
        // Check if this is just a no_surat_izin update for an approved izin
        if ($this->isNoSuratUpdate()) {
            return [
                'no_surat_izin' => ['required', 'string', Rule::unique('izin', 'no_surat_izin')->ignore($this->route('izin'), 'uuid')],
            ];
        }

        $rules = [
            'jenis_izin' => ['required', 'string', 'in:'.implode(',', IzinType::all())],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i'],
            'alasan' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:2048'],
            'atasan_pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
            'pimpinan_uuid' => ['required', 'exists:pegawai,uuid'],
        ];

        return array_merge($rules, $this->jenisRules());
    }

    /**
     * Check if this is just a no_surat_izin update for an approved izin
     * (csrf, method, and no_surat_izin only).
     */
    public function isNoSuratUpdate(): bool
    {
        $izin = $this->resolvedIzin();

        return $izin !== null
            && $izin->verifikasi_atasan == 'Disetujui'
            && $this->has('no_surat_izin')
            && count($this->all()) <= 3;
    }
}
