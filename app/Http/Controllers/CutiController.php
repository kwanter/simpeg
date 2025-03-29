<?php

namespace App\Http\Controllers;

use App\Models\Cuti;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Spatie\Permission\Traits\HasRoles;

class CutiController extends Controller
{
    use HasRoles;

    public function __construct()
    {
        $this->middleware('auth');

        // Allow super-admin for all actions
        $this->middleware('role:super-admin', ['only' => ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy', 'verifikasi', 'prosesVerifikasi', 'verifikasiPimpinan', 'prosesVerifikasiPimpinan']]);

        // Regular permissions for other roles
        $this->middleware('permission:view cuti', ['only' => ['index']]);
        $this->middleware('permission:create cuti', ['only' => ['create','store']]);
        $this->middleware('permission:update cuti', ['only' => ['update','edit']]);
        $this->middleware('permission:delete cuti', ['only' => ['destroy']]);
        $this->middleware('permission:verifikasi cuti', ['only' => ['verifikasi', 'prosesVerifikasi']]);
        $this->middleware('permission:pimpinan cuti', ['only' => ['verifikasiPimpinan', 'prosesVerifikasiPimpinan']]);
    }

    public function index()
    {
        $user = Auth::user();

        // If user has permission to verify or is pimpinan, show all leave requests
        if ($user->can('verifikasi cuti') || $user->can('pimpinan cuti')) {
            $cuti = Cuti::with(['pegawai', 'verifikator', 'pimpinan'])->latest()->paginate(10);
        } else {
            // Otherwise, show only the user's leave requests
            $pegawai = Pegawai::where('nip', $user->nip)->first();
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

        $jenisCuti = [
            'Cuti Tahunan',
            'Cuti Besar',
            'Cuti Sakit',
            'Cuti Melahirkan',
            'Cuti Karena Alasan Penting',
            'Cuti di Luar Tanggungan Negara'
        ];

        return view('cuti.create', compact('pegawai', 'jenisCuti'));
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
        ]);

        // Calculate leave duration
        $tanggalMulai = new \DateTime($validated['tanggal_mulai']);
        $tanggalSelesai = new \DateTime($validated['tanggal_selesai']);
        $interval = $tanggalMulai->diff($tanggalSelesai);
        $lamaCuti = $interval->days + 1; // Including the start day

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
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();
        return view('cuti.show', compact('cuti'));
    }

    public function edit($uuid)
    {
        $cuti = Cuti::where('uuid', $uuid)->firstOrFail();

        // Only allow editing if status is still pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti yang sudah diverifikasi tidak dapat diubah');
        }

        $jenisCuti = [
            'Cuti Tahunan',
            'Cuti Besar',
            'Cuti Sakit',
            'Cuti Melahirkan',
            'Cuti Karena Alasan Penting',
            'Cuti di Luar Tanggungan Negara'
        ];

        return view('cuti.edit', compact('cuti', 'jenisCuti'));
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

        // Calculate leave duration
        $tanggalMulai = new \DateTime($validated['tanggal_mulai']);
        $tanggalSelesai = new \DateTime($validated['tanggal_selesai']);
        $interval = $tanggalMulai->diff($tanggalSelesai);
        $lamaCuti = $interval->days + 1; // Including the start day

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

    public function verifikasi($uuid)
    {
        $cuti = Cuti::with('pegawai')->where('uuid', $uuid)->firstOrFail();

        // Only allow verification if status is still Pending
        if ($cuti->status !== 'Pending') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti ini sudah diverifikasi');
        }

        return view('cuti.verifikasi', compact('cuti'));
    }

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

    public function verifikasiPimpinan($uuid)
    {
        $cuti = Cuti::with(['pegawai', 'verifikator'])->where('uuid', $uuid)->firstOrFail();

        // Only allow pimpinan verification if already approved by verifikator
        if ($cuti->status !== 'Disetujui Verifikator') {
            return redirect()->route('cuti.index')->with('error', 'Permohonan cuti harus disetujui oleh verifikator terlebih dahulu');
        }

        return view('cuti.verifikasi_pimpinan', compact('cuti'));
    }

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

        // Update status based on pimpinan decision
        $newStatus = $validated['status_pimpinan'] === 'Disetujui' ? 'Disetujui Pimpinan' : 'Ditolak Pimpinan';

        $cuti->update([
            'status' => $newStatus,
            'status_pimpinan' => $validated['status_pimpinan'],
            'catatan_pimpinan' => $validated['catatan_pimpinan'],
            'pimpinan_uuid' => $pimpinan->uuid,
            'tanggal_verifikasi_pimpinan' => now(),
        ]);

        return redirect()->route('cuti.index')->with('success', 'Permohonan cuti berhasil diverifikasi oleh pimpinan');
    }
}