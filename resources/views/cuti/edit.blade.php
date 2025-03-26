@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Edit Permohonan Cuti</h5>
                </div>

                <div class="card-body">
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('cuti.update', $cuti->uuid) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="nama_pegawai" class="form-label">Nama Pegawai</label>
                            <input type="text" class="form-control" id="nama_pegawai" value="{{ $cuti->pegawai->nama }}" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="jenis_cuti" class="form-label">Jenis Cuti</label>
                            <select class="form-select @error('jenis_cuti') is-invalid @enderror" id="jenis_cuti" name="jenis_cuti" required>
                                <option value="">Pilih Jenis Cuti</option>
                                @foreach($jenisCuti as $jenis)
                                    <option value="{{ $jenis }}" {{ old('jenis_cuti', $cuti->jenis_cuti) == $jenis ? 'selected' : '' }}>{{ $jenis }}</option>
                                @endforeach
                            </select>
                            @error('jenis_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tanggal_mulai" class="form-label">Tanggal Mulai</label>
                                    <input type="date" class="form-control @error('tanggal_mulai') is-invalid @enderror" id="tanggal_mulai" name="tanggal_mulai" value="{{ old('tanggal_mulai', $cuti->tanggal_mulai) }}" required>
                                    @error('tanggal_mulai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="tanggal_selesai" class="form-label">Tanggal Selesai</label>
                                    <input type="date" class="form-control @error('tanggal_selesai') is-invalid @enderror" id="tanggal_selesai" name="tanggal_selesai" value="{{ old('tanggal_selesai', $cuti->tanggal_selesai) }}" required>
                                    @error('tanggal_selesai')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="alasan" class="form-label">Alasan Cuti</label>
                            <textarea class="form-control @error('alasan') is-invalid @enderror" id="alasan" name="alasan" rows="3" required>{{ old('alasan', $cuti->alasan) }}</textarea>
                            @error('alasan')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="alamat_selama_cuti" class="form-label">Alamat Selama Cuti</label>
                            <textarea class="form-control @error('alamat_selama_cuti') is-invalid @enderror" id="alamat_selama_cuti" name="alamat_selama_cuti" rows="2" required>{{ old('alamat_selama_cuti', $cuti->alamat_selama_cuti) }}</textarea>
                            @error('alamat_selama_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="no_hp_selama_cuti" class="form-label">No. HP Selama Cuti</label>
                            <input type="text" class="form-control @error('no_hp_selama_cuti') is-invalid @enderror" id="no_hp_selama_cuti" name="no_hp_selama_cuti" value="{{ old('no_hp_selama_cuti', $cuti->no_hp_selama_cuti) }}" required>
                            @error('no_hp_selama_cuti')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="dokumen" class="form-label">Dokumen Pendukung (PDF/JPG/PNG, max 2MB)</label>
                            <input type="file" class="form-control @error('dokumen') is-invalid @enderror" id="dokumen" name="dokumen">
                            @if($cuti->dokumen)
                                <div class="mt-2">
                                    <small class="text-muted">Dokumen saat ini: </small>
                                    <a href="{{ asset('storage/dokumen/cuti/' . $cuti->dokumen) }}" target="_blank">Lihat Dokumen</a>
                                </div>
                            @endif
                            <small class="text-muted">Opsional. Upload dokumen baru untuk mengganti dokumen lama.</small>
                            @error('dokumen')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('cuti.index') }}" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection