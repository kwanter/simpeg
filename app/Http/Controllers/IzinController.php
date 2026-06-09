<?php

namespace App\Http\Controllers;

use App\Models\Izin;
use App\Models\Pegawai;
use App\Services\ApproverDirectoryService;
use App\Services\IzinQueryService;
use App\Services\WorkdayService;
use App\Support\IzinType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IzinController extends Controller
{
    public function __construct(
        private readonly ApproverDirectoryService $approvers,
        private readonly IzinQueryService $izinQuery,
    ) {
        $this->middleware('auth');
    }

    /**
     * Validasi khusus untuk Izin Keluar Kantor dan Izin Pulang Cepat.
     * Pasal 5 PERMA No. 7 Tahun 2016 — Lampiran II
     */
    private function validateIzinKeluarKantor(Request $request): array
    {
        $rules = [
            'tanggal_mulai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
            'tanggal_selesai' => ['required', 'date', 'date_equals:'.now()->toDateString()],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
        $messages = [
            'tanggal_mulai.date_equals' => 'Izin keluar kantor hanya dapat diajukan pada hari ini.',
            'tanggal_selesai.date_equals' => 'Izin keluar kantor hanya dapat diajukan pada hari ini.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ];

        return [$rules, $messages];
    }

    /**
     * Validasi khusus untuk Izin Tidak Masuk Kerja.
     * Pasal 8 PERMA No. 7 Tahun 2016 — Lampiran III — Maks 2 hari kerja
     */
    private function validateIzinTidakMasukKerja(Request $request): array
    {
        $rules = [
            'tanggal_mulai' => ['required', 'date', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
        $messages = [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
        ];

        return [$rules, $messages];
    }

    public function index()
    {
        $this->authorize('viewAny', Izin::class);
        $izinList = $this->izinQuery->forUser(Auth::user())->latest()->paginate(10);

        return view('izin.index', compact('izinList'));
    }

    public function create()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        return view('izin.create', $this->formData($pegawai));
    }

    public function edit($uuid)
    {
        $user = Auth::user();
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $izin);

        // Allow editing if it's an admin or if the izin is approved by atasan but needs no_surat_izin
        $allowEdit = $user->hasRole('super-admin') || $user->hasRole('admin') ||
                     ($izin->verifikasi_atasan == 'Disetujui' && empty($izin->no_surat_izin)) ||
                     ($izin->verifikasi_atasan == 'Belum Diverifikasi' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi');

        if (! $allowEdit) {
            return redirect()->route('izin.index')->with('error', 'Pengajuan izin yang sudah diverifikasi tidak dapat diedit');
        }

        return view('izin.edit', array_merge(
            $this->formData($izin->pegawai),
            ['izin' => $izin]
        ));
    }

    /**
     * Shared form data for create/edit and PERMA-specific create methods.
     */
    private function formData(Pegawai $pegawai): array
    {
        return [
            'pegawai' => $pegawai,
            'jenisIzin' => IzinType::all(),
            'pimpinanList' => $this->approvers->pimpinanList(),
            'atasanList' => $this->approvers->atasanList(),
        ];
    }

    // In the store method, remove no_surat_izin from validation and don't set it initially
    public function store(Request $request)
    {
        $this->authorize('create', Izin::class);
        $validated = $request->validate([
            'pegawai_uuid' => 'nullable|exists:pegawai,uuid',
            'jenis_izin' => ['required', 'string', 'in:'.implode(',', IzinType::all())],
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:2048',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ]);

        if (! Auth::user()->hasAnyRole(['super-admin', 'admin']) || empty($validated['pegawai_uuid'])) {
            $validated['pegawai_uuid'] = Pegawai::where('nip', Auth::user()->nip)->firstOrFail()->uuid;
        }

        // Generate UUID for the new record
        $validated['uuid'] = Str::uuid();
        $validated['status'] = 'Diajukan';
        $validated['verifikasi_atasan'] = 'Belum Diverifikasi';
        $validated['verifikasi_pimpinan'] = 'Belum Diverifikasi';

        // Calculate jumlah_hari
        $jumlahHari = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);
        $validated['jumlah_hari'] = $jumlahHari;

        // Jenis-specific validation for new izin types
        if (IzinType::isSingleLevel($validated['jenis_izin'])) {
            [$rules, $messages] = $this->validateIzinKeluarKantor($request);
            $request->validate($rules, $messages);
            // Same-day time-range, jumlah_hari = 0
            $validated['jumlah_hari'] = 0;
            $validated['tanggal_mulai'] = now()->toDateString();
            $validated['tanggal_selesai'] = now()->toDateString();
            // Single-level jenis — keep pimpinan_uuid value but it is unused in verification flow.
        } elseif ($validated['jenis_izin'] === IzinType::TIDAK_MASUK) {
            [$rules, $messages] = $this->validateIzinTidakMasukKerja($request);
            $request->validate($rules, $messages);
            // Maks 2 hari kerja (Pasal 8 ayat 4 PERMA No. 7 Tahun 2016)
            $workDays = WorkdayService::countWorkdays($validated['tanggal_mulai'], $validated['tanggal_selesai']);
            if ($workDays > IzinType::maxWorkdays(IzinType::TIDAK_MASUK)) {
                throw ValidationException::withMessages([
                    'tanggal_selesai' => ['Izin tidak masuk kerja maksimal 2 (dua) hari kerja.'],
                ]);
            }
        }

        // Handle file upload
        if ($request->hasFile('dokumen')) {
            $file = $request->file('dokumen');
            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/dokumen/izin', $fileName);
            $validated['dokumen'] = $fileName;
        }

        Izin::create($validated);

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dibuat');
    }

    public function show($uuid)
    {
        $izin = Izin::with('pegawai')->where('uuid', $uuid)->firstOrFail();
        $this->authorize('view', $izin);

        return view('izin.show', compact('izin'));
    }

    public function update(Request $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $izin);
        $user = Auth::user();

        // Check if this is just a no_surat_izin update for an approved izin
        $isNoSuratUpdate = $izin->verifikasi_atasan == 'Disetujui' &&
                       $request->has('no_surat_izin') &&
                       count($request->all()) <= 3; // csrf, method, and no_surat_izin

        // Don't allow full updating if already verified by pimpinan or atasan
        if (! $isNoSuratUpdate &&
            ($izin->verifikasi_pimpinan !== 'Belum Diverifikasi' ||
             ($izin->verifikasi_atasan !== 'Belum Diverifikasi' && ! $user->hasRole('super-admin') && ! $user->hasRole('admin')))) {
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
            'jenis_izin' => ['required', 'string', 'in:'.implode(',', IzinType::all())],
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'jam_mulai' => 'nullable|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i',
            'alasan' => 'required|string',
            'dokumen' => 'nullable|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:2048',
            'atasan_pimpinan_uuid' => 'required|exists:pegawai,uuid',
            'pimpinan_uuid' => 'required|exists:pegawai,uuid',
        ];

        $validated = $request->validate($validationRules);
        $validated['jumlah_hari'] = WorkdayService::countWorkdays(
            $validated['tanggal_mulai'],
            $validated['tanggal_selesai']
        );

        // Handle file upload
        if ($request->hasFile('dokumen')) {
            // Delete old file if exists
            if ($izin->dokumen) {
                Storage::delete('public/dokumen/izin/'.$izin->dokumen);
            }

            $file = $request->file('dokumen');
            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/dokumen/izin', $fileName);
            $validated['dokumen'] = $fileName;
        }

        $izin->update($validated);

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil diperbarui');
    }

    public function destroy($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('delete', $izin);

        // Delete file if exists
        if ($izin->dokumen) {
            Storage::delete('public/dokumen/izin/'.$izin->dokumen);
        }

        $izin->delete();

        return redirect()->route('izin.index')->with('success', 'Pengajuan izin berhasil dihapus');
    }

    public function verifikasiAtasan($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasan', $izin);

        return view('izin.verifikasi-atasan', compact('izin'));
    }

    public function verifikasiPimpinan($uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $izin);

        return view('izin.verifikasi-pimpinan', compact('izin'));
    }

    public function prosesVerifikasiAtasan(Request $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyAtasan', $izin);

        $validated = $request->validate([
            'verifikasi_atasan' => 'required|in:Disetujui,Ditolak',
            'catatan_atasan' => 'nullable|string',
        ]);

        $izin->verifikasi_atasan = $validated['verifikasi_atasan'];
        $izin->catatan_atasan = $validated['catatan_atasan'];
        $izin->tanggal_verifikasi_atasan = now();

        if ($validated['verifikasi_atasan'] === 'Disetujui') {
            // Single-level approval for Izin Keluar Kantor and Izin Pulang Cepat
            // Pasal 5 PERMA No. 7 Tahun 2016 — atasan langsung only
            $izin->status = IzinType::isSingleLevel($izin->jenis_izin)
                ? 'Disetujui'
                : 'Disetujui Atasan';
        } else {
            $izin->status = 'Ditolak Atasan';
        }

        $izin->save();

        return redirect()->route('izin.index')->with('success', 'Verifikasi atasan berhasil dilakukan');
    }

    public function prosesVerifikasiPimpinan(Request $request, $uuid)
    {
        $izin = Izin::where('uuid', $uuid)->firstOrFail();
        $this->authorize('verifyPimpinan', $izin);

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

    // Add this method to generate PDF
    public function generatePdf($uuid)
    {
        $izin = Izin::with(['pegawai', 'atasan_pimpinan', 'pimpinan'])->where('uuid', $uuid)->firstOrFail();
        $this->authorize('cetak', $izin);

        $template = IzinType::pdfTemplate($izin->jenis_izin);

        $pdf = \PDF::loadView($template, ['izin' => $izin]);

        // Generate filename
        $filename = 'Surat_Izin_'.$izin->pegawai->nama.'_'.($izin->no_surat_izin ?? $izin->uuid).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Dedicated create form for Izin Keluar Kantor (Lampiran II PERMA No. 7/2016).
     */
    public function createKeluarKantor()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        $data = $this->formData($pegawai);
        $data['jenisIzin'] = IzinType::KELUAR_KANTOR;

        return view('izin.create-keluar-kantor', $data);
    }

    /**
     * Dedicated create form for Izin Tidak Masuk Kerja (Lampiran III PERMA No. 7/2016).
     */
    public function createTidakMasuk()
    {
        $this->authorize('create', Izin::class);
        $user = Auth::user();
        $pegawai = Pegawai::where('nip', $user->nip)->first();

        if (! $pegawai) {
            return redirect()->route('izin.index')->with('error', 'Data pegawai tidak ditemukan');
        }

        return view('izin.create-tidak-masuk', $this->formData($pegawai));
    }

    /**
     * Index for Izin Keluar Kantor & Izin Pulang Cepat.
     */
    public function indexKeluarKantor()
    {
        $this->authorize('viewAny', Izin::class);

        $izins = $this->izinQuery
            ->forUser(Auth::user(), IzinType::keluarKantorGroup()->all())
            ->latest()
            ->paginate(10);

        return view('izin.index-keluar-kantor', compact('izins'));
    }

    /**
     * Index for Izin Tidak Masuk Kerja.
     */
    public function indexTidakMasuk()
    {
        $this->authorize('viewAny', Izin::class);

        $izins = $this->izinQuery
            ->forUser(Auth::user(), [IzinType::TIDAK_MASUK])
            ->latest()
            ->paginate(10);

        return view('izin.index-tidak-masuk', compact('izins'));
    }
}
