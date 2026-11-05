<?php

namespace App\Http\Requests;

use App\Models\Izin;
use App\Traits\JenisIzinRulesTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIzinRequest extends FormRequest
{
    use JenisIzinRulesTrait;

    private ?Izin $cachedIzin = null;

    private bool $izinResolved = false;

    private ?bool $noSuratUpdate = null;

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

    /**
     * The Izin bound to this request, or a 404. The controller consumes this so
     * the 404 contract is enforced at the call site instead of being inherited
     * as a side effect of authorize() (COR-01). Doing so duplicates authorize()'s
     * own abort() deliberately: this is defence in depth on the boundary that
     * keeps 404 and 403 distinguishable (SEC-005).
     */
    public function resolvedIzinOrFail(): Izin
    {
        $izin = $this->resolvedIzin();

        if ($izin === null) {
            abort(404);
        }

        return $izin;
    }

    public function rules(): array
    {
        // Check if this is just a no_surat_izin update for an approved izin
        if ($this->isNoSuratUpdate()) {
            return [
                'no_surat_izin' => ['required', 'string', Rule::unique('izin', 'no_surat_izin')->ignore($this->route('izin'), 'uuid')],
            ];
        }

        $rules = $this->baseRules();

        return array_merge($rules, $this->jenisRules());
    }

    /**
     * Check if this is just a no_surat_izin update for an approved izin
     * (csrf, method, and no_surat_izin only).
     *
     * Memoized for the lifetime of the request so that rules() and the
     * controller action always read the same answer (spec §4.2, REQ-005).
     */
    public function isNoSuratUpdate(): bool
    {
        if ($this->noSuratUpdate !== null) {
            return $this->noSuratUpdate;
        }

        $izin = $this->resolvedIzin();

        $this->noSuratUpdate = $izin !== null
            && $izin->verifikasi_atasan == 'Disetujui'
            && $this->has('no_surat_izin')
            && count($this->all()) <= 3;

        return $this->noSuratUpdate;
    }
}
