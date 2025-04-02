<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\Pegawai;
use App\Models\CutiBalance;
use App\Models\HariLibur;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class CutiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');

        // Uncomment this line to enable role-based access
        //$this->middleware('role:super-admin|admin', ['except' => ['index', 'show', 'create', 'store']]);

        $this->middleware('permission:create cuti', ['only' => ['create','store']]);
        $this->middleware('permission:update cuti', ['only' => ['update','edit']]);
        $this->middleware('permission:delete cuti', ['only' => ['destroy']]);
        $this->middleware('permission:verifikasi cuti', ['only' => ['verifikasi', 'prosesVerifikasi']]);
        $this->middleware('role:pimpinan', ['only' => ['verifikasiPimpinan', 'prosesVerifikasiPimpinan']]);
        $this->middleware('role:atasan-pimpinan', ['only' => ['verifikasiAtasanPimpinan', 'prosesVerifikasiAtasanPimpinan']]);
    }

    public function index()
    {
        // If user has permission to verify or is pimpinan, show all leave requests
        if (auth()->user()->can('verifikasi cuti') || auth()->user()->can('pimpinan cuti')) {
            $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->latest()->paginate(10);
        } else {
            // Otherwise, show only the user's leave requests
            $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();
            if (!$pegawai) {
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

        if (!$pegawai) {
            return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
        }

        $jenisCuti = ['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Alasan Penting', 'Cuti Besar'];

        // Get current year leave balance
        $currentYear = date('Y');
        $balance = CutiBalance::where('pegawai_uuid', $pegawai->uuid)
            ->where('year', $currentYear)
            ->first();

        if (!$balance) {
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

        if (!$balance) {
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
                'carried_over' => $carriedOver
            ]);
        }

        return $balance;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'jenis_cuti' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string',
            'alamat_selama_cuti' => 'required|string',
            'no_hp_selama_cuti' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ]);

        // Calculate leave duration - count only workdays
        $lamaCuti = $this->countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);

        // Check annual leave limit if the type is "Cuti Tahunan"
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));

            // Ensure balance record exists
            $balance = $this->ensureCutiBalance($validated['pegawai_uuid'], $currentYear);

            // Calculate remaining days
            $remainingDays = $balance->total_days + $balance->carried_over - $balance->used_days;

            // Check if adding this leave would exceed the available balance
            if ($lamaCuti > $remainingDays) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Sisa cuti tahunan Anda tidak mencukupi. Sisa cuti: {$remainingDays} hari, permintaan: {$lamaCuti} hari.");
            }
        }

        $validated['uuid'] = Str::uuid();
        $validated['lama_cuti'] = $lamaCuti;
        $validated['status'] = 'Pending';

        // Handle document upload
        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
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

            if (!$balance) {
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.show', compact('cuti', 'balance'));
    }

    public function edit($uuid)
    {
        $cuti = Cuti::with('pegawai')->where('uuid', $uuid)->firstOrFail();

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

            if (!$balance) {
                $balance = CutiBalance::checkAndUpdateBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.edit', compact('cuti', 'jenisCuti', 'balance'));
    }

    public function update(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

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
        $lamaCuti = $this->countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);

        // Check annual leave limit if the type is "Cuti Tahunan"
        if ($validated['jenis_cuti'] === 'Cuti Tahunan') {
            $currentYear = date('Y', strtotime($validated['tanggal_mulai']));

            // Ensure balance record exists
            $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);

            // Calculate used days excluding this request
            $usedDaysExcludingThis = $balance->used_days;

            // If this was already a Cuti Tahunan request, subtract its days
            if ($cuti->jenis_cuti === 'Cuti Tahunan' &&
                date('Y', strtotime($cuti->tanggal_mulai)) == $currentYear) {
                $usedDaysExcludingThis -= $cuti->lama_cuti;
            }

            // Calculate remaining days
            $remainingDays = $balance->total_days + $balance->carried_over - $usedDaysExcludingThis;

            // Check if updating this leave would exceed the available balance
            if ($lamaCuti > $remainingDays) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', "Sisa cuti tahunan Anda tidak mencukupi. Sisa cuti: {$remainingDays} hari, permintaan: {$lamaCuti} hari.");
            }
        }

        $validated['lama_cuti'] = $lamaCuti;

        // Handle document upload
        if ($request->hasFile('dokumen')) {
            // Delete old file if exists
            if ($cuti->dokumen) {
                Storage::delete('public/dokumen/cuti/' . $cuti->dokumen);
            }

            $file = $request->file('dokumen');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
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
            Storage::delete('public/dokumen/cuti/' . $cuti->dokumen);
        }

        $cuti->delete();

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil dihapus');
    }

    //verifikasi method (around line 166)
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

            if (!$balance) {
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi', compact('cuti', 'balance'));
    }

    //verifikasiPimpinan method (around line 190)
    public function verifikasiPimpinan($uuid)
    {
        // Check if user has pimpinan role
        if (!auth()->user()->hasRole('pimpinan')) {
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

            if (!$balance) {
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-pimpinan', compact('cuti', 'balance'));
    }

    //Verifikasi atasan pimpinan method (around line 230)
    public function verifikasiAtasanPimpinan($uuid)
    {
        // Check if user has atasan-pimpinan role
        if (!auth()->user()->hasRole('atasan-pimpinan')) {
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

            if (!$balance) {
                $balance = $this->ensureCutiBalance($cuti->pegawai_uuid, $currentYear);
            }
        }

        return view('cuti.verifikasi-atasan-pimpinan', compact('cuti', 'balance'));
    }

    // Proses verifikasi method (around line 270)
    public function prosesVerifikasi(Request $request, $uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

        // Only allow verification if status is still Pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti ini sudah diverifikasi');
        }

        $validated = $request->validate([
            'status_verifikator' => 'required|in:Disetujui,Ditolak',
            'catatan_verifikator' => 'nullable|string',
        ]);

        $user = Auth::user();
        $verifikator = Pegawai::where('nip', $user->nip)->first();

        if (!$verifikator) {
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

        if (!$pimpinan) {
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

        if (!$atasanPimpinan) {
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

        if (!$pegawai) {
            return redirect()->route('cuti')->with('error', 'Data pegawai tidak ditemukan');
        }

        $currentYear = date('Y');
        $balance = $this->ensureCutiBalance($pegawai->uuid, $currentYear);

        return view('cuti.balance', compact('balance', 'pegawai'));
    }

    //method untuk menghitung jumlah hari kerja
    // Update the countWorkdays method to exclude holidays
    private function countWorkdays($startDate, $endDate)
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $workdays = 0;

        // Get all holidays and collective leave days between the start and end dates
        $holidays = HariLibur::whereBetween('tanggal', [$startDate, $endDate])
            ->pluck('tanggal')
            ->map(function($date) {
                return date('Y-m-d', strtotime($date));
            })
            ->toArray();

        $current = clone $start;
        while ($current <= $end) {
            $dayOfWeek = $current->format('N');
            $currentDateStr = $current->format('Y-m-d');

            // Check if it's a workday (Monday to Friday) and not a holiday
            if ($dayOfWeek <= 5 && !in_array($currentDateStr, $holidays)) {
                $workdays++;
            }

            $current->modify('+1 day');
        }

        return $workdays;
    }

    //Update jumlah cuti pegawai method (around line 400)
    public function updateBalance()
    {
        $user = auth()->user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (!$pegawai) {
            return redirect()->route('dashboard')->with('error', 'Data pegawai tidak ditemukan');
        }

        $currentYear = date('Y');

        // Make sure we're using the fully qualified class name or the imported class
        CutiBalance::checkAndUpdateBalance($pegawai->uuid, $currentYear);

        return redirect()->route('cuti.index')->with('success', 'Saldo cuti berhasil diperbarui');
    }

    //Update semua jumlah cuti pegawai method (around line 410)
    public function updateAllBalances()
    {
        // Check if user has admin permissions
        if (!auth()->user()->can('update cuti')) {
            return redirect()->route('dashboard')->with('error', 'Anda tidak memiliki izin untuk melakukan tindakan ini');
        }

        $pegawai = Pegawai::all();
        $currentYear = date('Y');
        $count = 0;

        foreach ($pegawai as $p) {
            CutiBalance::checkAndUpdateBalance($p->uuid, $currentYear);
            $count++;
        }

        return redirect()->route('cuti.index')->with('success', "Saldo cuti untuk $count pegawai berhasil diperbarui");
    }
}