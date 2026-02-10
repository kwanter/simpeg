<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\CutiBalance;
use App\Models\Pegawai;
use App\Models\User;
use App\Services\WorkdayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CutiController extends Controller
{
    public function __construct()
    {
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
            $balance = CutiBalance::checkAndUpdateBalance($pegawai->uuid, $currentYear);
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

    // Add this method to ensure a balance record exists for the employee
    private function ensureCutiBalance($pegawaiUuid, $year)
    {
        $balance = CutiBalance::where('pegawai_uuid', $pegawaiUuid)
            ->where('year', $year)
            ->first();

        if (! $balance) {
            // Create new balance for current year
            $carriedOver = 0;

            // Check if there's a previous year balance to carry over (max 6 days)
            $previousYearBalance = CutiBalance::where('pegawai_uuid', $pegawaiUuid)
                ->where('year', $year - 1)
                ->first();

            if ($previousYearBalance) {
                $remainingPreviousYear = $previousYearBalance->total_days +
                                        $previousYearBalance->carried_over -
                                        $previousYearBalance->used_days;
                $carriedOver = min(6, max(0, $remainingPreviousYear));
            }

            $balance = CutiBalance::create([
                'uuid' => Str::uuid(),
                'pegawai_uuid' => $pegawaiUuid,
                'year' => $year,
                'total_days' => 12, // Default annual leave
                'used_days' => 0,
                'carried_over' => $carriedOver,
            ]);
        }

        return $balance;
    }

    // In the store method, add this validation before creating the cuti record
    public function store(Request $request)
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->firstOrFail();

        $validated = $request->validate([
            'jenis_cuti' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'alamat_selama_cuti' => 'required|string',
            'no_hp_selama_cuti' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $validated['pegawai_uuid'] = $pegawai->uuid;

        // Calculate leave duration - count only workdays
        $lamaCuti = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);

        // Check annual leave limit if the type is "Cuti Tahunan"
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));
            $balance = $this->ensureCutiBalance($pegawai->uuid, $currentYear);
            $remainingDays = $balance->total_days + $balance->carried_over - $balance->used_days;

            // Check if trying to apply for Cuti Tahunan but already has Cuti Besar in the same year
            $existingCutiBesar = Cuti::where('pegawai_uuid', $pegawai->uuid)
                ->where('jenis_cuti', 'Cuti Besar')
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan']) // Consider approved/pending
                ->exists();

            if ($existingCutiBesar) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Besar di tahun ini, sehingga tidak dapat mengajukan Cuti Tahunan.');
            }

            if ($lamaCuti > $remainingDays) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Sisa cuti tahunan Anda tidak mencukupi. Sisa cuti: {$remainingDays} hari, permintaan: {$lamaCuti} hari.");
            }
        }
        // --- Start Cuti Besar Validation ---
        elseif ($validated['jenis_cuti'] === 'Cuti Besar') {
            // Check masa kerja (work period) - assuming 'tanggal_masuk' exists on Pegawai model
            if (! $pegawai->tanggal_masuk) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Tanggal masuk kerja pegawai tidak ditemukan untuk validasi Cuti Besar.');
            }
            $masaKerja = now()->diffInYears(\Carbon\Carbon::parse($pegawai->tanggal_masuk));
            if ($masaKerja < 5) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Cuti Besar hanya dapat diajukan oleh pegawai dengan masa kerja minimal 5 tahun.');
            }

            // Check if the requested leave is not more than 3 months (approx 90 workdays)
            // Note: Using calculated $lamaCuti which counts workdays
            if ($lamaCuti > 90) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Cuti Besar maksimal 3 bulan (sekitar 90 hari kerja). Permintaan Anda: '.$lamaCuti.' hari.');
            }

            // Check if there's already a Cuti Tahunan in the same year
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));
            $existingCutiTahunan = Cuti::where('pegawai_uuid', $pegawai->uuid)
                ->where('jenis_cuti', 'Cuti Tahunan')
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan']) // Consider approved/pending
                ->exists();

            if ($existingCutiTahunan) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Tahunan di tahun ini, sehingga tidak dapat mengajukan Cuti Besar.');
            }

            // Optional: Check if Cuti Besar already taken in the same year (usually only allowed once every few years, but basic check here)
            $existingCutiBesar = Cuti::where('pegawai_uuid', $pegawai->uuid)
                ->where('jenis_cuti', 'Cuti Besar')
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan']) // Consider approved/pending
                ->exists();

            if ($existingCutiBesar) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Besar di tahun ini.');
            }
        }
        // --- End Cuti Besar Validation ---

        $validated['uuid'] = Str::uuid();
        $validated['lama_cuti'] = $lamaCuti;
        $validated['status'] = 'Pending';

        // Handle document upload
        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/dokumen/cuti', $filename);
            $validated['dokumen'] = $filename;
        }

        Cuti::create($validated);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diajukan');
    }

    public function show($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->where('uuid', $uuid)->firstOrFail();

        // Get leave balance for annual leave
        $balance = null;
        if ($cuti->jenis_cuti == 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = CutiBalance::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('year', $currentYear)
                ->first();

            if (! $balance) {
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.show', compact('cuti', 'balance'));
    }

    public function edit($uuid)
    {
        $cuti = Cuti::with('pegawai')->where('uuid', $uuid)->firstOrFail();

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
                $balance = CutiBalance::checkAndUpdateBalance($cuti->pegawai_uuid, $currentYear);
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
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        // Calculate leave duration - count only workdays
        $lamaCuti = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);

        // Check annual leave limit if the type is "Cuti Tahunan"
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));
            $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            $usedDaysExcludingThis = $balance->used_days;
            if ($cuti->jenis_cuti === 'Cuti Tahunan' && date('Y', strtotime($cuti->tanggal_mulai)) == $currentYear) {
                $usedDaysExcludingThis -= $cuti->lama_cuti;
            }
            $remainingDays = $balance->total_days + $balance->carried_over - $usedDaysExcludingThis;

            // Check if trying to apply for Cuti Tahunan but already has Cuti Besar in the same year
            $existingCutiBesar = Cuti::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('jenis_cuti', 'Cuti Besar')
                ->where('uuid', '!=', $cuti->uuid) // Exclude current request
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan'])
                ->exists();

            if ($existingCutiBesar) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Besar di tahun ini, sehingga tidak dapat mengajukan Cuti Tahunan.');
            }

            if ($lamaCuti > $remainingDays) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Sisa cuti tahunan Anda tidak mencukupi. Sisa cuti: {$remainingDays} hari, permintaan: {$lamaCuti} hari.");
            }
        }
        // --- Start Cuti Besar Validation ---
        elseif ($validated['jenis_cuti'] === 'Cuti Besar') {
            // Check masa kerja (work period)
            if (! $pegawai->tanggal_masuk) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Tanggal masuk kerja pegawai tidak ditemukan untuk validasi Cuti Besar.');
            }
            $masaKerja = now()->diffInYears(\Carbon\Carbon::parse($pegawai->tanggal_masuk));
            if ($masaKerja < 5) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Cuti Besar hanya dapat diajukan oleh pegawai dengan masa kerja minimal 5 tahun.');
            }

            // Check if the requested leave is not more than 3 months (approx 90 workdays)
            if ($lamaCuti > 90) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Cuti Besar maksimal 3 bulan (sekitar 90 hari kerja). Permintaan Anda: '.$lamaCuti.' hari.');
            }

            // Check if there's already a Cuti Tahunan in the same year
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));
            $existingCutiTahunan = Cuti::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('jenis_cuti', 'Cuti Tahunan')
                ->where('uuid', '!=', $cuti->uuid) // Exclude current request
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan'])
                ->exists();

            if ($existingCutiTahunan) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Tahunan di tahun ini, sehingga tidak dapat mengajukan Cuti Besar.');
            }

            // Optional: Check if Cuti Besar already taken in the same year (excluding current)
            $existingCutiBesar = Cuti::where('pegawai_uuid', $cuti->pegawai_uuid)
                ->where('jenis_cuti', 'Cuti Besar')
                ->where('uuid', '!=', $cuti->uuid) // Exclude current request
                ->whereYear('tanggal_mulai', $currentYear)
                ->whereIn('status', ['Pending', 'Disetujui Verifikator', 'Disetujui Pimpinan', 'Disetujui Atasan Pimpinan'])
                ->exists();

            if ($existingCutiBesar) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Anda sudah mengajukan Cuti Besar lain di tahun ini.');
            }
        }
        // --- End Cuti Besar Validation ---

        $validated['lama_cuti'] = $lamaCuti;

        // Handle document upload
        if ($request->hasFile('dokumen')) {
            // Delete old file if exists
            if ($cuti->dokumen) {
                Storage::delete('public/dokumen/cuti/'.$cuti->dokumen);
            }

            $file = $request->file('dokumen');
            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/dokumen/cuti', $filename);
            $validated['dokumen'] = $filename;
        }

        $cuti->update($validated);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

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
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi', compact('cuti', 'balance'));
    }

    // verifikasiPimpinan method (around line 190)
    public function verifikasiPimpinan($uuid)
    {
        // Check if user has pimpinan role
        if (! auth()->user()->hasRole('pimpinan') && ! auth()->user()->hasRole('atasan-pimpinan') && ! auth()->user()->hasRole('super-admin')) {
            return redirect()->route('cuti.index')->with('error', 'Anda tidak memiliki izin untuk melakukan verifikasi pimpinan');
        }

        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();

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
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-pimpinan', compact('cuti', 'balance'));
    }

    // Verifikasi atasan pimpinan method (around line 230)
    public function verifikasiAtasanPimpinan($uuid)
    {
        // Check if user has atasan-pimpinan role
        if (! auth()->user()->hasRole('atasan-pimpinan')) {
            return redirect()->route('cuti.index')->with('error', 'Anda tidak memiliki izin untuk melakukan verifikasi atasan pimpinan');
        }

        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();

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
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-atasan-pimpinan', compact('cuti', 'balance'));
    }

    // Proses verifikasi method (around line 270)
    public function prosesVerifikasi(Request $request, $uuid)
    {
        $validated = $request->validate([
            'status_verifikator' => 'required|in:Disetujui,Ditolak',
            'catatan_verifikator' => 'nullable|string',
        ]);

        $user = Auth::user();
        $verifikator = Pegawai::where('nip', $user->nip)->first();
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

        if (! $verifikator) {
            return redirect()->route('cuti.index')->with('error', 'Data verifikator tidak ditemukan');
        }

        // Update status based on verifikator decision
        $newStatus = $validated['status_verifikator'] === 'Disetujui' ? 'Disetujui Verifikator' : 'Ditolak Verifikator';

        $cuti->update([
            'status' => $newStatus,
            'status_verifikator' => $validated['status_verifikator'],
            'catatan_verifikator' => $validated['catatan_verifikator'],
            'verifikator_uuid' => $verifikator->uuid,
            'tanggal_verifikasi' => now(),
        ]);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi');
    }

    // Proses verifikasi pimpinan method (around line 300)
    public function prosesVerifikasiPimpinan(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

        // Only allow pimpinan verification if already approved by verifikator
        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        $validated = $request->validate([
            'status_pimpinan' => 'required|in:Disetujui,Ditolak',
            'catatan_pimpinan' => 'nullable|string',
        ]);

        $user = Auth::user();
        $pimpinan = Pegawai::where('nip', $user->nip)->first();

        if (! $pimpinan) {
            return redirect()->route('cuti.index')->with('error', 'Data pimpinan tidak ditemukan');
        }

        // Check if the current pimpinan is the assigned pimpinan for this request
        if ($pimpinan->uuid !== $cuti->pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        // Update status based on pimpinan decision
        $newStatus = $validated['status_pimpinan'] === 'Disetujui' ? 'Disetujui Pimpinan' : 'Ditolak Pimpinan';

        $cuti->update([
            'status' => $newStatus,
            'status_pimpinan' => $validated['status_pimpinan'],
            'catatan_pimpinan' => $validated['catatan_pimpinan'],
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_verifikasi_pimpinan' => now(),
        ]);

        // In the prosesVerifikasiPimpinan method, modify the part where we update the balance

        // If this is an approved annual leave, update the balance
        if ($newStatus === 'Disetujui Pimpinan' && $cuti->jenis_cuti === 'Cuti Tahunan') {
            $year = date('Y', strtotime($cuti->tanggal_mulai));
            $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $year);

            // Count only workdays (excluding weekends)
            $startDate = new \DateTime($cuti->tanggal_mulai);
            $endDate = new \DateTime($cuti->tanggal_selesai);
            $workdays = 0;

            // Clone the start date to avoid modifying it
            $currentDate = clone $startDate;

            // Loop through each day and count only workdays
            while ($currentDate <= $endDate) {
                $dayOfWeek = $currentDate->format('N'); // 1 (Monday) to 7 (Sunday)

                // Check if it's a workday (Monday to Friday)
                if ($dayOfWeek <= 5) {
                    $workdays++;
                }

                // Move to the next day
                $currentDate->modify('+1 day');
            }

            // Update the balance with only workdays
            $balance->used_days += $workdays;
            $balance->save();
        }

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi oleh pimpinan');
    }

    // Proses verifikasi atasan pimpinan method (around line 340)
    public function prosesVerifikasiAtasanPimpinan(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

        // Only allow atasan pimpinan verification if status is appropriate
        if ($cuti->status !== 'Disetujui Pimpinan') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh pimpinan terlebih dahulu');
        }

        $validated = $request->validate([
            'status_atasan_pimpinan' => 'required|in:Disetujui,Ditolak',
            'catatan_atasan_pimpinan' => 'nullable|string',
        ]);

        $user = Auth::user();
        $atasanPimpinan = Pegawai::where('nip', $user->nip)->first();

        if (! $atasanPimpinan) {
            return redirect()->route('cuti.index')->with('error', 'Data atasan pimpinan tidak ditemukan');
        }

        // Check if the current atasan pimpinan is the assigned atasan pimpinan for this request
        if ($atasanPimpinan->uuid !== $cuti->atasan_pimpinan_uuid) {
            return redirect()->route('cuti.index')->with('error', 'Anda bukan atasan pimpinan yang ditunjuk untuk menyetujui permohonan cuti ini');
        }

        // Update status based on atasan pimpinan decision
        $newStatus = $validated['status_atasan_pimpinan'] === 'Disetujui' ? 'Disetujui Atasan Pimpinan' : 'Ditolak Atasan Pimpinan';

        $cuti->update([
            'status' => $newStatus,
            'status_atasan_pimpinan' => $validated['status_atasan_pimpinan'],
            'catatan_atasan_pimpinan' => $validated['catatan_atasan_pimpinan'],
            'atasan_pimpinan_uuid' => $atasanPimpinan->uuid,
            'tanggal_verifikasi_atasan_pimpinan' => now(),
        ]);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi oleh atasan pimpinan');
    }

    // Add a method to display leave balance
    public function showBalance()
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('cuti')->with('error', 'Data pegawai tidak ditemukan');
        }

        $currentYear = date('Y');
        $balance = $this->ensureCutiBalance($pegawai->uuid, $currentYear);

        return view('cuti.balance', compact('balance', 'pegawai'));
    }

    // Update jumlah cuti pegawai method (around line 400)
    public function updateBalance()
    {
        $user = auth()->user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
        }

        $currentYear = date('Y');

        // Make sure we're using the fully qualified class name or the imported class
        CutiBalance::checkAndUpdateBalance($pegawai->uuid, $currentYear);

        return redirect()->route('cuti.index')->with('success', 'Saldo cuti berhasil diperbarui');
    }

    // Update semua jumlah cuti pegawai method (around line 410)
    public function updateAllBalances()
    {
        // Check if user has admin permissions
        if (! auth()->user()->can('update cuti')) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini');
        }

        $pegawai = Pegawai::all();
        $currentYear = date('Y');

        $uuids = $pegawai->pluck('uuid')->toArray();
        CutiBalance::bulkCheckAndUpdateBalance($uuids, $currentYear);
        $count = count($uuids);

        return redirect()->route('cuti.index')->with('success', "Saldo cuti untuk $count pegawai berhasil diperbarui");
    }

    // Add this method to generate PDF
    public function generatePdf($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan', 'atasanPimpinan'])->where('uuid', $uuid)->firstOrFail();

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
