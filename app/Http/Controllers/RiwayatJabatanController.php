<?php

namespace App\Http\Controllers;

use App\Models\RiwayatJabatan;
use App\Models\Pegawai;
use App\Models\Jabatan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RiwayatJabatanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view riwayat jabatan', ['only' => ['index']]);
        $this->middleware('permission:create riwayat jabatan', ['only' => ['create','store']]);
        $this->middleware('permission:update riwayat jabatan', ['only' => ['update','edit']]);
        $this->middleware('permission:delete riwayat jabatan', ['only' => ['destroy']]);
    }

    public function index($uuid)
    {
        $riwayatJabatan = RiwayatJabatan::with(['pegawai', 'jabatan'])->where('pegawai_uuid', $uuid)->get();

        $pegawai = Pegawai::where('uuid', $uuid)->first();
        return view('riwayat_jabatan.index', compact('riwayatJabatan', 'pegawai'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        $jabatan = Jabatan::all()->sortBy('parent_uuid');
        return view('riwayat_jabatan.create', compact('pegawai', 'jabatan'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'jabatan_uuid' => 'required|exists:jabatan,uuid',
            'satuan_kerja' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            'keterangan' => 'nullable|string',
        ]);

        $validated['uuid'] = Str::uuid();

        RiwayatJabatan::create($validated);

        return redirect()->to('riwayat_jabatan/'.$validated['pegawai_uuid'])
            ->with('success', 'Riwayat Jabatan berhasil ditambahkan.');

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RiwayatJabatan $riwayatJabatan)
    {
        $pegawai = Pegawai::where('uuid', $riwayatJabatan->pegawai_uuid)->first();
        $jabatan = Jabatan::all();
        return view('riwayat_jabatan.edit', compact('riwayatJabatan', 'pegawai', 'jabatan'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RiwayatJabatan $riwayatJabatan)
    {
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'jabatan_uuid' => 'required|exists:jabatan,uuid',
            'satuan_kerja' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'nullable|date|after:tanggal_mulai',
            'keterangan' => 'nullable|string',
        ]);

        $riwayatJabatan->update($validated);

        return redirect()->route('riwayat_jabatan.index')->with('success', 'Riwayat Jabatan berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RiwayatJabatan $riwayatJabatan)
    {
        $riwayatJabatan->delete();

        return redirect()->route('riwayat_jabatan.index')->with('success', 'Riwayat Jabatan berhasil dihapus.');
    }
}
