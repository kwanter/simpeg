<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Ajukan Izin
                            <a href="{{ route('izin.index') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
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

                            <!-- Remove the no_surat_izin field -->

                            <div class="mb-3">
                                <label for="jenis_izin" class="form-label">Jenis Izin</label>
                                <select name="jenis_izin" id="jenis_izin" class="form-control @error('jenis_izin') is-invalid @enderror" required>
                                    <option value="">Pilih Jenis Izin</option>
                                    @foreach($jenisIzin as $jenis)
                                        <option value="{{ $jenis }}" {{ old('jenis_izin') == $jenis ? 'selected' : '' }}>{{ $jenis }}</option>
                                    @endforeach
                                </select>
                                @error('jenis_izin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Add atasan pimpinan and pimpinan selection -->
                            <div class="mb-3">
                                <label for="atasan_pimpinan_uuid" class="form-label">Atasan Pimpinan</label>
                                <select name="atasan_pimpinan_uuid" id="atasan_pimpinan_uuid" class="form-control @error('atasan_pimpinan_uuid') is-invalid @enderror" required>
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
                                <label for="pimpinan_uuid" class="form-label">Pimpinan</label>
                                <select name="pimpinan_uuid" id="pimpinan_uuid" class="form-control @error('pimpinan_uuid') is-invalid @enderror" required>
                                    <option value="">Pilih Pimpinan</option>
                                    @foreach($pimpinanList as $pimpinan)
                                        <option value="{{ $pimpinan->pimpinan_uuid }}" {{ old('pimpinan_uuid') == $pimpinan->pimpinan_uuid ? 'selected' : '' }}>{{ $pimpinan->nama }}</option>
                                    @endforeach
                                </select>
                                @error('pimpinan_uuid')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror" value="{{ old('tanggal_mulai') }}" required>
                                @error('tanggal_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror" value="{{ old('tanggal_selesai') }}" required>
                                @error('tanggal_selesai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="jam_mulai" class="form-label">Jam Mulai (opsional)</label>
                                    <input type="time" name="jam_mulai" id="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror" value="{{ old('jam_mulai') }}">
                                    <small class="text-muted">Kosongkan jika izin seharian</small>
                                    @error('jam_mulai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="jam_selesai" class="form-label">Jam Selesai (opsional)</label>
                                    <input type="time" name="jam_selesai" id="jam_selesai" class="form-control @error('jam_selesai') is-invalid @enderror" value="{{ old('jam_selesai') }}">
                                    <small class="text-muted">Kosongkan jika izin seharian</small>
                                    @error('jam_selesai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="alasan" class="form-label">Alasan</label>
                                <textarea name="alasan" id="alasan" class="form-control @error('alasan') is-invalid @enderror" rows="3" required>{{ old('alasan') }}</textarea>
                                @error('alasan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="dokumen" class="form-label">Dokumen Pendukung (PDF/JPG/PNG, max 2MB)</label>
                                <input type="file" name="dokumen" id="dokumen" class="form-control @error('dokumen') is-invalid @enderror">
                                <small class="text-muted">Unggah dokumen pendukung seperti surat keterangan dokter, surat undangan, atau dokumen lainnya yang relevan.</small>
                                @error('dokumen')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Ajukan Izin</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
