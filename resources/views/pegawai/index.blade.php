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
                        <h4>Data Pegawai
                            @can('create pegawai')
                            <a href="{{ url('pegawai/create') }}" class="btn btn-primary float-end">Tambah Pegawai</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('pegawai/search') }}" method="POST">
                            @csrf
                            <div class="input-group mb-3">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama Pegawai">
                                <button type="submit" class="btn btn-primary">Cari</button>
                            </div>
                        </form>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr class="text-center">
                                    <th class="align-middle">No</th>
                                    <th class="align-middle">NIP</th>
                                    <th class="align-middle">Nama</th>
                                    <th class="align-middle">Status Pegawai</th>
                                    <th class="align-middle">Jenis Kelamin</th>
                                    <th class="align-middle">Agama</th>
                                    <th class="align-middle">No HP</th>
                                    <th class="align-middle">Foto</th>
                                    <th class="align-middle">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pegawai as $index => $item)
                                <tr class="text-center">
                                    <td class="align-middle">{{ ($pegawai->currentPage() - 1) * $pegawai->perPage() + $index + 1 }}</td>
                                    <td class="align-middle">{{ $item->nip }}</td>
                                    <td class="align-middle">{{ $item->nama }}</td>
                                    <td class="align-middle">{{ $item->status_display }}</td>
                                    <td class="align-middle">{{ $item->jenis_kelamin }}</td>
                                    <td class="align-middle">{{ $item->agama }}</td>
                                    <td class="align-middle">{{ $item->no_hp }}</td>
                                    <td class="align-middle">
                                        <!-- Existing foto code -->
                                    </td>
                                    <td class="align-middle">
                                        @can('view riwayat jabatan')
                                        <a href="{{ url('riwayat_jabatan/'.$item->uuid) }}" class="btn btn-warning">Riwayat Jabatan</a>
                                        @endcan

                                        @can('view riwayat pangkat')
                                        <a href="{{ url('riwayat_pangkat/'.$item->uuid) }}" class="btn btn-info">Riwayat Pangkat</a>
                                        @endcan

                                        @can('view pegawai')
                                        <a href="{{ url('pegawai/'.$item->uuid.'/detail') }}" class="btn btn-primary">Detail</a>
                                        @endcan

                                        @can('update pegawai')
                                        <a href="{{ url('pegawai/'.$item->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                        @endcan

                                        @can('delete pegawai')
                                        <a href="{{ url('pegawai/'.$item->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">Tidak ada data pegawai</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <!-- Add pagination links -->
                        <div class="d-flex justify-content-center mt-3">
                            {{ $pegawai->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('layouts.delete')

</x-app-layout>
