<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Izin Tidak Masuk Kerja</h2>
    </x-slot>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Ajukan Izin Tidak Masuk Kerja
                            <a href="{{ route('izin.index') }}" class="btn btn-secondary float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <strong>Lampiran III</strong> PERMA No. 7 Tahun 2016 Pasal 8 — Izin tidak masuk kerja <strong>maksimal 2 (dua) hari kerja</strong>. Melebihi batas tersebut wajib menggunakan cuti.
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
                            <input type="hidden" name="jenis_izin" value="{{ old('jenis_izin', 'Izin Tidak Masuk Kerja') }}">

                            <div class="mb-3">
                                <label class="form-label">Jenis Izin</label>
                                <input type="text" class="form-control" value="Izin Tidak Masuk Kerja" disabled>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror" value="{{ old('tanggal_mulai') }}" min="{{ now()->toDateString() }}" required>
                                    @error('tanggal_mulai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="tanggal_selesai" class="form-label">Tanggal Selesai <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror" value="{{ old('tanggal_selesai') }}" min="{{ now()->toDateString() }}" required>
                                    @error('tanggal_selesai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="atasan_pimpinan_uuid" class="form-label">Atasan Pimpinan <span class="text-danger">*</span></label>
                                <select name="atasan_pimpinan_uuid" id="atasan_pimpinan_uuid" class="form-select @error('atasan_pimpinan_uuid') is-invalid @enderror" required>
                                    <option value="">Pilih Atasan Pimpinan</option>
                                    @foreach($atasanList as $atasan)
                                        <option value="{{ $atasan->atasan_pimpinan_uuid }}" {{ old('atasan_pimpinan_uuid') == $atasan->atasan_pimpinan_uuid ? 'selected' : '' }}>{{ $atasan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('atasan_pimpinan_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="pimpinan_uuid" class="form-label">Pimpinan <span class="text-danger">*</span></label>
                                <select name="pimpinan_uuid" id="pimpinan_uuid" class="form-select @error('pimpinan_uuid') is-invalid @enderror" required>
                                    <option value="">Pilih Pimpinan</option>
                                    @foreach($pimpinanList as $pimpinan)
                                        <option value="{{ $pimpinan->pimpinan_uuid }}" {{ old('pimpinan_uuid') == $pimpinan->pimpinan_uuid ? 'selected' : '' }}>{{ $pimpinan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('pimpinan_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Two-level approval: atasan langsung + pimpinan.</small>
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
