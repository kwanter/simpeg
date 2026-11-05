<?php

namespace App\Http\Requests;

use App\Models\Cuti;
use App\Support\CutiType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCutiRequest extends FormRequest
{
    private ?Cuti $cachedCuti = null;

    private bool $cutiResolved = false;

    private ?bool $noSuratUpdate = null;

    public function authorize(): bool
    {
        return true; // authorization handled in controller via policy
    }

    /**
     * Resolve the route's Cuti once so rules() and the controller read the
     * same record (same memoized contract as UpdateIzinRequest).
     */
    public function resolvedCuti(): ?Cuti
    {
        if (! $this->cutiResolved) {
            $this->cutiResolved = true;
            $this->cachedCuti = Cuti::where('uuid', $this->route('uuid'))->first();
        }

        return $this->cachedCuti;
    }

    /**
     * Detect the letter-number-only submission produced by
     * resources/views/cuti/edit-nomor.blade.php. Mirrors
     * CutiController::isNoSuratEdit() so validation and the controller branch
     * never disagree.
     */
    public function isNoSuratUpdate(): bool
    {
        if ($this->noSuratUpdate !== null) {
            return $this->noSuratUpdate;
        }

        $cuti = $this->resolvedCuti();
        $role = $this->user();

        $this->noSuratUpdate = $cuti !== null
            && in_array($cuti->status, ['Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan'], true)
            && $role !== null
            && $role->hasAnyRole(['super-admin', 'admin', 'verifikator'])
            && $this->has('no_surat_cuti')
            && count($this->all()) <= 3;

        return $this->noSuratUpdate;
    }

    public function rules(): array
    {
        if ($this->isNoSuratUpdate()) {
            return [
                'no_surat_cuti' => ['required', 'string', 'max:255'],
            ];
        }

        return [
            'jenis_cuti' => ['required', 'string', 'in:'.implode(',', CutiType::all())],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string'],
            'alamat_selama_cuti' => ['required', 'string'],
            'no_hp_selama_cuti' => ['required', 'string'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:2048'],
            'pimpinan_uuid' => ['sometimes', 'required', 'exists:pegawai,uuid'],
            'atasan_pimpinan_uuid' => ['sometimes', 'required', 'exists:pegawai,uuid'],
        ];
    }
}
