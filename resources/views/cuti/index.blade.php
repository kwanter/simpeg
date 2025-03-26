@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Data Cuti</h5>
                    @can('create cuti')
                    <a href="{{ route('cuti.create') }}" class="btn btn-primary">Ajukan Cuti</a>
                    @endcan
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Pegawai</th>
                                    <th>Jenis Cuti</th>
                                    <th>Tanggal</th>
                                    <th>Lama Cuti</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cuti as $index => $item)
                                <tr>
                                    <td>{{ $index + $cuti->firstItem() }}</td>
                                    <td>{{ $item->pegawai->nama }}</td>
                                    <td>{{ $item->jenis_cuti }}</td>
                                    <td>{{ \Carbon\Carbon::parse($item->tanggal_mulai)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($item->tanggal_selesai)->format('d/m/Y') }}</td>
                                    <td>{{ $item->lama_cuti }} hari</td>
                                    <td>
                                        @if($item->status == 'Pending')
                                            <span class="badge bg-warning">Pending</span>
                                        @elseif($item->status == 'Disetujui Verifikator')
                                            <span class="badge bg-info">Disetujui Verifikator</span>
                                        @elseif($item->status == 'Ditolak Verifikator')
                                            <span class="badge bg-danger">Ditolak Verifikator</span>
                                        @elseif($item->status == 'Disetujui Pimpinan')
                                            <span class="badge bg-success">Disetujui Pimpinan</span>
                                        @elseif($item->status == 'Ditolak Pimpinan')
                                            <span class="badge bg-danger">Ditolak Pimpinan</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('cuti.show', $item->uuid) }}" class="btn btn-info btn-sm">Detail</a>

                                            @if($item->status == 'Pending')
                                                @can('update cuti')
                                                <a href="{{ route('cuti.edit', $item->uuid) }}" class="btn btn-warning btn-sm">Edit</a>
                                                @endcan

                                                @can('delete cuti')
                                                <form action="{{ route('cuti.destroy', $item->uuid) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                                </form>
                                                @endcan

                                                @can('verifikasi cuti')
                                                <a href="{{ route('cuti.verifikasi', $item->uuid) }}" class="btn btn-primary btn-sm">Verifikasi</a>
                                                @endcan
                                            @endif

                                            @if($item->status == 'Disetujui Verifikator')
                                                @can('pimpinan cuti')
                                                <a href="{{ route('cuti.verifikasi-pimpinan', $item->uuid) }}" class="btn btn-primary btn-sm">Verifikasi Pimpinan</a>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $cuti->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection