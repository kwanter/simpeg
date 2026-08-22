<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIzinRequest;
use App\Http\Requests\UpdateIzinRequest;
use App\Http\Requests\VerifyAtasanIzinRequest;
use App\Http\Requests\VerifyPimpinanIzinRequest;
use App\Models\Izin;
use App\Models\Pegawai;
use App\Services\ApproverDirectoryService;
use App\Services\IzinApprovalService;
use App\Services\IzinDocumentService;
use App\Services\IzinQueryService;
use App\Services\WorkdayService;
use App\Support\IzinType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IzinController extends Controller
{
    public function __construct(
        private readonly ApproverDirectoryService $approvers,
        private readonly IzinQueryService $izinQuery,
        private readonly IzinDocumentService $izinDocuments,
        private readonly IzinApprovalService $izinApproval,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorize('viewAny', Izin::class);
        $izinList = $this->izinQuery->forUser(Auth::user())->latest()->paginate(10);

        return view('izin.index', compact('izinList'));
    }

    public function create()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = $user->pegawai;

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        return view('izin.create', $this->formData($pegawai));
    }

    public function edit($uuid)
    {
        $user = Auth::user();
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $izin);

        // Allow editing if it's an admin or if the izin is approved by atasan but needs no_surat_izin
        $allowEdit = $user->hasRole('super-admin') || $user->hasRole('admin') ||
                     ($izin->verifikasi_atasan == 'Disetujui' && empty($izin->no_surat_izin)) ||
                     ($izin->verifikasi_atasan == 'Belum Diverifikasi' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi');

        if (! $allowEdit) {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat diedit');
        }

        return view('izin.edit', array_merge(
            $this->formData($izin->pegawai),
            ['izin' => $izin]
        ));
    }

    /**
     * Shared form data for create/edit and PERMA-specific create methods.
     */
    private function formData(Pegawai $pegawai): array
    {
        return [
            'pegawai' => $pegawai,
            'jenisIzin' => IzinType::all(),
            'pimpinanList' => $this->approvers->pimpinanList(),
            'atasanList' => $this->approvers->atasanList(),
        ];
    }

    // In the store method, remove no_surat_izin from validation and don't set it initially
    public function store(StoreIzinRequest $request)
    {
        $this->authorize('create', Izin::class);
        $validated = $request->validated();

        if (! Auth::user()->hasAnyRole(['super-admin', 'admin']) || empty($validated['pegawai_uuid'])) {
            $validated['pegawai_uuid'] = Auth::user()->pegawai()->firstOrFail()->uuid;
        }

        $validated['uuid'] = Str::uuid();

        // Calculate jumlah_hari
        $jumlahHari = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);
        $validated['jumlah_hari'] = $jumlahHari;

        // Jenis-specific PERMA validation runs in StoreIzinRequest::rules()
        if (IzinType::isSingleLevel($validated['jenis_izin'])) {
            // Same-day time-range, jumlah_hari = 0
            $validated['jumlah_hari'] = 0;
            $validated['tanggal_mulai'] = now()->toDateString();
            $validated['tanggal_selesai'] = now()->toDateString();
            // Single-level jenis — keep pimpinan_uuid value but it is unused in verification flow.
        } elseif ($validated['jenis_izin'] === IzinType::TIDAK_MASUK) {
            // Maks 2 hari kerja (Pasal 8 ayat 4 PERMA No. 7 Tahun 2016)
            $workDays = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);
            if ($workDays > IzinType::maxWorkdays(IzinType::TIDAK_MASUK)) {
                throw ValidationException::withMessages([
                    'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
                ]);
            }
        }

        if ($request->hasFile('dokumen')) {
            $validated['dokumen'] = $this->izinDocuments->storeDocument($request->file('dokumen'));
        }

        $izin = new Izin($validated);
        $izin->status = 'Diajukan';
        $izin->verifikasi_atasan = 'Belum Diverifikasi';
        $izin->verifikasi_pimpinan = 'Belum Diverifikasi';
        $izin->save();

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dibuat');
    }

    public function show($uuid)
    {
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $izin);

        return view('izin.show', compact('izin'));
    }

    public function update(UpdateIzinRequest $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $izin);
        $user = Auth::user();

        // Check if this is just a no_surat_izin update for an approved izin
        $isNoSuratUpdate = $request->isNoSuratUpdate();

        // Don't allow full updating if already verified by pimpinan or atasan
        if (! $isNoSuratUpdate &&
            ($izin->verifikasi_pimpinan !== 'Belum Diverifikasi' ||
             ($izin->verifikasi_atasan !== 'Belum Diverifikasi' && ! $user->hasRole('super-admin') && ! $user->hasRole('admin')))) {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat diubah');
        }

        if ($isNoSuratUpdate) {
            // Only validate and update no_surat_izin
            $validated = $request->validated();

            $izin->update(['no_surat_izin' => $validated['no_surat_izin']]);

            return redirect()->route('izin.index')->with('success', 'Nomor surat izin berhasil diperbarui');
        }

        // Full update for non-verified izin; jenis-specific PERMA validation runs in UpdateIzinRequest::rules()
        $validated = $request->validated();
        $validated['jumlah_hari'] = WorkdayService::countWorkdays(
            $validated['tanggal_mulai'],
            $validated['tanggal_selesai']
        );

        if (IzinType::isSingleLevel($validated['jenis_izin'])) {
            $validated['jumlah_hari'] = 0;
            $validated['tanggal_mulai'] = now()->toDateString();
            $validated['tanggal_selesai'] = now()->toDateString();
        } elseif ($validated['jenis_izin'] === IzinType::TIDAK_MASUK) {
            if ($validated['jumlah_hari'] > IzinType::maxWorkdays(IzinType::TIDAK_MASUK)) {
                throw ValidationException::withMessages([
                    'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
                ]);
            }
        }

        $oldDocument = $izin->dokumen;
        if ($request->hasFile('dokumen')) {
            $validated['dokumen'] = $this->izinDocuments->storeDocument($request->file('dokumen'));
        }

        try {
            $izin->update($validated);
        } catch (\Throwable $e) {
            if (isset($validated['dokumen'])) {
                $this->izinDocuments->delete($validated['dokumen']);
            }
            throw $e;
        }

        if (isset($validated['dokumen']) && $oldDocument) {
            $this->izinDocuments->delete($oldDocument);
        }

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $izin);

        $document = $izin->dokumen;
        if (! $izin->delete()) {
            abort(500, 'Pengajuan izin gagal dihapus.');
        }
        if ($document) {
            $this->izinDocuments->delete($document);
        }

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dihapus');
    }

    public function verifikasiAtasan($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasan', $izin);

        return view('izin.verifikasi-atasan', compact('izin'));
    }

    public function verifikasiPimpinan($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $izin);

        return view('izin.verifikasi-pimpinan', compact('izin'));
    }

    public function prosesVerifikasiAtasan(VerifyAtasanIzinRequest $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasan', $izin);

        $validated = $request->validated();

        $this->izinApproval->applyAtasan(
            $izin,
            $validated['verifikasi_atasan'],
            $validated['catatan_atasan']
        );

        return redirect()->route('izin.index')->with('success', 'Verifikasi atasan berhasil dilakukan');
    }

    public function prosesVerifikasiPimpinan(VerifyPimpinanIzinRequest $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $izin);

        $validated = $request->validated();

        $this->izinApproval->applyPimpinan(
            $izin,
            $validated['verifikasi_pimpinan'],
            $validated['catatan_pimpinan']
        );

        return redirect()->route('izin.index')->with('success', 'Verifikasi pimpinan berhasil dilakukan');
    }

    public function generatePdf($uuid)
    {
        $izin = Izin::with(['pegawai', 'atasan_pimpinan', 'pimpinan'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('cetak', $izin);

        $template = IzinType::pdfTemplate($izin->jenis_izin);

        $pdf = \PDF::loadView($template, ['izin' => $izin]);

        $filename = 'Surat_Izin_'.$izin->pegawai->nama.'_'.($izin->no_surat_izin ?? $izin->uuid).'.pdf';

        return $pdf->download($filename);
    }

    public function downloadDocument(string $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $izin);

        if (! $izin->dokumen || ! $this->izinDocuments->exists($izin->dokumen)) {
            abort(404);
        }

        return $this->izinDocuments->download($izin->dokumen);
    }

    /**
     * Dedicated create form for Izin Keluar Kantor (Lampiran II PERMA No. 7/2016).
     */
    public function createKeluarKantor()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = $user->pegawai;

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        $data = $this->formData($pegawai);
        $data['jenisIzin'] = IzinType::KELUAR_KANTOR;

        return view('izin.create-keluar-kantor', $data);
    }

    /**
     * Dedicated create form for Izin Tidak Masuk Kerja (Lampiran III PERMA No. 7/2016).
     */
    public function createTidakMasuk()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = $user->pegawai;

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        return view('izin.create-tidak-masuk', $this->formData($pegawai));
    }

    /**
     * Index for Izin Keluar Kantor & Izin Pulang Cepat.
     */
    public function indexKeluarKantor()
    {
        $this->authorize('viewAny', Izin::class);

        $izins = $this->izinQuery
            ->forUser(Auth::user(), IzinType::keluarKantorGroup()->all())
            ->latest()
            ->paginate(10);

        return view('izin.index-keluar-kantor', compact('izins'));
    }

    /**
     * Index for Izin Tidak Masuk Kerja.
     */
    public function indexTidakMasuk()
    {
        $this->authorize('viewAny', Izin::class);

        $izins = $this->izinQuery
            ->forUser(Auth::user(), [IzinType::TIDAK_MASUK])
            ->latest()
            ->paginate(10);

        return view('izin.index-tidak-masuk', compact('izins'));
    }
}
