<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Ajukan Cuti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card shadow">
                <div class="card-body">
                    <div class="mb-4">
                        <a href="{{ route('cuti.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>

                    <!-- Add cuti balance card here -->
                    @if(isset($cutiBalance))
                    <div class="card mb-4">
                        <!-- Update the route names in the buttons -->
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Saldo Cuti Tahunan</h5>
                            <div>
                                <a href="{{ route('cuti.update-balance') }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-sync-alt me-1"></i> Perbarui Saldo
                                </a>
                                @can('update cuti')
                                <a href="{{ route('cuti.update-all-balances') }}" class="btn btn-sm btn-warning ms-2"
                                   onclick="return confirm('Apakah Anda yakin ingin memperbarui saldo cuti untuk semua pegawai?')">
                                    <i class="fas fa-sync-alt me-1"></i> Perbarui Semua Pegawai
                                </a>
                                @endcan
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h6>Jatah Tahunan</h6>
                                            <h3>{{ $cutiBalance['total_days'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h6>Sisa Tahun Lalu</h6>
                                            <h3>{{ $cutiBalance['carried_over'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h6>Terpakai</h6>
                                            <h3>{{ $cutiBalance['used_days'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h6>Sisa Cuti</h6>
                                            <h3>{{ $cutiBalance['remaining_days'] }}</h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <form action="{{ route('cuti.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="pegawai_uuid" value="{{ $pegawai->uuid }}">

                        <!-- Add employee information card -->
                        <div class="card shadow mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Informasi Pegawai</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="30%">Nama</th>
                                                <td width="70%">: {{ $pegawai->nama }}</td>
                                            </tr>
                                            <tr>
                                                <th>NIP</th>
                                                <td>: {{ $pegawai->nip }}</td>
                                            </tr>
                                            <tr>
                                                <th>Jabatan</th>
                                                <td>: {{ $pegawai->jabatan->nama_jabatan ?? 'Belum ada jabatan' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th width="30%">Unit Kerja</th>
                                                <td width="70%">: {{ $pegawai->unit_kerja ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Pangkat/Gol.</th>
                                                <td>: {{ $pegawai->pangkat ?? '-' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td>: {{ $pegawai->status_pegawai ?? '-' }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave balance card -->
                        @if($balance)
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">Informasi Saldo Cuti Tahunan</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center mb-3 mb-md-0">
                                            <h6 class="text-muted">Total Cuti</h6>
                                            <h4>{{ $balance->total_days }} hari</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center mb-3 mb-md-0">
                                            <h6 class="text-muted">Sisa Tahun Lalu</h6>
                                            <h4>{{ $balance->carried_over }} hari</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center mb-3 mb-md-0">
                                            <h6 class="text-muted">Terpakai</h6>
                                            <h4>{{ $balance->used_days }} hari</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <h6 class="text-muted">Sisa Cuti</h6>
                                            <h4 class="text-primary">{{ $balance->total_days + $balance->carried_over - $balance->used_days }} hari</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jenis_cuti" class="form-label">Jenis Cuti</label>
                                    <select name="jenis_cuti" id="jenis_cuti" class="form-select @error('jenis_cuti') is-invalid @enderror" required>
                                        <option value="">Pilih Jenis Cuti</option>
                                        <option value="Cuti Tahunan" {{ old('jenis_cuti') == 'Cuti Tahunan' ? 'selected' : '' }}>Cuti Tahunan</option>
                                        <option value="Cuti Sakit" {{ old('jenis_cuti') == 'Cuti Sakit' ? 'selected' : '' }}>Cuti Sakit</option>
                                        <option value="Cuti Melahirkan" {{ old('jenis_cuti') == 'Cuti Melahirkan' ? 'selected' : '' }}>Cuti Melahirkan</option>
                                        <option value="Cuti Alasan Penting" {{ old('jenis_cuti') == 'Cuti Alasan Penting' ? 'selected' : '' }}>Cuti Alasan Penting</option>
                                        <option value="Cuti Besar" {{ old('jenis_cuti') == 'Cuti Besar' ? 'selected' : '' }}>Cuti Besar</option>
                                    </select>
                                    @error('jenis_cuti')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control @error('tanggal_mulai') is-invalid @enderror" value="{{ old('tanggal_mulai') }}" required>
                                    @error('tanggal_mulai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                                    <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control @error('tanggal_selesai') is-invalid @enderror" value="{{ old('tanggal_selesai') }}" required>
                                    @error('tanggal_selesai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Add this after your existing form fields -->

                        <div class="mb-3">
                            <label for="pimpinan_uuid" class="form-label">Pimpinan</label>
                            <select name="pimpinan_uuid" id="pimpinan_uuid" class="form-control @error('pimpinan_uuid') is-invalid @enderror" required>
                                <option value="">Pilih Pimpinan</option>
                                @foreach($pimpinanList as $pimpinan)
                                    <option value="{{ $pimpinan->uuid }}">{{ $pimpinan->nama }} ({{ $pimpinan->nip }})</option>
                                @endforeach
                            </select>
                            @error('pimpinan_uuid')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="atasan_pimpinan_uuid" class="form-label">Atasan Pimpinan</label>
                            <select name="atasan_pimpinan_uuid" id="atasan_pimpinan_uuid" class="form-control @error('atasan_pimpinan_uuid') is-invalid @enderror" required>
                                <option value="">Pilih Atasan Pimpinan</option>
                                @foreach($atasanPimpinanList as $atasanPimpinan)
                                    <option value="{{ $atasanPimpinan->uuid }}">{{ $atasanPimpinan->nama }} ({{ $atasanPimpinan->nip }})</option>
                                @endforeach
                            </select>
                            @error('atasan_pimpinan_uuid')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="alasan" class="form-label">Alasan Cuti</label>
                            <textarea name="alasan" id="alasan" rows="3" class="form-control @error('alasan') is-invalid @enderror" required>{{ old('alasan') }}</textarea>
                            @error('alasan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="alamat_selama_cuti" class="form-label">Alamat Selama Cuti</label>
                                    <textarea name="alamat_selama_cuti" id="alamat_selama_cuti" rows="3" class="form-control @error('alamat_selama_cuti') is-invalid @enderror" required>{{ old('alamat_selama_cuti') }}</textarea>
                                    @error('alamat_selama_cuti')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="no_hp_selama_cuti" class="form-label">No. HP Selama Cuti</label>
                                    <input type="text" name="no_hp_selama_cuti" id="no_hp_selama_cuti" class="form-control @error('no_hp_selama_cuti') is-invalid @enderror" value="{{ old('no_hp_selama_cuti') }}" required>
                                    @error('no_hp_selama_cuti')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="dokumen" class="form-label">Dokumen Pendukung (opsional)</label>
                            <input type="file" name="dokumen" id="dokumen" class="form-control @error('dokumen') is-invalid @enderror">
                            <div class="form-text">Format: PDF, JPG, JPEG, PNG (Maks. 2MB)</div>
                            @error('dokumen')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>