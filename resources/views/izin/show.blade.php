<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Detail Pengajuan Izin
                            <a href="{{ route('izin.index') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
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
                                        <th>Tanggal Mulai</th>
                                        <td>{{ $izin->tanggal_mulai->format('d-m-Y') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Tanggal Selesai</th>
                                        <td>{{ $izin->tanggal_selesai->format('d-m-Y') }}</td>
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
                                        <th>Atasan Pimpinan</th>
                                        <td>{{ $izin->atasan_pimpinan->nama ?? 'Tidak ada' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Pimpinan</th>
                                        <td>{{ $izin->pimpinan->nama ?? 'Tidak ada' }}</td>
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
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200px">Status</th>
                                        <td>
                                            @if($izin->status == 'Diajukan')
                                                <span class="badge bg-warning">{{ $izin->status }}</span>
                                            @elseif($izin->status == 'Disetujui Atasan')
                                                <span class="badge bg-info">{{ $izin->status }}</span>
                                            @elseif($izin->status == 'Ditolak Atasan')
                                                <span class="badge bg-danger">{{ $izin->status }}</span>
                                            @elseif($izin->status == 'Disetujui')
                                                <span class="badge bg-success">{{ $izin->status }}</span>
                                            @elseif($izin->status == 'Ditolak')
                                                <span class="badge bg-danger">{{ $izin->status }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Verifikasi Atasan</th>
                                        <td>
                                            @if($izin->verifikasi_atasan == 'Belum Diverifikasi')
                                                <span class="badge bg-secondary">{{ $izin->verifikasi_atasan }}</span>
                                            @elseif($izin->verifikasi_atasan == 'Disetujui')
                                                <span class="badge bg-success">{{ $izin->verifikasi_atasan }}</span>
                                            @elseif($izin->verifikasi_atasan == 'Ditolak')
                                                <span class="badge bg-danger">{{ $izin->verifikasi_atasan }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($izin->tanggal_verifikasi_atasan)
                                    <tr>
                                        <th>Tanggal Verifikasi Atasan</th>
                                        <td>{{ $izin->tanggal_verifikasi_atasan->format('d-m-Y') }}</td>
                                    </tr>
                                    @endif
                                    @if($izin->catatan_atasan)
                                    <tr>
                                        <th>Catatan Atasan</th>
                                        <td>{{ $izin->catatan_atasan }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <th>Verifikasi Pimpinan</th>
                                        <td>
                                            @if($izin->verifikasi_pimpinan == 'Belum Diverifikasi')
                                                <span class="badge bg-secondary">{{ $izin->verifikasi_pimpinan }}</span>
                                            @elseif($izin->verifikasi_pimpinan == 'Disetujui')
                                                <span class="badge bg-success">{{ $izin->verifikasi_pimpinan }}</span>
                                            @elseif($izin->verifikasi_pimpinan == 'Ditolak')
                                                <span class="badge bg-danger">{{ $izin->verifikasi_pimpinan }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($izin->tanggal_verifikasi_pimpinan)
                                    <tr>
                                        <th>Tanggal Verifikasi Pimpinan</th>
                                        <td>{{ $izin->tanggal_verifikasi_pimpinan->format('d-m-Y') }}</td>
                                    </tr>
                                    @endif
                                    @if($izin->catatan_pimpinan)
                                    <tr>
                                        <th>Catatan Pimpinan</th>
                                        <td>{{ $izin->catatan_pimpinan }}</td>
                                    </tr>
                                    @endif
                                </table>
                                <!-- Add this button in the appropriate location in your show.blade.php -->
                                @if(($izin->status == 'Disetujui' || $izin->status == 'Disetujui Atasan') && !empty($izin->no_surat_izin))
                                <a href="{{ route('izin.pdf', $izin->uuid) }}" class="btn btn-primary">
                                    <i class="fas fa-file-pdf me-1"></i> Cetak Surat Izin
                                </a>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3">
                            @if(auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || (auth()->user()->id == $izin->pegawai->user_id && $izin->verifikasi_atasan == 'Belum Diverifikasi' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi'))
                                <a href="{{ route('izin.edit', $izin->uuid) }}" class="btn btn-primary">Edit</a>

                                <form action="{{ route('izin.destroy', $izin->uuid) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">Hapus</button>
                                </form>
                            @endif

                            @if((auth()->user()->hasRole('atasan-pimpinan') || auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin')) && $izin->verifikasi_atasan == 'Belum Diverifikasi')
                                <a href="{{ route('izin.verifikasi-atasan', $izin->uuid) }}" class="btn btn-warning">Verifikasi Atasan</a>
                            @endif

                            @if((auth()->user()->hasRole('pimpinan') || auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin')) && $izin->verifikasi_atasan == 'Disetujui' && $izin->verifikasi_pimpinan == 'Belum Diverifikasi')
                                <a href="{{ route('izin.verifikasi-pimpinan', $izin->uuid) }}" class="btn btn-warning">Verifikasi Pimpinan</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
