<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Data Cuti') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="card shadow">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="card-title">Daftar Permohonan Cuti</h3>
                        <div>
                            @can('update cuti')
                            <a href="{{ route('cuti.update-all-balances') }}" class="btn btn-warning me-2"
                               onclick="return confirm('Apakah Anda yakin ingin memperbarui saldo cuti untuk semua pegawai?')">
                                <i class="fas fa-sync-alt me-1"></i> Perbarui Saldo Semua Pegawai
                            </a>
                            @endcan
                            @can('create cuti')
                            <a href="{{ route('cuti.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> Ajukan Cuti
                            </a>
                            @endcan
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>No Surat Cuti</th>
                                    @if(auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('pimpinan') || auth()->user()->hasRole('verifikator'))
                                        <th>Nama Pegawai</th>
                                    @endif
                                    <th>Jenis Cuti</th>
                                    <th>Tanggal Mulai</th>
                                    <th>Tanggal Selesai</th>
                                    <th>Lama Cuti</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($cuti as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $item->no_surat_cuti ?? '-' }}</td>
                                        @if(auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('pimpinan') || auth()->user()->hasRole('verifikator'))
                                            <td>{{ $item->pegawai->nama ?? 'N/A' }}</td>
                                        @endif
                                        <td>{{ $item->jenis_cuti }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal_mulai)->format('d/m/Y') }}</td>
                                        <td>{{ \Carbon\Carbon::parse($item->tanggal_selesai)->format('d/m/Y') }}</td>
                                        <td>{{ $item->lama_cuti }} hari</td>
                                        <td>
                                            <span class="badge
                                                {{ $item->status == 'Pending' ? 'bg-warning' : '' }}
                                                {{ $item->status == 'Disetujui Verifikator' ? 'bg-info' : '' }}
                                                {{ $item->status == 'Ditolak Verifikator' ? 'bg-danger' : '' }}
                                                {{ $item->status == 'Disetujui Pimpinan' ? 'bg-success' : '' }}
                                                {{ $item->status == 'Ditolak Pimpinan' ? 'bg-danger' : '' }}
                                                {{ $item->status == 'Disetujui Atasan Pimpinan' ? 'bg-primary' : '' }}
                                                {{ $item->status == 'Ditolak Atasan Pimpinan' ? 'bg-danger' : '' }}">
                                                {{ $item->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('cuti.show', $item->uuid) }}" class="btn btn-sm btn-info">Detail</a>

                                                @if($item->status == 'Pending')
                                                    @can('update cuti')
                                                        <a href="{{ route('cuti.edit', $item->uuid) }}" class="btn btn-sm btn-warning">Edit</a>
                                                    @endcan

                                                    @can('delete cuti')
                                                        <form action="{{ route('cuti.destroy', $item->uuid) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                                        </form>
                                                    @endcan

                                                    @can('verifikasi cuti')
                                                        <a href="{{ route('cuti.verifikasi', $item->uuid) }}" class="btn btn-sm btn-success">Verifikasi</a>
                                                    @endcan
                                                @endif

                                                @if($item->status == 'Disetujui Verifikator')
                                                    @can('pimpinan cuti')
                                                        <a href="{{ route('cuti.verifikasi-pimpinan', $item->uuid) }}" class="btn btn-sm btn-primary">Verifikasi Pimpinan</a>
                                                    @endcan
                                                @endif

                                                @if($item->status == 'Disetujui Pimpinan')
                                                    @can('atasan pimpinan cuti')
                                                        <a href="{{ route('cuti.verifikasi-atasan-pimpinan', $item->uuid) }}" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-check-circle me-1"></i> Verifikasi Atasan
                                                        </a>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin') || auth()->user()->hasRole('pimpinan') || auth()->user()->hasRole('verifikator') ? '9' : '8' }}" class="text-center">Tidak ada data cuti</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $cuti->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
