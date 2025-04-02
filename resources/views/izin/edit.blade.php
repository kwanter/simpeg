<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>
                            @if($izin->verifikasi_atasan == 'Disetujui' && empty($izin->no_surat_izin))
                                Tambah Nomor Surat Izin
                            @else
                                Edit Pengajuan Izin
                            @endif
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

                        <form action="{{ route('izin.update', $izin->uuid) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            @if($izin->verifikasi_atasan == 'Disetujui')
                                <div class="mb-3">
                                    <label for="no_surat_izin" class="form-label">Nomor Surat Izin</label>
                                    <input type="text" name="no_surat_izin" id="no_surat_izin" class="form-control @error('no_surat_izin') is-invalid @enderror" value="{{ old('no_surat_izin', $izin->no_surat_izin) }}" required>
                                    @error('no_surat_izin')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                @if($izin->verifikasi_pimpinan == 'Belum Diverifikasi' && (auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin')))
                                    <!-- Full form for admins -->
                                    <div class="mb-3">
                                        <label for="jenis_izin" class="form-label">Jenis Izin</label>
                                        <!-- Rest of the form fields -->
                                        <!-- ... -->
                                    </div>
                                    <!-- Include all other form fields here -->
                                @else
                                    <!-- Only show submit button for no_surat_izin update -->
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary">Simpan Nomor Surat</button>
                                    </div>
                                @endif
                            @else
                                <!-- Regular edit form for non-verified izin -->
                                <div class="mb-3">
                                    <label for="jenis_izin" class="form-label">Jenis Izin</label>
                                    <select name="jenis_izin" id="jenis_izin" class="form-control @error('jenis_izin') is-invalid @enderror" required>
                                        <option value="">Pilih Jenis Izin</option>
                                        @foreach($jenisIzin as $jenis)
                                            <option value="{{ $jenis }}" {{ old('jenis_izin', $izin->jenis_izin) == $jenis ? 'selected' : '' }}>{{ $jenis }}</option>
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
                                            <option value="{{ $atasan->atasan_pimpinan_uuid }}" {{ old('atasan_pimpinan_uuid', $izin->atasan_pimpinan_uuid) == $atasan->atasan_pimpinan_uuid ? 'selected' : '' }}>{{ $atasan->nama }}</option>
                                        @endforeach
                                    </select>
                                    @error('atasan_pimpinan_uuid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Rest of the form fields -->
                                <!-- ... -->

                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">Update Izin</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
