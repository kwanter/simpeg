@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Verifikasi Pimpinan - Permohonan Cuti</h5>
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
                        <div class="col-md-4 fw-bold">Tanggal</div>
                        <div class="col-md-8">
                            {{ \Carbon\Carbon::parse($cuti->tanggal_mulai)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($cuti->tanggal_selesai)->format('d/m/Y') }}
                            ({{ $cuti->lama_cuti }} hari)
                        </div>
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
                        <div class="col-md-4 fw-bold">Status Verifikator</div>
                        <div class="col-md-8">
                            <span class="badge bg-success">Disetujui Verifikator</span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Verifikator</div>
                        <div class="col-md-8">{{ $cuti->verifikator->nama }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Tanggal Verifikasi</div>
                        <div class="col-md-8">{{ \Carbon\Carbon::parse($cuti->tanggal_verifikasi)->format('d/m/Y') }}</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4 fw-bold">Catatan Verifikator</div>
                        <div class="col-md-8">{{ $cuti->catatan_verifikator ?? 'Tidak ada catatan' }}</div>
                    </div>

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

                    <hr>

                    <form action="{{ route('cuti.proses-verifikasi-pimpinan', $cuti->uuid) }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">Keputusan Pimpinan</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status_pimpinan" id="status_disetujui" value="Disetujui" required>
                                <label class="form-check-label" for="status_disetujui">
                                    Disetujui
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="status_pimpinan" id="status_ditolak" value="Ditolak">
                                <label class="form-check-label" for="status_ditolak">
                                    Ditolak
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="catatan_pimpinan" class="form-label">Catatan Pimpinan</label>
                            <textarea class="form-control" id="catatan_pimpinan" name="catatan_pimpinan" rows="3"></textarea>
                            <small class="text-muted">Opsional. Berikan catatan atau alasan terkait keputusan Anda.</small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('cuti.index') }}" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan Keputusan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection