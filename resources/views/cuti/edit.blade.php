<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Permohonan Cuti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                    <td width="70%">: {{ $cuti->pegawai->nama }}</td>
                                </tr>
                                <tr>
                                    <th>NIP</th>
                                    <td>: {{ $cuti->pegawai->nip }}</td>
                                </tr>
                                <tr>
                                    <th>Jabatan</th>
                                    <td>: {{ $cuti->pegawai->jabatan->nama_jabatan ?? 'Belum ada jabatan' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Unit Kerja</th>
                                    <td width="70%">: {{ $cuti->pegawai->unit_kerja ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Pangkat/Gol.</th>
                                    <td>: {{ $cuti->pegawai->pangkat ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>: {{ $cuti->pegawai->status_pegawai ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave balance card -->
            @if($balance && $cuti->jenis_cuti == 'Cuti Tahunan')
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

            <div class="card shadow">
                <div class="card-body">
                    <div class="mb-4">
                        <a href="{{ route('cuti.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Kembali
                        </a>
                    </div>

                    <form action="{{ route('cuti.update', $cuti->uuid) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="jenis_cuti" class="form-label">Jenis Cuti</label>
                            <select name="jenis_cuti" id="jenis_cuti" class="form-select @error('jenis_cuti') is-invalid @enderror" required>
                                <option value="">Pilih Jenis Cuti</option>
                                @foreach($jenisCuti as $jenis)
                                    <option value="{{ $jenis }}" {{ $cuti->jenis_cuti == $jenis ? 'selected' : '' }}>{{ $jenis }}</option>
                                @endforeach
                            </select>
                            @error('jenis_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                                <input type="date" name="tanggal_mulai" id="tanggal_mulai" value="{{ $cuti->tanggal_mulai }}"
                                    class="form-control @error('tanggal_mulai') is-invalid @enderror" required>
                                @error('tanggal_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                                <input type="date" name="tanggal_selesai" id="tanggal_selesai" value="{{ $cuti->tanggal_selesai }}"
                                    class="form-control @error('tanggal_selesai') is-invalid @enderror" required>
                                @error('tanggal_selesai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="alasan" class="form-label">Alasan Cuti</label>
                            <textarea name="alasan" id="alasan" rows="3"
                                class="form-control @error('alasan') is-invalid @enderror" required>{{ $cuti->alasan }}</textarea>
                            @error('alasan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="alamat_selama_cuti" class="form-label">Alamat Selama Cuti</label>
                            <textarea name="alamat_selama_cuti" id="alamat_selama_cuti" rows="2"
                                class="form-control @error('alamat_selama_cuti') is-invalid @enderror" required>{{ $cuti->alamat_selama_cuti }}</textarea>
                            @error('alamat_selama_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="no_hp_selama_cuti" class="form-label">No. HP Selama Cuti</label>
                            <input type="text" name="no_hp_selama_cuti" id="no_hp_selama_cuti" value="{{ $cuti->no_hp_selama_cuti }}"
                                class="form-control @error('no_hp_selama_cuti') is-invalid @enderror" required>
                            @error('no_hp_selama_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="dokumen" class="form-label">Dokumen Pendukung (PDF/JPG/PNG, max 2MB)</label>
                            @if($cuti->dokumen)
                                <div class="mb-2">
                                    <a href="{{ asset('storage/dokumen/cuti/' . $cuti->dokumen) }}" target="_blank" class="text-primary">
                                        <i class="fas fa-file-alt me-1"></i> Lihat dokumen saat ini
                                    </a>
                                </div>
                            @endif
                            <input type="file" name="dokumen" id="dokumen" class="form-control @error('dokumen') is-invalid @enderror">
                            <small class="text-muted">Biarkan kosong jika tidak ingin mengubah dokumen</small>
                            @error('dokumen')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <a href="{{ route('cuti.index') }}" class="btn btn-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>