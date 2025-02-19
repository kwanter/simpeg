<?php

namespace App\Http\Controllers;

use App\Models\RiwayatPangkat;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RiwayatPangkatController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view riwayat pangkat', ['only' => ['index']]);
        $this->middleware('permission:create riwayat pangkat', ['only' => ['create','store']]);
        $this->middleware('permission:update riwayat pangkat', ['only' => ['update','edit']]);
        $this->middleware('permission:delete riwayat pangkat', ['only' => ['destroy']]);
    }

    public function index($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        $riwayatPangkat = RiwayatPangkat::where('pegawai_uuid', $uuid)
            ->orderBy('tmt', 'desc')
            ->paginate(10)
            ->through(function ($item) use ($pegawai) {
                if ($pegawai->status_pegawai == 'PNS' || $pegawai->status_pegawai == 'CPNS' || $pegawai->status_pegawai == 'Hakim') {
                    // Map pangkat names for PNS/CPNS/Hakim
                    $pangkatNames = [
                        'I/a' => 'Juru Muda',
                        'I/b' => 'Juru Muda Tingkat I',
                        'I/c' => 'Juru',
                        'I/d' => 'Juru Tingkat I',
                        'II/a' => 'Pengatur Muda',
                        'II/b' => 'Pengatur Muda Tingkat I',
                        'II/c' => 'Pengatur',
                        'II/d' => 'Pengatur Tingkat I',
                        'III/a' => 'Penata Muda',
                        'III/b' => 'Penata Muda Tingkat I',
                        'III/c' => 'Penata',
                        'III/d' => 'Penata Tingkat I',
                        'IV/a' => 'Pembina',
                        'IV/b' => 'Pembina Tingkat I',
                        'IV/c' => 'Pembina Utama Muda',
                        'IV/d' => 'Pembina Utama Madya',
                        'IV/e' => 'Pembina Utama'
                    ];
                    $item->pangkat_display = $pangkatNames[$item->pangkat_golongan] ?? '';
                    $item->golongan_display = $item->pangkat_golongan;
                } elseif ($pegawai->status_pegawai == 'PPPK') {
                    $romanNumerals = [
                        '1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', '5' => 'V',
                        '6' => 'VI', '7' => 'VII', '8' => 'VIII', '9' => 'IX', '10' => 'X',
                        '11' => 'XI', '12' => 'XII', '13' => 'XIII', '14' => 'XIV', '15' => 'XV',
                        '16' => 'XVI', '17' => 'XVII'
                    ];
                    $item->pangkat_display = 'PPPK';
                    $item->golongan_display = 'Golongan ' . ($romanNumerals[$item->pangkat_golongan] ?? $item->pangkat_golongan);
                } else {
                    $item->pangkat_display = 'PPNPN';
                    $item->golongan_display = '-';
                }
                return $item;
            });

        return view('riwayat_pangkat.index', compact('riwayatPangkat', 'pegawai'));
    }

    public function create($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        return view('riwayat_pangkat.create', compact('pegawai'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'pangkat_golongan' => 'required|string',
            'tmt' => 'required|date',
            'nomor_sk' => 'required|string',
            'tanggal_sk' => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        $validated['uuid'] = Str::uuid();

        try {
            RiwayatPangkat::create($validated);

            return redirect()->route('riwayat_pangkat.index', $validated['pegawai_uuid'])
                ->with('success', 'Riwayat Pangkat berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menambahkan Riwayat Pangkat.')
                ->withInput();
        }
    }

    public function edit($riwayatPangkatId)
    {
        $riwayatPangkat = RiwayatPangkat::where('uuid', $riwayatPangkatId)->firstOrFail();
        $pegawai = Pegawai::where('uuid', $riwayatPangkat->pegawai_uuid)->first();
        return view('riwayat_pangkat.edit', compact('riwayatPangkat', 'pegawai'));
    }

    public function update(Request $request, $riwayatPangkatId)
    {
        $riwayatPangkat = RiwayatPangkat::where('uuid', $riwayatPangkatId)->firstOrFail();
        $validated = $request->validate([
            'pegawai_uuid' => 'required|exists:pegawai,uuid',
            'pangkat_golongan' => 'required|string',
            'tmt' => 'required|date',
            'nomor_sk' => 'required|string',
            'tanggal_sk' => 'required|date',
            'keterangan' => 'nullable|string',
        ]);

        $riwayatPangkat->update($validated);

        return redirect()->to('riwayat_pangkat/'.$riwayatPangkat->pegawai_uuid)
            ->with('success', 'Riwayat Pangkat berhasil diperbarui.');
    }

    public function destroy($riwayatPangkatId)
    {
        $riwayatPangkat = RiwayatPangkat::where('uuid', $riwayatPangkatId)->firstOrFail();
        $pegawai_uuid = $riwayatPangkat->pegawai_uuid;
        $riwayatPangkat->delete();

        return redirect()->to('riwayat_pangkat/'.$pegawai_uuid)
            ->with('success', 'Riwayat Pangkat berhasil dihapus.');
    }
}
