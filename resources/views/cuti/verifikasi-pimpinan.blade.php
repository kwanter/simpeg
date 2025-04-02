<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Verifikasi Pimpinan Permohonan Cuti') }}
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

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Detail Permohonan</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-2">Alasan Cuti:</h6>
                                    <p class="bg-light p-3 rounded">{{ $cuti->alasan }}</p>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-2">Alamat Selama Cuti:</h6>
                                    <p class="bg-light p-3 rounded">{{ $cuti->alamat_selama_cuti }}</p>

                                    <h6 class="fw-bold mb-2 mt-3">No. HP Selama Cuti:</h6>
                                    <p class="bg-light p-3 rounded">{{ $cuti->no_hp_selama_cuti }}</p>
                                </div>
                            </div>

                            @if($cuti->dokumen)
                                <div class="mt-3">
                                    <h6 class="fw-bold mb-2">Dokumen Pendukung:</h6>
                                    <a href="{{ asset('storage/dokumen/cuti/' . $cuti->dokumen) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-download me-1"></i> Lihat Dokumen
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            @if($cuti->verifikator_uuid)
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">Informasi Verifikasi</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-2">Verifikator:</h6>
                                    <p>{{ $cuti->verifikator->nama ?? 'N/A' }}</p>

                                    <h6 class="fw-bold mb-2 mt-3">Tanggal Verifikasi:</h6>
                                    <p>{{ $cuti->tanggal_verifikasi ? \Carbon\Carbon::parse($cuti->tanggal_verifikasi)->format('d/m/Y H:i') : 'N/A' }}</p>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-2">Status Verifikasi:</h6>
                                    <p>{{ $cuti->status_verifikator }}</p>

                                    <h6 class="fw-bold mb-2 mt-3">Catatan Verifikator:</h6>
                                    <p class="bg-light p-3 rounded">{{ $cuti->catatan_verifikator ?: 'Tidak ada catatan' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Form Verifikasi Pimpinan</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('cuti.proses-verifikasi-pimpinan', $cuti->uuid) }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Status Persetujuan</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status_pimpinan" id="status_disetujui" value="Disetujui" required>
                                    <label class="form-check-label" for="status_disetujui">
                                        Disetujui
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="status_pimpinan" id="status_ditolak" value="Ditolak" required>
                                    <label class="form-check-label" for="status_ditolak">
                                        Ditolak
                                    </label>
                                </div>
                            </div>
                            @error('status_pimpinan')
                                <div class="text-danger mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="catatan_pimpinan" class="form-label">Catatan Pimpinan</label>
                            <textarea name="catatan_pimpinan" id="catatan_pimpinan" rows="3" class="form-control"></textarea>
                            @error('catatan_pimpinan')
                                <div class="text-danger mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <a href="{{ route('cuti.index') }}" class="btn btn-secondary me-2">Batal</a>
                            <button type="submit" class="btn btn-primary">Simpan Persetujuan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>