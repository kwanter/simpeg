<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Detail Permohonan Cuti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Employee information card moved to top -->
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
                                    <td width="70%">: {{ $cuti->pegawai?->nama }}</td>
                                </tr>
                                <tr>
                                    <th>NIP</th>
                                    <td>: {{ $cuti->pegawai?->nip }}</td>
                                </tr>
                                <tr>
                                    <th>Jabatan</th>
                                    <td>: {{ $cuti->pegawai?->jabatan?->nama_jabatan ?? 'Belum ada jabatan' }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">Unit Kerja</th>
                                    <td width="70%">: {{ $cuti->pegawai?->unit_kerja ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Pangkat/Gol.</th>
                                    <td>: {{ $cuti->pegawai?->pangkat ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>: {{ $cuti->pegawai?->status_pegawai ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leave balance card moved to top -->
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

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">Informasi Cuti</h5>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="40%">Jenis Cuti</td>
                                            <td>: {{ $cuti->jenis_cuti }}</td>
                                        </tr>
                                        <tr>
                                            <td>Tanggal Mulai</td>
                                            <td>: {{ \Carbon\Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <td>Tanggal Selesai</td>
                                            <td>: {{ \Carbon\Carbon::parse($cuti->tanggal_selesai)->format('d/m/Y') }}</td>
                                        </tr>
                                        <tr>
                                            <td>Lama Cuti</td>
                                            <td>: {{ $cuti->lama_cuti }} hari</td>
                                        </tr>
                                        <tr>
                                            <td>Status</td>
                                            <td>:
                                                <span class="badge
                                                    {{ $cuti->status == 'Pending' ? 'bg-warning' : '' }}
                                                    {{ $cuti->status == 'Disetujui Verifikator' ? 'bg-info' : '' }}
                                                    {{ $cuti->status == 'Ditolak Verifikator' ? 'bg-danger' : '' }}
                                                    {{ $cuti->status == 'Disetujui Pimpinan' ? 'bg-success' : '' }}
                                                    {{ $cuti->status == 'Ditolak Pimpinan' ? 'bg-danger' : '' }}
                                                    {{ $cuti->status == 'Disetujui Atasan Pimpinan' ? 'bg-primary' : '' }}
                                                    {{ $cuti->status == 'Ditolak Atasan Pimpinan' ? 'bg-danger' : '' }}">
                                                    {{ $cuti->status }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>No Surat Cuti</td>
                                            <td>:
                                                {{ $cuti->no_surat_cuti ?? 'Belum ada' }}
                                                @can('editNoSurat', $cuti)
                                                    <a href="{{ route('cuti.edit', $cuti->uuid) }}" class="btn btn-sm btn-warning ms-2">
                                                        <i class="fas fa-edit"></i> Edit No Surat
                                                    </a>
                                                @endcan
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
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
                                                <p>{{ $cuti->verifikator?->nama ?? 'N/A' }}</p>

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

                    @if($cuti->pimpinan_uuid)
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">Informasi Persetujuan Pimpinan</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Pimpinan:</h6>
                                                <p>{{ $cuti->pimpinan?->nama ?? 'N/A' }}</p>

                                                <h6 class="fw-bold mb-2 mt-3">Tanggal Persetujuan:</h6>
                                                <p>{{ $cuti->tanggal_verifikasi_pimpinan ? \Carbon\Carbon::parse($cuti->tanggal_verifikasi_pimpinan)->format('d/m/Y H:i') : 'N/A' }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Status Persetujuan:</h6>
                                                <p>{{ $cuti->status_pimpinan }}</p>

                                                <h6 class="fw-bold mb-2 mt-3">Catatan Pimpinan:</h6>
                                                <p class="bg-light p-3 rounded">{{ $cuti->catatan_pimpinan ?: 'Tidak ada catatan' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($cuti->atasan_pimpinan_uuid)
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">Informasi Persetujuan Atasan Pimpinan</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Atasan Pimpinan:</h6>
                                                <p>{{ $cuti->atasanPimpinan?->nama ?? 'N/A' }}</p>

                                                <h6 class="fw-bold mb-2 mt-3">Tanggal Persetujuan:</h6>
                                                <p>{{ $cuti->tanggal_verifikasi_atasan_pimpinan ? \Carbon\Carbon::parse($cuti->tanggal_verifikasi_atasan_pimpinan)->format('d/m/Y H:i') : 'N/A' }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <h6 class="fw-bold mb-2">Status Persetujuan:</h6>
                                                <p>{{ $cuti->status_atasan_pimpinan }}</p>

                                                <h6 class="fw-bold mb-2 mt-3">Catatan Atasan Pimpinan:</h6>
                                                <p class="bg-light p-3 rounded">{{ $cuti->catatan_atasan_pimpinan ?: 'Tidak ada catatan' }}</p>
                                            </div>
                                         </div>
                                          <!-- Add this button in the appropriate location in your show.blade.php -->
                                          @can('cetak', $cuti)
                                          <a href="{{ route('cuti.pdf', $cuti->uuid) }}" class="btn btn-primary">
                                              <i class="fas fa-file-pdf me-1"></i> Cetak Surat Cuti
                                          </a>
                                          @endcan
                                     </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
