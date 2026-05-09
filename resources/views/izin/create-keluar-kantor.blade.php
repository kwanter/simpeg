<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Izin Keluar Kantor / Pulang Cepat</h2>
    </x-slot>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Ajukan Izin Keluar Kantor / Pulang Cepat
                            <a href="{{ route('izin.index') }}" class="btn btn-secondary float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Lampiran II</strong> PERMA No. 7 Tahun 2016 — Izin hanya berlaku untuk hari ini.
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('izin.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="pegawai_uuid" value="{{ $pegawai->uuid }}">
                            <input type="hidden" name="jenis_izin" value="{{ old('jenis_izin', $jenisIzin) }}">
                            <input type="hidden" name="tanggal_mulai" value="{{ now()->toDateString() }}">
                            <input type="hidden" name="tanggal_selesai" value="{{ now()->toDateString() }}">

                            <div class="mb-3">
                                <label class="form-label">Jenis Izin</label>
                                <input type="text" class="form-control" value="{{ $jenisIzin }}" disabled>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tanggal</label>
                                <input type="text" class="form-control" value="{{ \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y') }}" disabled>
                                <small class="text-muted">Izin keluar kantor hanya dapat diajukan untuk hari ini.</small>
                            </div>

                            <div class="mb-3">
                                <label for="atasan_pimpinan_uuid" class="form-label">Atasan Pimpinan (Pemberi Izin)</label>
                                <select name="atasan_pimpinan_uuid" id="atasan_pimpinan_uuid" class="form-select @error('atasan_pimpinan_uuid') is-invalid @enderror" required onchange="document.getElementById('pimpinan_uuid').value = this.value">
                                    <option value="">Pilih Atasan Pimpinan</option>
                                    @foreach($atasanList as $atasan)
                                        <option value="{{ $atasan->atasan_pimpinan_uuid }}" {{ old('atasan_pimpinan_uuid') == $atasan->atasan_pimpinan_uuid ? 'selected' : '' }}>{{ $atasan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('atasan_pimpinan_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Single-level approval: hanya atasan langsung.</small>
                            </div>

                            {{-- Hidden pimpinan_uuid for Keluar Kantor — use same as atasan --}}
                            <input type="hidden" name="pimpinan_uuid" id="pimpinan_uuid" value="{{ old('pimpinan_uuid') }}">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="jam_mulai" class="form-label">Jam Keluar <span class="text-danger">*</span></label>
                                    <input type="time" name="jam_mulai" id="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror" value="{{ old('jam_mulai') }}" required>
                                    @error('jam_mulai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="jam_selesai" class="form-label">Jam Kembali / Pulang <span class="text-danger">*</span></label>
                                    <input type="time" name="jam_selesai" id="jam_selesai" class="form-control @error('jam_selesai') is-invalid @enderror" value="{{ old('jam_selesai') }}" required>
                                    @error('jam_selesai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="alasan" class="form-label">Alasan / Kepentingan <span class="text-danger">*</span></label>
                                <textarea name="alasan" id="alasan" class="form-control @error('alasan') is-invalid @enderror" rows="3" required>{{ old('alasan') }}</textarea>
                                @error('alasan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="dokumen" class="form-label">Dokumen Pendukung (opsional, PDF/JPG/PNG, max 2MB)</label>
                                <input type="file" name="dokumen" id="dokumen" class="form-control @error('dokumen') is-invalid @enderror">
                                @error('dokumen')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Ajukan Izin</button>
                                <a href="{{ route('izin.index') }}" class="btn btn-secondary ms-2">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
