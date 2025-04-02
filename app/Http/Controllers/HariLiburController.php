<?php

namespace App\Http\Controllers;

use App\Models\HariLibur;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HariLiburController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:super-admin|admin', ['except' => ['index', 'show']]);
    }

    public function index()
    {
        $hariLibur = HariLibur::orderBy('tanggal')->paginate(10);
        return view('hari-libur.index', compact('hariLibur'));
    }

    public function create()
    {
        $jenisLibur = ['Libur Nasional', 'Cuti Bersama'];
        return view('hari-libur.create', compact('jenisLibur'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'hari_libur' => 'required|array',
            'hari_libur.*.tanggal' => 'required|date',
            'hari_libur.*.nama' => 'required|string|max:255',
            'hari_libur.*.jenis' => 'required|in:Libur Nasional,Cuti Bersama',
            'hari_libur.*.keterangan' => 'nullable|string',
        ]);

        $hariLiburData = $request->hari_libur;

        foreach ($hariLiburData as $data) {
            HariLibur::create([
                'uuid' => Str::uuid(),
                'tanggal' => $data['tanggal'],
                'nama' => $data['nama'],
                'jenis' => $data['jenis'],
                'keterangan' => $data['keterangan'] ?? null,
            ]);
        }

        return redirect()->route('hari-libur.index')->with('success', 'Data hari libur berhasil ditambahkan');
    }

    public function show($uuid)
    {
        $hariLibur = HariLibur::where('uuid', $uuid)->firstOrFail();
        return view('hari-libur.show', compact('hariLibur'));
    }

    public function edit($uuid)
    {
        $hariLibur = HariLibur::where('uuid', $uuid)->firstOrFail();
        $jenisLibur = ['Libur Nasional', 'Cuti Bersama'];
        return view('hari-libur.edit', compact('hariLibur', 'jenisLibur'));
    }

    public function update(Request $request, $uuid)
    {
        $hariLibur = HariLibur::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'nama' => 'required|string|max:255',
            'jenis' => 'required|in:Libur Nasional,Cuti Bersama',
            'keterangan' => 'nullable|string',
        ]);

        $hariLibur->update($validated);

        return redirect()->route('hari-libur.index')->with('success', 'Data hari libur berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $hariLibur = HariLibur::where('uuid', $uuid)->firstOrFail();
        $hariLibur->delete();

        return redirect()->route('hari-libur.index')->with('success', 'Data hari libur berhasil dihapus');
    }
}