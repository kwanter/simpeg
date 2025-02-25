<x-app-layout>
    @if (Auth::user()->hasRole('super-admin') || Auth::user()->hasRole('admin') || Auth::user()->hasRole('pimpinan') || Auth::user()->hasRole('verifikator'))
    <div class="container mt-5">
        <a href="{{ url('jabatan') }}" class="btn btn-primary mx-1">Jabatan</a>
        <a href="{{ url('pegawai') }}" class="btn btn-warning mx-1">Data Pegawai</a>
    </div>
    @endif
    <div class="container mt-2">
        <div class="row">
            <div class="col-md-12">

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card mt-3">
                    <div class="card-header">
                        <h4>Data Riwayat Jabatan
                            @can('create riwayat jabatan')
                            <a href="{{ url('riwayat_jabatan/'.$pegawai->uuid.'/create') }}" class="btn btn-primary float-end mx-2">Tambah Riwayat Jabatan</a>
                            @endcan
                            <a href="{{ url('pegawai') }}" class="btn btn-secondary float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-4">Nama Pegawai : {{ $pegawai->nama }}</h2>

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="text-center">
                                    <th>No</th>
                                    <th>Nama Jabatan</th>
                                    <th>Satuan Kerja</th>
                                    <th>Tanggal Mulai</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($riwayatJabatan as $index => $riwayat)
                                    <tr class="text-center">
                                        <td class="align-middle">{{ ($riwayatJabatan->currentPage() - 1) * $riwayatJabatan->perPage() + $index + 1 }}</td>
                                        <td class="align-middle">{{ $riwayat->jabatan->nama }}</td>
                                        <td class="align-middle">{{ $riwayat->satuan_kerja }}</td>
                                        <td class="align-middle">{{ $riwayat->tanggal_mulai->format('d-m-Y') }}</td>
                                        <td class="align-middle">{{ $riwayat->keterangan }}</td>
                                        <td class="align-middle">
                                            @can('update riwayat jabatan')
                                            <a href="{{ url('riwayat_jabatan/'.$riwayat->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                            @endcan

                                            @can('delete riwayat jabatan')
                                            <a href="{{ url('riwayat_jabatan/'.$riwayat->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">Tidak ada data riwayat jabatan</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <!-- Add pagination links -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $riwayatJabatan->links() }}
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('layouts.delete')

</x-app-layout>
