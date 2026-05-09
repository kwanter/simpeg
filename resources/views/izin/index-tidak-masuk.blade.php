<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Izin Tidak Masuk Kerja</h2>
    </x-slot>

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
                        <h4>Daftar Izin Tidak Masuk Kerja
                            <a href="{{ route('izin.create-tidak-masuk') }}" class="btn btn-primary float-end">Ajukan Izin Tidak Masuk</a>
                        </h4>
                        <small class="text-muted">Lampiran III PERMA No. 7 Tahun 2016 — Maksimal 2 hari kerja (Pasal 8)</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr class="text-center">
                                        <th>No</th>
                                        <th>Nama Pegawai</th>
                                        <th>Tanggal</th>
                                        <th>Jumlah Hari</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($izins as $izin)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $izin->pegawai?->nama }}</td>
                                        <td>
                                            {{ \Carbon\Carbon::parse($izin->tanggal_mulai)->locale('id')->translatedFormat('d F Y') }}
                                            s.d.
                                            {{ \Carbon\Carbon::parse($izin->tanggal_selesai)->locale('id')->translatedFormat('d F Y') }}
                                        </td>
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
                                            @can('verifyAtasan', $izin)
                                                <a href="{{ route('izin.verifikasi-atasan', $izin->uuid) }}" class="btn btn-warning btn-sm">Verifikasi Atasan</a>
                                            @endcan
                                            @can('verifyPimpinan', $izin)
                                                <a href="{{ route('izin.verifikasi-pimpinan', $izin->uuid) }}" class="btn btn-warning btn-sm">Verifikasi Pimpinan</a>
                                            @endcan
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Belum ada data izin tidak masuk kerja.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $izins->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
