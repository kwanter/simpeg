<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Models\HariLibur;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class IzinController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        // Check if the user has any of these roles
        if (auth()->user()->hasRole(['super-admin', 'admin'])) {
            // Admin can see all izin
            $izinList = Izin::with('pegawai')->latest()->paginate(10);
        } elseif (auth()->user()->hasRole('atasan-pimpinan')) {
            // Atasan can see izin where they are assigned as atasan_pimpinan
            $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();
            if ($pegawai) {
                $izinList = Izin::with('pegawai')
                    ->where('atasan_pimpinan_uuid', $pegawai->uuid)
                    ->latest()
                    ->paginate(10);
            } else {
                $izinList = collect(); // Empty collection if pegawai not found
            }
        } elseif (auth()->user()->hasRole('pimpinan')) {
            // Pimpinan can see izin where they are assigned as pimpinan
            $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();
            if ($pegawai) {
                $izinList = Izin::with('pegawai')
                    ->where('pimpinan_uuid', $pegawai->uuid)
                    ->latest()
                    ->paginate(10);
            } else {
                $izinList = collect(); // Empty collection if pegawai not found
            }
        } else {
            // Regular users can only see their own izin
            $pegawai = Pegawai::where('nip', auth()->user()->nip)->first();
            if ($pegawai) {
                $izinList = Izin::with('pegawai')
                    ->where('pegawai_uuid', $pegawai->uuid)
                    ->latest()
                    ->paginate(10);
            } else {
                $izinList = collect(); // Empty collection if pegawai not found
            }
        }

        return view('izin.index', compact('izinList'));
    }

    public function create()
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (!$pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        $jenisIzin = [
            'Izin Sakit',
            'Izin Keperluan Keluarga',
            'Izin Keperluan Pribadi',
            'Izin Dinas Luar',
            'Izin Setengah Hari',
            'Izin Terlambat',
            'Izin Pulang Cepat',
            'Izin Lainnya'
        ];

        // Get list of pimpinan and atasan for dropdown
        $pimpinanList = User::role('pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as pimpinan_uuid', 'pegawai.nama')
            ->get();

        $atasanList = User::role('atasan-pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as atasan_pimpinan_uuid', 'pegawai.nama')
            ->get();

        return view('izin.create', compact('pegawai', 'jenisIzin', 'pimpinanList', 'atasanList'));
    }

    public function edit($uuid)
    {
        $user = Auth::user();
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();

        // Only allow editing if the izin belongs to the user or if user is admin
        if (!$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            $pegawai = Pegawai::where('nip', $user->nip)->first();
            if (!$pegawai || $pegawai->uuid !== $izin->pegawai_uuid) {
                return redirect()->route('izin.index')->with('error', 'Anda tidak memiliki akses untuk mengedit pengajuan izin ini');
            }
        }

        // Allow editing if it's an admin or if the izin is approved by atasan but needs no_surat_izin
        $allowEdit = $user->hasRole('super-admin') || $user->hasRole('admin') ||
                     ($izin->verifikasi_atasan == 'Disetujui' && empty($izin->no_surat_izin)) ||
                     ($izin->verifikasi_atasan == 'Belum Diverifikasi' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi');

        if (!$allowEdit) {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat diedit');
        }

        $jenisIzin = [
            'Izin Sakit',
            'Izin Keperluan Keluarga',
            'Izin Keperluan Pribadi',
            'Izin Dinas Luar',
            'Izin Setengah Hari',
            'Izin Terlambat',
            'Izin Pulang Cepat',
            'Izin Lainnya'
        ];

        // Get list of pimpinan and atasan for dropdown
        $pimpinanList = User::role('pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as pimpinan_uuid', 'pegawai.nama')
            ->get();

        $atasanList = User::role('atasan-pimpinan')
            ->join('pegawai', 'users.nip', '=', 'pegawai.nip')
            ->select('pegawai.uuid as atasan_pimpinan_uuid', 'pegawai.nama')
            ->get();

        return view('izin.edit', compact('izin', 'jenisIzin', 'pimpinanList', 'atasanList'));
    }

    // In the store method, remove no_surat_izin from validation and don't set it initially
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'jenis_izin' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ]);

        // Generate UUID for the new record
        $validated['uuid'] = Str::uuid();
        $validated['status'] = 'Diajukan'; // Changed from 'Belum Diverifikasi' to 'Diajukan'
        $validated['verifikasi_atasan'] = 'Belum Diverifikasi';
        $validated['verifikasi_pimpinan'] = 'Belum Diverifikasi';

        // Calculate jumlah_hari
        $jumlahHari = $this->countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);
        $validated['jumlah_hari'] = $jumlahHari;

        // Handle file upload
        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $fileName = time(). '_'. $file->getClientOriginalName();
            $file->storeAs('public/dokumen/izin', $fileName);
            $validated['dokumen'] = $fileName;
        }

        Izin::create($validated);

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dibuat');
    }

    public function show($uuid)
    {
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        return view('izin.show', compact('izin'));
    }

    public function update(Request $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $user = Auth::user();

        // Check if this is just a no_surat_izin update for an approved izin
        $isNoSuratUpdate = $izin->verifikasi_atasan == 'Disetujui' &&
                       $request->has('no_surat_izin') &&
                       count($request->all()) <= 3; // csrf, method, and no_surat_izin

        // Don't allow full updating if already verified by pimpinan or atasan
        if (!$isNoSuratUpdate &&
            ($izin->verifikasi_pimpinan !== 'Belum Diverifikasi' ||
             ($izin->verifikasi_atasan !== 'Belum Diverifikasi' && !$user->hasRole('super-admin') && !$user->hasRole('admin')))) {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat diubah');
        }

        if ($isNoSuratUpdate) {
            // Only validate and update no_surat_izin
            $validated = $request->validate([
                'no_surat_izin' => 'required|string|unique:izin,no_surat_izin,'.$izin->id,
            ]);

            $izin->update(['no_surat_izin' => $validated['no_surat_izin']]);
            return redirect()->route('izin.index')->with('success', 'Nomor surat izin berhasil diperbarui');
        }

        // Full update for non-verified izin
        $validationRules = [
            'jenis_izin' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ];

        $validated = $request->validate($validationRules);

        // Handle file upload
        if ($request->hasFile('dokumen')) {
            // Delete old file if exists
            if ($izin->dokumen) {
                Storage::delete('public/dokumen/izin/' . $izin->dokumen);
            }

            $file = $request->file('dokumen');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/dokumen/izin', $fileName);
            $validated['dokumen'] = $fileName;
        }

        $izin->update($validated);

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();

        // Don't allow deletion if already verified
        if ($izin->verifikasi_atasan !== 'Belum Diverifikasi' || $izin->verifikasi_pimpinan !== 'Belum Diverifikasi') {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat dihapus');
        }

        // Delete file if exists
        if ($izin->dokumen) {
            Storage::delete('public/dokumen/izin/' . $izin->dokumen);
        }

        $izin->delete();

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dihapus');
    }

    public function verifikasiAtasan($uuid)
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (!$pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        $izin = Izin::where('uuid', $uuid)->firstOrFail();

        // Check if the current user is the assigned atasan or has admin role
        if (!$user->hasRole('super-admin') && !$user->hasRole('admin') &&
            (!$user->hasRole('atasan-pimpinan') || $pegawai->uuid !== $izin->atasan_pimpinan_uuid)) {
            return redirect()->route('izin.index')->with('error', 'Anda tidak memiliki akses untuk melakukan verifikasi');
        }

        return view('izin.verifikasi-atasan', compact('izin'));
    }

    public function verifikasiPimpinan($uuid)
    {
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (!$pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        $izin = Izin::where('uuid', $uuid)->firstOrFail();

        if ($izin->verifikasi_atasan !== 'Disetujui') {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin harus disetujui oleh atasan terlebih dahulu');
        }

        // Check if the current user is the assigned pimpinan or has admin role
        if (!$user->hasRole('super-admin') && !$user->hasRole('admin') &&
            (!$user->hasRole('pimpinan') || $pegawai->uuid !== $izin->pimpinan_uuid)) {
            return redirect()->route('izin.index')->with('error', 'Anda tidak memiliki akses untuk melakukan verifikasi');
        }

        return view('izin.verifikasi-pimpinan', compact('izin'));
    }

    public function prosesVerifikasiAtasan(Request $request, $uuid)
    {
        $user = Auth::user();

        if (!$user->hasRole('atasan-pimpinan') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return redirect()->route('izin.index')->with('error', 'Anda tidak memiliki akses untuk melakukan verifikasi');
        }

        $izin = Izin::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'verifikasi_atasan' => 'required|in:Disetujui,Ditolak',
            'catatan_atasan' => 'nullable|string',
        ]);

        $izin->verifikasi_atasan = $validated['verifikasi_atasan'];
        $izin->catatan_atasan = $validated['catatan_atasan'];
        $izin->tanggal_verifikasi_atasan = now();

        if ($validated['verifikasi_atasan'] === 'Disetujui') {
            $izin->status = 'Disetujui Atasan';
        } else {
            $izin->status = 'Ditolak Atasan';
        }

        $izin->save();

        return redirect()->route('izin.index')->with('success', 'Verifikasi atasan berhasil dilakukan');
    }

    public function prosesVerifikasiPimpinan(Request $request, $uuid)
    {
        $user = Auth::user();

        if (!$user->hasRole('pimpinan') && !$user->hasRole('super-admin') && !$user->hasRole('admin')) {
            return redirect()->route('izin.index')->with('error', 'Anda tidak memiliki akses untuk melakukan verifikasi');
        }

        $izin = Izin::where('uuid', $uuid)->firstOrFail();

        if ($izin->verifikasi_atasan !== 'Disetujui') {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin harus disetujui oleh atasan terlebih dahulu');
        }

        $validated = $request->validate([
            'verifikasi_pimpinan' => 'required|in:Disetujui,Ditolak',
            'catatan_pimpinan' => 'nullable|string',
        ]);

        $izin->verifikasi_pimpinan = $validated['verifikasi_pimpinan'];
        $izin->catatan_pimpinan = $validated['catatan_pimpinan'];
        $izin->tanggal_verifikasi_pimpinan = now();

        if ($validated['verifikasi_pimpinan'] === 'Disetujui') {
            $izin->status = 'Disetujui';
        } else {
            $izin->status = 'Ditolak';
        }

        $izin->save();

        return redirect()->route('izin.index')->with('success', 'Verifikasi pimpinan berhasil dilakukan');
    }

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
    // Add this method to generate PDF
    public function generatePdf($uuid)
    {
        $izin = Izin::with(['pegawai', 'atasan_pimpinan', 'pimpinan'])->where('uuid', $uuid)->firstOrFail();

        // Check if izin has been approved
        if ($izin->status !== 'Disetujui' && $izin->status !== 'Disetujui Atasan') {
            return redirect()->route('izin.show', $izin->uuid)
                ->with('error', 'Surat izin hanya dapat dicetak setelah disetujui');
        }

        // Check if no_surat_izin exists
        if (empty($izin->no_surat_izin)) {
            return redirect()->route('izin.show', $izin->uuid)
                ->with('error', 'Nomor surat izin belum diisi. Silakan isi nomor surat terlebih dahulu.');
        }

        $pdf = \PDF::loadView('izin.pdf', ['izin' => $izin]);

        // Generate filename
        $filename = 'Surat_Izin_' . $izin->pegawai->nama . '_' . $izin->no_surat_izin . '.pdf';

        return $pdf->download($filename);
    }
}
