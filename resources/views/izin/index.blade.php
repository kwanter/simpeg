<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4>Data Pengajuan Izin
                            @can('create', App\Models\Izin::class)
                                <a href="{{ route('izin.create') }}" class="btn btn-primary float-end">Ajukan Izin</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>No Surat Izin</th>
                                        @can('viewNamaPegawai', App\Models\Izin::class)
                                            <th>Nama Pegawai</th>
                                        @endcan
                                        <th>Jenis Izin</th>
                                        <th>Tanggal</th>
                                        <th>Jumlah Hari</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($izinList as $izin)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $izin->no_surat_izin }}</td>
                                        @can('viewNamaPegawai', App\Models\Izin::class)
                                            <td>{{ $izin->pegawai?->nama }}</td>
                                        @endcan
                                        <td>{{ $izin->jenis_izin }}</td>
                                        <td>{{ $izin->tanggal_mulai->format('d-m-Y') }} s/d {{ $izin->tanggal_selesai->format('d-m-Y') }}</td>
                                        <td class="text-center">{{ $izin->jumlah_hari }}</td>
                                        <td class="text-center">
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
                                        <td class="text-center">
                                            <a href="{{ route('izin.show', $izin->uuid) }}" class="btn btn-info btn-sm">Detail</a>
                                            @can('update', $izin)
                                                <a href="{{ route('izin.edit', $izin->uuid) }}" class="btn btn-primary btn-sm">Edit</a>
                                                <form action="{{ route('izin.destroy', $izin->uuid) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')">Hapus</button>
                                                </form>
                                            @endcan
                                            @can('verifyAtasan', $izin)
                                                <a href="{{ route('izin.verifikasi-atasan', $izin->uuid) }}" class="btn btn-warning btn-sm">Verifikasi Atasan</a>
                                            @endcan
                                            @can('verifyPimpinan', $izin)
                                                <a href="{{ route('izin.verifikasi-pimpinan', $izin->uuid) }}" class="btn btn-warning btn-sm">Verifikasi Pimpinan</a>
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <!-- Empty state remains the same -->
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-center mt-3">
                            {{ $izinList->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
