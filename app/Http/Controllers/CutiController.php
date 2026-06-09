<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\CutiBalance;
use App\Models\Pegawai;
use App\Services\CutiApprovalService;
use App\Services\CutiBalanceService;
use App\Services\CutiDocumentService;
use App\Services\CutiEligibilityService;
use App\Services\WorkdayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
    ) {
        $this->middleware('auth');

        // Uncomment this line to enable role-based access
        // $this->middleware('role:super-admin|admin', ['except' => ['index', 'show', 'create', 'store']]);

        $this->middleware('permission:create cuti', ['only' => ['create', 'store']]);
        $this->middleware('permission:update cuti', ['only' => ['update', 'edit']]);
        $this->middleware('permission:delete cuti', ['only' => ['destroy']]);
        $this->middleware('permission:verifikasi cuti', ['only' => ['verifikasi', 'prosesVerifikasi']]);
        // Change these from role to permission
        $this->middleware('permission:pimpinan cuti', ['only' => ['verifikasiPimpinan', 'prosesVerifikasiPimpinan']]);
        $this->middleware('permission:atasan pimpinan cuti', ['only' => ['verifikasiAtasanPimpinan', 'prosesVerifikasiAtasanPimpinan']]);
    }

    public function index()
    {
        // If user has permission to verify or is pimpinan, show all leave requests
        if (auth()->user()->can('verifikasi cuti') || auth()->user()->can('pimpinan cuti')) {
            $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->latest()->paginate(10);
        } else {
            // Otherwise, show only the user's leave requests
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

        $jenisCuti = ['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Alasan Penting', 'Cuti Besar'];

        // Get current year leave balance
        $currentYear = date('Y');
        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)
            ->where('year', $currentYear)
            ->first();

        if (! $balance) {
            $balance = $this->cutiBalances->getOrCreateBalance($pegawai->uuid, $currentYear);
        }

        // Get list of potential pimpinan and atasan pimpinan
        // Using join with users table instead of relationship
        $pimpinanList = Pegawai::join('users', 'pegawai.nip', '=', 'users.nip')
            ->join('model_has_roles', 'users.uuid', '=', 'model_has_roles.uuid')
            ->join('roles', 'model_has_roles.role_uuid', '=', 'roles.uuid')
            ->where('roles.name', 'pimpinan')
            ->select('pegawai.*')
            ->get();

        $atasanPimpinanList = Pegawai::join('users', 'pegawai.nip', '=', 'users.nip')
            ->join('model_has_roles', 'users.uuid', '=', 'model_has_roles.uuid')
            ->join('roles', 'model_has_roles.role_uuid', '=', 'roles.uuid')
            ->where('roles.name', 'atasan-pimpinan')
            ->select('pegawai.*')
            ->get();

        return view('cuti.create', compact('pegawai', 'jenisCuti', 'balance', 'pimpinanList', 'atasanPimpinanList'));
    }

    // In the store method, add this validation before creating the cuti record
    public function store(Request $request)
    {
        $this->authorize('create', Cuti::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        $validated = $request->validate([
            'jenis_cuti' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'alamat_selama_cuti' => 'required|string',
            'no_hp_selama_cuti' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:2048',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ]);

        $validated['pegawai_uuid'] = $pegawai->uuid;

        $start = Carbon::parse($validated['tanggal_mulai']);
        $end = Carbon::parse($validated['tanggal_selesai']);

        // Eligibility checks via service
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $error = $this->cutiEligibility->checkAnnualLeave($pegawai->uuid, $start, $end);
            if ($error) {
                return redirect()->back()->withInput()->with('error', $error);
            }
        } elseif ($validated['jenis_cuti'] === 'Cuti Besar') {
            $error = $this->cutiEligibility->checkCutiBesar($pegawai, $start, $end);
            if ($error) {
                return redirect()->back()->withInput()->with('error', $error);
            }
        }

        $validated['uuid'] = Str::uuid();
        $validated['lama_cuti'] = WorkdayService::countWorkdays($start, $end);
        $validated['status'] = 'Pending';

        // Handle document upload
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

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->cutiBalances->getOrCreateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.show', compact('cuti', 'balance'));
    }

    public function edit($uuid)
    {
        $cuti = Cuti::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        if (($cuti->status == 'Disetujui Verifikator' || $cuti->status == 'Disetujui Pimpinan' || $cuti->status == 'Disetujui Atasan Pimpinan') &&
        (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('verifikator'))) {
            $this->authorize('editNoSurat', $cuti);
        } else {
            $this->authorize('update', $cuti);
        }

        // Allow editing no_surat_cuti for verified leave requests by admins
        if (($cuti->status == 'Disetujui Verifikator' || $cuti->status == 'Disetujui Pimpinan' || $cuti->status == 'Disetujui Atasan Pimpinan') &&
        (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('verifikator'))) {

            return view('cuti.edit-nomor', compact('cuti'));
        }

        // Only allow editing if status is still pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat diubah');
        }

        $jenisCuti = ['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Alasan Penting', 'Cuti Besar'];

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y');
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->cutiBalances->getOrCreateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.edit', compact('cuti', 'jenisCuti', 'balance'));
    }

    public function update(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $pegawai = $cuti->pegawai; // Get the related pegawai

        // Check if this is just a no_surat_cuti update
        if (($cuti->status == 'Disetujui Verifikator' || $cuti->status == 'Disetujui Pimpinan' || $cuti->status == 'Disetujui Atasan Pimpinan') &&
        (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('verifikator'))) {

            $this->authorize('editNoSurat', $cuti);

            $validated = $request->validate([
                'no_surat_cuti' => 'required|string|max:255',
            ]);

            $cuti->update([
                'no_surat_cuti' => $validated['no_surat_cuti'],
            ]);

            return redirect()->route('cuti.show', $cuti->uuid)->with('success', 'Nomor surat cuti berhasil diperbarui');
        }

        // Regular update logic for pending cuti
        // Only allow updating if status is still pending
        $this->authorize('update', $cuti);
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat diubah');
        }

        $validated = $request->validate([
            'jenis_cuti' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'alamat_selama_cuti' => 'required|string',
            'no_hp_selama_cuti' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:2048',
            'pimpinan_uuid' => 'sometimes|required|exists:pegawai,uuid',
            'atasan_pimpinan_uuid' => 'sometimes|required|exists:pegawai,uuid',
        ]);

        $start = Carbon::parse($validated['tanggal_mulai']);
        $end = Carbon::parse($validated['tanggal_selesai']);

        // Eligibility checks via service (excluding the current cuti being updated)
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $error = $this->cutiEligibility->checkAnnualLeave($cuti->pegawai_uuid, $start, $end, $cuti->uuid);
            if ($error) {
                return redirect()->back()->withInput()->with('error', $error);
            }
        } elseif ($validated['jenis_cuti'] === 'Cuti Besar') {
            $error = $this->cutiEligibility->checkCutiBesar($pegawai, $start, $end, $cuti->uuid);
            if ($error) {
                return redirect()->back()->withInput()->with('error', $error);
            }
        }

        $validated['lama_cuti'] = WorkdayService::countWorkdays($start, $end);

        // Handle document upload
        if ($request->hasFile('dokumen')) {
            // Delete old file if exists
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

        // Only allow deletion if status is still pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat dihapus');
        }

        // Delete document if exists
        if ($cuti->dokumen) {
            Storage::delete('public/dokumen/cuti/'.$cuti->dokumen);
        }

        $cuti->delete();

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil dihapus');
    }

    // verifikasi method (around line 166)
    public function verifikasi($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verify', $cuti);

        // Only allow verification if status is still Pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti ini sudah diverifikasi');
        }

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->cutiBalances->getOrCreateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi', compact('cuti', 'balance'));
    }

    // verifikasiPimpinan method (around line 190)
    public function verifikasiPimpinan($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $cuti);

        // Get the current user's pegawai record
        $user = Auth::user();
        $currentPimpinan = Pegawai::where('nip', $user->nip)->first();

        // Check if the current pimpinan is the assigned pimpinan for this request
        if ($currentPimpinan->uuid !== $cuti->pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        // Only allow pimpinan verification if already approved by verifikator
        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->cutiBalances->getOrCreateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-pimpinan', compact('cuti', 'balance'));
    }

    // Verifikasi atasan pimpinan method (around line 230)
    public function verifikasiAtasanPimpinan($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasanPimpinan', $cuti);

        // Get the current user's pegawai record
        $user = Auth::user();
        $currentAtasanPimpinan = Pegawai::where('nip', $user->nip)->first();

        // Check if the current atasan pimpinan is the assigned atasan pimpinan for this request
        if ($currentAtasanPimpinan->uuid !== $cuti->atasan_pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan atasan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        // Only allow atasan pimpinan verification if already approved by pimpinan
        if ($cuti->status !== 'Disetujui Pimpinan') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh pimpinan terlebih dahulu');
        }

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->cutiBalances->getOrCreateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-atasan-pimpinan', compact('cuti', 'balance'));
    }

    // Proses verifikasi method
    public function prosesVerifikasi(Request $request, $uuid)
    {
        $validated = $request->validate([
            'status_verifikator' => 'required|in:Disetujui,Ditolak',
            'catatan_verifikator' => 'nullable|string',
        ]);

        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verify', $cuti);

        $this->cutiApprovals->applyVerifikator($cuti, $validated['status_verifikator'], $validated['catatan_verifikator'] ?? null);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi');
    }

    // Proses verifikasi pimpinan method
    public function prosesVerifikasiPimpinan(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $cuti);

        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        $validated = $request->validate([
            'status_pimpinan' => 'required|in:Disetujui,Ditolak',
            'catatan_pimpinan' => 'nullable|string',
        ]);

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

    // Proses verifikasi atasan pimpinan method
    public function prosesVerifikasiAtasanPimpinan(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasanPimpinan', $cuti);

        if ($cuti->status !== 'Disetujui Pimpinan') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh pimpinan terlebih dahulu');
        }

        $validated = $request->validate([
            'status_atasan_pimpinan' => 'required|in:Disetujui,Ditolak',
            'catatan_atasan_pimpinan' => 'nullable|string',
        ]);

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

    // Add this method to generate PDF
    public function generatePdf($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan', 'atasanPimpinan'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('cetak', $cuti);

        // Check if cuti has been approved
        if ($cuti->status !== 'Disetujui Pimpinan' && $cuti->status !== 'Disetujui Atasan Pimpinan') {
            return redirect()->route('cuti.show', $cuti->uuid)
                ->with('error', 'Surat cuti hanya dapat dicetak setelah disetujui');
        }

        // Check if no_surat_cuti exists
        if (empty($cuti->no_surat_cuti)) {
            return redirect()->route('cuti.show', $cuti->uuid)
                ->with('error', 'Nomor surat cuti belum diisi. Silakan isi nomor surat terlebih dahulu.');
        }

        $pdf = $cuti->generatePdf();

        // Generate filename
        $filename = 'Surat_Cuti_'.$cuti->pegawai->nama.'_'.$cuti->no_surat_cuti.'.pdf';

        return $pdf->download($filename);
    }
}
