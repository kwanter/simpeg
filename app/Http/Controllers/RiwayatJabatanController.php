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
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        $riwayatJabatan = RiwayatJabatan::with(['jabatan'])
            ->where('pegawai_uuid', $uuid)
            ->orderBy('tanggal_mulai', 'desc')
            ->paginate(10);

        return view('riwayat_jabatan.index', compact('riwayatJabatan', 'pegawai'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        $jabatans = Jabatan::all()->sortBy('parent_uuid');  // Changed $jabatan to $jabatans
        return view('riwayat_jabatan.create', compact('pegawai', 'jabatans'));  // Changed 'jabatan' to 'jabatans'
    }
     /**
     * Show the form for editing the specified resource.
     */
    public function edit($riwayatJabatanId)
    {
        $riwayatJabatan = RiwayatJabatan::where('uuid', $riwayatJabatanId)->firstOrFail();
        $pegawai = Pegawai::where('uuid', $riwayatJabatan->pegawai_uuid)->first();
        $jabatans = Jabatan::all()->sortBy('parent_uuid');
        return view('riwayat_jabatan.edit', compact('riwayatJabatan', 'pegawai', 'jabatans'));
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
            'keterangan' => 'nullable|string',
        ]);

        $validated['uuid'] = Str::uuid();

        try {
            RiwayatJabatan::create($validated);

            return redirect()->route('riwayat_jabatan.index', $validated['pegawai_uuid'])
                ->with('success', 'Riwayat Jabatan berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menambahkan Riwayat Jabatan.')
                ->withInput();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $riwayatJabatanId)
    {
        $riwayatJabatan = RiwayatJabatan::where('uuid', $riwayatJabatanId)->firstOrFail();
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'jabatan_uuid' => 'required|exists:jabatan,uuid',
            'satuan_kerja' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        $riwayatJabatan->update($validated);

        return redirect()->to('riwayat_jabatan/'.$riwayatJabatan->pegawai_uuid)
            ->with('success', 'Riwayat Jabatan berhasil diperbarui.');
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
