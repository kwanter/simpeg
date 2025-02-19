<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;  // Add this line

class PegawaiController extends Controller
{
    public function __construct()
    {
        $this->middleware('throttle:60,1'); // 60 requests per minute
        $this->middleware('auth');
        // Remove the csrf middleware since it's already included in 'web' middleware group
        $this->middleware('permission:view pegawai', ['only' => ['index']]);
        $this->middleware('permission:create pegawai', ['only' => ['create','store']]);
        $this->middleware('permission:update pegawai', ['only' => ['update','edit']]);
        $this->middleware('permission:delete pegawai', ['only' => ['destroy']]);
        $this->middleware('permission:detail pegawai', ['only' => ['detail']]);
    }

    public function index()
    {
        $pegawai = Pegawai::select([
            'uuid',
            'nip',
            'nama',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'agama',
            'status_perkawinan',
            'alamat',
            'no_hp',
            'status_pegawai',
            'foto'
        ])
        ->orderBy('nama', 'asc')  // Optional: sort by name
        ->paginate(15);

        // Map status pegawai to more readable format if needed
        $pegawai->through(function ($item) {
            $statusMap = [
                'PNS' => 'PNS',
                'CPNS' => 'CPNS',
                'PPPK' => 'PPPK',
                'PPNPN' => 'PPNPN',
                'Hakim' => 'Hakim'
            ];

            $item->status_display = $statusMap[$item->status_pegawai] ?? $item->status_pegawai;
            return $item;
        });

        return view('pegawai.index', ['pegawai' => $pegawai]);
    }

    public function create()
    {
        return view('pegawai.create');
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nip' => 'required|string|max:255|unique:pegawai,nip',
                'nama' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'tempat_lahir' => 'required|string|max:255',
                'tanggal_lahir' => 'required|date|before:today',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'agama' => 'required|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
                'status_perkawinan' => 'required|in:Kawin,Belum Kawin,Duda,Janda',
                'alamat' => 'required|string|max:500',
                'no_hp' => 'required|string|max:20|regex:/^[0-9]+$/',
                'status_pegawai' => 'required|in:CPNS,Hakim,PNS,PPPK,PPNPN',
            ]);

            $request->merge($validatedData);
            $pegawai = Pegawai::create($request->except('foto'));
            if ($request->hasFile('foto')) {
                $this->handleFotoUpload($request, $pegawai);
            }
            if ($pegawai) {
                return redirect()->route('pegawai.index')->with('success', 'Data Pegawai berhasil ditambahkan');
            } else {
                return redirect()->route('pegawai.index')->with('error', 'Data Pegawai gagal ditambahkan');
            }
            Log::info('Pegawai created successfully', ['id' => $pegawai->id]);
        } catch (\Exception $e) {
            Log::error('Error creating pegawai', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to create pegawai');
        }
    }

    public function edit($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        return view('pegawai.edit', ['pegawai' => $pegawai]);
    }

    public function update(Request $request, $uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->firstOrFail();
        $validatedData = $request->validate([
            'nip' => 'required|string|max:255|unique:pegawai,nip,'.$pegawai->uuid.',uuid',  // Changed from id to uuid
            'nama' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|in:Islam,Kristen,Katolik,Hindu,Buddha,Konghucu',
            'status_perkawinan' => 'required|in:Kawin,Belum Kawin,Duda,Janda',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_pegawai' => 'required|in:CPNS,Hakim,PNS,PPPK,PPNPN',
        ]);

        $request->merge($validatedData);
        $pegawai->update($request->except('foto'));
        if ($request->hasFile('foto')) {
            $this->handleFotoUpload($request, $pegawai);
        }
        if ($pegawai) {
            return redirect()->route('pegawai.index')->with('success', 'Data Pegawai berhasil diubah');
        } else {
            return redirect()->route('pegawai.index')->with('error', 'Data Pegawai gagal diubah');
        }
    }

    public function destroy($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        if (!$pegawai) {
            return redirect()->route('pegawai.index')->with('error', 'Data Pegawai tidak ditemukan');
        }
        if ($pegawai->delete()) {
            return redirect()->route('pegawai.index')->with('success', 'Data Pegawai berhasil dihapus');
        }
        return redirect()->route('pegawai.index')->with('error', 'Data Pegawai gagal dihapus');
    }

    public function detail($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        if (!$pegawai) {
            return redirect()->route('pegawai.index')->with('error', 'Data Pegawai tidak ditemukan');
        }
        $user = User::where('nip', $pegawai->nip)->first();
        if (!$user) {
            return redirect()->route('pegawai.index')->with('error', 'Data Pegawai Belum Dihubungkan Dengan Akun');
        }
        return view('pegawai.detail', ['pegawai' => $pegawai, 'user' => $user->email]);
    }

    private function handleFotoUpload(Request $request, Pegawai $pegawai)
    {
        $request->validate([
            'foto' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
        ]);

        // Generate unique filename
        $filename = Str::uuid() . '.' . $request->file('foto')->getClientOriginalExtension();

        // Store using Storage facade
        $path = $request->file('foto')->storeAs('public/pic/pegawai', $filename);

        // Delete old file if exists
        if ($pegawai->foto) {
            Storage::delete('public/pic/pegawai/' . $pegawai->foto);
        }

        // Update pegawai record
        $pegawai->foto = $filename;
        $pegawai->save();
    }
}
