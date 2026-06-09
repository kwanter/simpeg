<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCutiRequest;
use App\Http\Requests\UpdateCutiNoSuratRequest;
use App\Http\Requests\UpdateCutiRequest;
use App\Http\Requests\VerifyAtasanPimpinanCutiRequest;
use App\Http\Requests\VerifyCutiRequest;
use App\Http\Requests\VerifyPimpinanCutiRequest;
use App\Models\Cuti;
use App\Models\CutiBalance;
use App\Models\Pegawai;
use App\Services\ApproverDirectoryService;
use App\Services\CutiApprovalService;
use App\Services\CutiBalanceService;
use App\Services\CutiDocumentService;
use App\Services\CutiEligibilityService;
use App\Services\WorkdayService;
use App\Support\CutiType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CutiController extends Controller
{
    public function __construct(
        private readonly CutiBalanceService $cutiBalances,
        private readonly CutiEligibilityService $cutiEligibility,
        private readonly CutiApprovalService $cutiApprovals,
        private readonly CutiDocumentService $cutiDocuments,
        private readonly ApproverDirectoryService $approvers,
    ) {
        $this->middleware('auth');

        $this->middleware('permission:create cuti', ['only' => ['create', 'store']]);
        $this->middleware('permission:update cuti', ['only' => ['update', 'edit']]);
        $this->middleware('permission:delete cuti', ['only' => ['destroy']]);
        $this->middleware('permission:verifikasi cuti', ['only' => ['verifikasi', 'prosesVerifikasi']]);
        $this->middleware('permission:pimpinan cuti', ['only' => ['verifikasiPimpinan', 'prosesVerifikasiPimpinan']]);
        $this->middleware('permission:atasan pimpinan cuti', ['only' => ['verifikasiAtasanPimpinan', 'prosesVerifikasiAtasanPimpinan']]);
    }

    public function index()
    {
        if (auth()->user()->can('verifikasi cuti') || auth()->user()->can('pimpinan cuti')) {
            $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->latest()->paginate(10);
        } else {
            $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();
            if (! $pegawai) {
                return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
            }
            $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])
                ->where('pegawai_uuid', $pegawai->uuid)
                ->latest()
                ->paginate(10);
        }

        return view('cuti.index', compact('cuti'));
    }

    public function create()
    {
        $this->authorize('create', Cuti::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
        }

        $currentYear = date('Y');
        $balance = $this->resolveBalance($pegawai->uuid, $currentYear);

        return view('cuti.create', [
            'pegawai' => $pegawai,
            'jenisCuti' => CutiType::all(),
            'balance' => $balance,
            'pimpinanList' => $this->approvers->pimpinanList(),
            'atasanPimpinanList' => $this->approvers->atasanList(),
        ]);
    }

    public function store(StoreCutiRequest $request)
    {
        $this->authorize('create', Cuti::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        $validated = $request->validated();
        $validated['pegawai_uuid'] = $pegawai->uuid;

        $start = Carbon::parse($validated['tanggal_mulai']);
        $end = Carbon::parse($validated['tanggal_selesai']);

        // Eligibility checks via service
        if ($error = $this->checkEligibility($validated['jenis_cuti'], $pegawai, $start, $end)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $validated['uuid'] = Str::uuid();
        $validated['lama_cuti'] = WorkdayService::countWorkdays($start, $end);
        $validated['status'] = 'Pending';

        if ($request->hasFile('dokumen')) {
            $validated['dokumen'] = $this->cutiDocuments->storeDocument($request->file('dokumen'));
        }

        Cuti::create($validated);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diajukan');
    }

    public function show($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $cuti);

        $balance = $this->balanceForCuti($cuti);

        return view('cuti.show', compact('cuti', 'balance'));
    }

    public function edit($uuid)
    {
        $cuti = Cuti::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        $isNoSuratEdit = $this->isNoSuratEdit($cuti);

        if ($isNoSuratEdit) {
            $this->authorize('editNoSurat', $cuti);

            return view('cuti.edit-nomor', compact('cuti'));
        }

        $this->authorize('update', $cuti);

        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat diubah');
        }

        $currentYear = date('Y');
        $balance = CutiType::requiresBalance($cuti->jenis_cuti)
            ? $this->resolveBalance($cuti->pegawai_uuid, $currentYear)
            : null;

        return view('cuti.edit', [
            'cuti' => $cuti,
            'jenisCuti' => CutiType::all(),
            'balance' => $balance,
        ]);
    }

    public function update(UpdateCutiRequest $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $pegawai = $cuti->pegawai;

        // No-surat update path for verified leave
        if ($this->isNoSuratEdit($cuti)) {
            $this->authorize('editNoSurat', $cuti);
            $validated = $request->validate((new UpdateCutiNoSuratRequest)->rules());
            $cuti->update(['no_surat_cuti' => $validated['no_surat_cuti']]);

            return redirect()->route('cuti.show', $cuti->uuid)->with('success', 'Nomor surat cuti berhasil diperbarui');
        }

        // Regular update for pending cuti
        $this->authorize('update', $cuti);
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat diubah');
        }

        $validated = $request->validated();
        $start = Carbon::parse($validated['tanggal_mulai']);
        $end = Carbon::parse($validated['tanggal_selesai']);

        if ($error = $this->checkEligibility($validated['jenis_cuti'], $pegawai, $start, $end, $cuti->uuid)) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        $validated['lama_cuti'] = WorkdayService::countWorkdays($start, $end);

        if ($request->hasFile('dokumen')) {
            if ($cuti->dokumen) {
                Storage::delete('public/dokumen/cuti/'.$cuti->dokumen);
            }
            $validated['dokumen'] = $this->cutiDocuments->storeDocument($request->file('dokumen'));
        }

        $cuti->update($validated);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $cuti);

        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat dihapus');
        }

        if ($cuti->dokumen) {
            Storage::delete('public/dokumen/cuti/'.$cuti->dokumen);
        }

        $cuti->delete();

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil dihapus');
    }

    public function verifikasi($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verify', $cuti);

        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti ini sudah diverifikasi');
        }

        return view('cuti.verifikasi', [
            'cuti' => $cuti,
            'balance' => $this->balanceForCuti($cuti),
        ]);
    }

    public function verifikasiPimpinan($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $cuti);

        $currentPimpinan = Pegawai::where('nip', Auth::user()->nip)->first();
        if ($currentPimpinan->uuid !== $cuti->pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        return view('cuti.verifikasi-pimpinan', [
            'cuti' => $cuti,
            'balance' => $this->balanceForCuti($cuti),
        ]);
    }

    public function verifikasiAtasanPimpinan($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasanPimpinan', $cuti);

        $currentAtasanPimpinan = Pegawai::where('nip', Auth::user()->nip)->first();
        if ($currentAtasanPimpinan->uuid !== $cuti->atasan_pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan atasan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        if ($cuti->status !== 'Disetujui Pimpinan') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh pimpinan terlebih dahulu');
        }

        return view('cuti.verifikasi-atasan-pimpinan', [
            'cuti' => $cuti,
            'balance' => $this->balanceForCuti($cuti),
        ]);
    }

    public function prosesVerifikasi(VerifyCutiRequest $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verify', $cuti);

        $validated = $request->validated();
        $this->cutiApprovals->applyVerifikator($cuti, $validated['status_verifikator'], $validated['catatan_verifikator'] ?? null);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi');
    }

    public function prosesVerifikasiPimpinan(VerifyPimpinanCutiRequest $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $cuti);

        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        $validated = $request->validated();
        $pimpinans = Pegawai::where('nip', auth()->user()->nip)->first();
        if (! $pimpinans) {
            return redirect()->route('cuti.index')->with('error', 'Data pimpinan tidak ditemukan');
        }

        if ($pimpinans->uuid !== $cuti->pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        $this->cutiApprovals->applyPimpinan($cuti, $pimpinans, $validated['status_pimpinan'], $validated['catatan_pimpinan'] ?? null);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi oleh pimpinan');
    }

    public function prosesVerifikasiAtasanPimpinan(VerifyAtasanPimpinanCutiRequest $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasanPimpinan', $cuti);

        if ($cuti->status !== 'Disetujui Pimpinan') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh pimpinan terlebih dahulu');
        }

        $validated = $request->validated();
        $atasanPimpinan = Pegawai::where('nip', auth()->user()->nip)->first();
        if (! $atasanPimpinan) {
            return redirect()->route('cuti.index')->with('error', 'Data atasan pimpinan tidak ditemukan');
        }

        if ($atasanPimpinan->uuid !== $cuti->atasan_pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan atasan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        $this->cutiApprovals->applyAtasanPimpinan($cuti, $atasanPimpinan, $validated['status_atasan_pimpinan'], $validated['catatan_atasan_pimpinan'] ?? null);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi oleh atasan pimpinan');
    }

    public function showBalance()
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('cuti')->with('error', 'Data pegawai tidak ditemukan');
        }

        $balance = $this->cutiBalances->getOrCreateBalance($pegawai->uuid, date('Y'));

        return view('cuti.balance', compact('balance', 'pegawai'));
    }

    public function updateBalance()
    {
        $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();

        if (! $pegawai) {
            return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
        }

        $this->cutiBalances->refreshBalance($pegawai->uuid, date('Y'));

        return redirect()->route('cuti.index')->with('success', 'Saldo cuti berhasil diperbarui');
    }

    public function updateAllBalances()
    {
        if (! auth()->user()->can('update cuti')) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini');
        }

        $uuids = Pegawai::pluck('uuid')->toArray();
        $this->cutiBalances->refreshAll($uuids, date('Y'));
        $count = count($uuids);

        return redirect()->route('cuti.index')->with('success', "Saldo cuti untuk $count pegawai berhasil diperbarui");
    }

    public function generatePdf($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan', 'atasanPimpinan'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('cetak', $cuti);

        if ($cuti->status !== 'Disetujui Pimpinan' && $cuti->status !== 'Disetujui Atasan Pimpinan') {
            return redirect()->route('cuti.show', $cuti->uuid)
                ->with('error', 'Surat cuti hanya dapat dicetak setelah disetujui');
        }

        if (empty($cuti->no_surat_cuti)) {
            return redirect()->route('cuti.show', $cuti->uuid)
                ->with('error', 'Nomor surat cuti belum diisi. Silakan isi nomor surat terlebih dahulu.');
        }

        $pdf = $cuti->generatePdf();
        $filename = 'Surat_Cuti_'.$cuti->pegawai->nama.'_'.$cuti->no_surat_cuti.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Resolve or create a leave balance for the given pegawai and year.
     */
    private function resolveBalance(string $pegawaiUuid, int|string $year): ?CutiBalance
    {
        return $this->cutiBalances->getOrCreateBalance($pegawaiUuid, $year);
    }

    /**
     * Get the balance for a cuti record (only for Cuti Tahunan).
     */
    private function balanceForCuti(Cuti $cuti): ?CutiBalance
    {
        if (! CutiType::requiresBalance($cuti->jenis_cuti)) {
            return null;
        }

        $currentYear = date('Y', strtotime($cuti->tanggal_mulai));

        return $this->resolveBalance($cuti->pegawai_uuid, $currentYear);
    }

    /**
     * Check if this is a no-surat edit for an already-verified leave.
     */
    private function isNoSuratEdit(Cuti $cuti): bool
    {
        return in_array($cuti->status, ['Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan'])
            && (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('verifikator'));
    }

    /**
     * Run eligibility check for the given jenis, returning an error string or null.
     */
    private function checkEligibility(string $jenisCuti, Pegawai $pegawai, Carbon $start, Carbon $end, ?string $excludeUuid = null): ?string
    {
        if ($jenisCuti === CutiType::TAHUNAN) {
            return $this->cutiEligibility->checkAnnualLeave($pegawai->uuid, $start, $end, $excludeUuid);
        }

        if ($jenisCuti === CutiType::BESAR) {
            return $this->cutiEligibility->checkCutiBesar($pegawai, $start, $end, $excludeUuid);
        }

        return null;
    }
}
