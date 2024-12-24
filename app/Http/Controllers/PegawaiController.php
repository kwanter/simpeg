<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\User;
class PegawaiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view pegawai', ['only' => ['index']]);
        $this->middleware('permission:create pegawai', ['only' => ['create','store']]);
        $this->middleware('permission:update pegawai', ['only' => ['update','edit']]);
        $this->middleware('permission:delete pegawai', ['only' => ['destroy']]);
        $this->middleware('permission:detail pegawai', ['only' => ['detail']]);
    }

    public function index()
    {
        $pegawai = Pegawai::get();   
        return view('pegawai.index', ['pegawai' => $pegawai]);
    }

    public function create()
    {
        return view('pegawai.create');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nip' => 'required|string|max:255|unique:pegawai,nip',
            'nama' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string|max:255',
            'status_perkawinan' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_pegawai' => 'required|string|max:255',
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
            'nip' => 'required|string|max:255',
            'nama' => 'required|string|max:255',
            'tempat_lahir' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'agama' => 'required|string|max:255',
            'status_perkawinan' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_hp' => 'required|string|max:20',
            'status_pegawai' => 'required|string|max:255',
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
        $pegawai = Pegawai::where('uuid', $uuid)->delete();
        if ($pegawai) {
            return redirect()->route('pegawai.index')->with('success', 'Data Pegawai berhasil dihapus');
        } else {
            return redirect()->route('pegawai.index')->with('error', 'Data Pegawai gagal dihapus');
        }
    }

    public function detail($uuid)
    {
        $pegawai = Pegawai::where('uuid', $uuid)->first();
        if ($pegawai) {
            $user = User::where('nip', $pegawai->nip)->first();
            if (!$user) {
                return redirect()->route('pegawai.index')->with('error', 'Data Pegawai Belum Dihubungkan Dengan Akun');
            }
            return view('pegawai.detail', ['pegawai' => $pegawai, 'user' => $user->email]);
        }
    }

    private function handleFotoUpload(Request $request, Pegawai $pegawai)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,gif|max:20480',
        ]);
        // Check file size
        if ($request->file('foto')->getSize() > 20480 * 1024) { // 20480 KB = 20 MB
            return back()->withErrors(['foto' => 'Ukuran file tidak boleh lebih dari 20 MB.']);
        }

        // Check file type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        if (!in_array($request->file('foto')->getMimeType(), $allowedMimeTypes)) {
            return back()->withErrors(['foto' => 'File harus berupa gambar JPG, JPEG, PNG, atau GIF.']);
        }

        // Get the original filename
        $originalFilename = $request->file('foto')->getClientOriginalName();

        // Shrink file size
        $image = Image::make($request->file('foto'));
        $image->resize(800, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $image->encode('jpg', 80);

        if ($pegawai->foto) {
            Storage::delete("app/public/pic/pegawai/{$pegawai->foto}");
        }

        $filename = time() . '_' . $originalFilename;
        $image->save(storage_path("app/public/pic/pegawai/{$filename}"));
        
        $pegawai->foto = $filename;
        $pegawai->save();
    }
}
