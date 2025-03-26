@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Detail Permohonan Cuti</h5>
                </div>

                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Nama Pegawai</div>
                        <div class="col-md-8">{{ $cuti->pegawai->nama }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">NIP</div>
                        <div class="col-md-8">{{ $cuti->pegawai->nip }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Jenis Cuti</div>
                        <div class="col-md-8">{{ $cuti->jenis_cuti }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tanggal Mulai</div>
                        <div class="col-md-8">{{ \Carbon\Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y') }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tanggal Selesai</div>
                        <div class="col-md-8">{{ \Carbon\Carbon::parse($cuti->tanggal_selesai)->format('d/m/Y') }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Lama Cuti</div>
                        <div class="col-md-8">{{ $cuti->lama_cuti }} hari</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Alasan</div>
                        <div class="col-md-8">{{ $cuti->alasan }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Alamat Selama Cuti</div>
                        <div class="col-md-8">{{ $cuti->alamat_selama_cuti }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">No. HP Selama Cuti</div>
                        <div class="col-md-8">{{ $cuti->no_hp_selama_cuti }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Status</div>
                        <div class="col-md-8">
                            @if($cuti->status == 'Pending')
                                <span class="badge bg-warning">Pending</span>
                            @elseif($cuti->status == 'Disetujui Verifikator')
                                <span class="badge bg-info">Disetujui Verifikator</span>
                            @elseif($cuti->status == 'Ditolak Verifikator')
                                <span class="badge bg-danger">Ditolak Verifikator</span>
                            @elseif($cuti->status == 'Disetujui Pimpinan')
                                <span class="badge bg-success">Disetujui Pimpinan</span>
                            @elseif($cuti->status == 'Ditolak Pimpinan')
                                <span class="badge bg-danger">Ditolak Pimpinan</span>
                            @endif
                        </div>
                    </div>

                    <!-- Verifikator Information -->
                    @if($cuti->verifikator_uuid)
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold">Informasi Verifikasi Tahap 1 (Verifikator)</h6>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Status Verifikator</div>
                        <div class="col-md-8">
                            @if($cuti->status_verifikator == 'Disetujui')
                                <span class="badge bg-success">Disetujui</span>
                            @elseif($cuti->status_verifikator == 'Ditolak')
                                <span class="badge bg-danger">Ditolak</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Verifikator</div>
                        <div class="col-md-8">{{ $cuti->verifikator->nama ?? 'N/A' }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tanggal Verifikasi</div>
                        <div class="col-md-8">{{ $cuti->tanggal_verifikasi ? \Carbon\Carbon::parse($cuti->tanggal_verifikasi)->format('d/m/Y') : 'N/A' }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Catatan Verifikator</div>
                        <div class="col-md-8">{{ $cuti->catatan_verifikator ?? 'Tidak ada catatan' }}</div>
                    </div>
                    @endif

                    <!-- Pimpinan Information -->
                    @if($cuti->pimpinan_uuid)
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold">Informasi Verifikasi Tahap 2 (Pimpinan)</h6>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Status Pimpinan</div>
                        <div class="col-md-8">
                            @if($cuti->status_pimpinan == 'Disetujui')
                                <span class="badge bg-success">Disetujui</span>
                            @elseif($cuti->status_pimpinan == 'Ditolak')
                                <span class="badge bg-danger">Ditolak</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Pimpinan</div>
                        <div class="col-md-8">{{ $cuti->pimpinan->nama ?? 'N/A' }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tanggal Verifikasi</div>
                        <div class="col-md-8">{{ $cuti->tanggal_verifikasi_pimpinan ? \Carbon\Carbon::parse($cuti->tanggal_verifikasi_pimpinan)->format('d/m/Y') : 'N/A' }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Catatan Pimpinan</div>
                        <div class="col-md-8">{{ $cuti->catatan_pimpinan ?? 'Tidak ada catatan' }}</div>
                    </div>
                    @endif

                    @if($cuti->dokumen)
                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Dokumen Pendukung</div>
                        <div class="col-md-8">
                            <a href="{{ asset('storage/dokumen/cuti/' . $cuti->dokumen) }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="bi bi-file-earmark"></i> Lihat Dokumen
                            </a>
                        </div>
                    </div>
                    @endif

                    <div class="d-flex justify-content-between mt-4">
                        <a href="{{ route('cuti.index') }}" class="btn btn-secondary">Kembali</a>

                        <div>
                            @if($cuti->status == 'Pending')
                                @can('update cuti')
                                <a href="{{ route('cuti.edit', $cuti->uuid) }}" class="btn btn-warning">Edit</a>
                                @endcan

                                @can('verifikasi cuti')
                                <a href="{{ route('cuti.verifikasi', $cuti->uuid) }}" class="btn btn-primary">Verifikasi</a>
                                @endcan
                            @endif

                            @if($cuti->status == 'Disetujui Verifikator')
                                @can('pimpinan cuti')
                                <a href="{{ route('cuti.verifikasi-pimpinan', $cuti->uuid) }}" class="btn btn-primary">Verifikasi Pimpinan</a>
                                @endcan
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection