<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Verifikasi Atasan - Pengajuan Izin
                            <a href="{{ route('izin.index') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200px">Nomor Surat Izin</th>
                                        <td>{{ $izin->no_surat_izin }}</td>
                                    </tr>
                                    <tr>
                                        <th width="200px">Nama Pegawai</th>
                                        <td>{{ $izin->pegawai->nama }}</td>
                                    </tr>
                                    <tr>
                                        <th>NIP</th>
                                        <td>{{ $izin->pegawai->nip }}</td>
                                    </tr>
                                    <tr>
                                        <th>Jenis Izin</th>
                                        <td>{{ $izin->jenis_izin }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal</th>
                                        <td>{{ $izin->tanggal_mulai->format('d-m-Y') }} s/d {{ $izin->tanggal_selesai->format('d-m-Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Waktu</th>
                                        <td>
                                            @if($izin->jam_mulai && $izin->jam_selesai)
                                                {{ $izin->jam_mulai }} s/d {{ $izin->jam_selesai }}
                                            @else
                                                <span class="text-muted">Seharian</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Jumlah Hari</th>
                                        <td>{{ $izin->jumlah_hari }} hari</td>
                                    </tr>
                                    <tr>
                                        <th>Alasan</th>
                                        <td>{{ $izin->alasan }}</td>
                                    </tr>
                                    <tr>
                                        <th>Dokumen Pendukung</th>
                                        <td>
                                            @if($izin->dokumen)
                                                <a href="{{ asset('storage/dokumen/izin/' . $izin->dokumen) }}" target="_blank" class="btn btn-sm btn-info">Lihat Dokumen</a>
                                            @else
                                                <span class="text-muted">Tidak ada dokumen</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <form action="{{ route('izin.proses-verifikasi-atasan', $izin->uuid) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="verifikasi_atasan" class="form-label">Verifikasi</label>
                                <select name="verifikasi_atasan" id="verifikasi_atasan" class="form-control @error('verifikasi_atasan') is-invalid @enderror" required>
                                    <option value="">Pilih Verifikasi</option>
                                    <option value="Disetujui">Disetujui</option>
                                    <option value="Ditolak">Ditolak</option>
                                </select>
                                @error('verifikasi_atasan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="catatan_atasan" class="form-label">Catatan</label>
                                <textarea name="catatan_atasan" id="catatan_atasan" class="form-control @error('catatan_atasan') is-invalid @enderror" rows="3">{{ old('catatan_atasan') }}</textarea>
                                @error('catatan_atasan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Simpan Verifikasi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
