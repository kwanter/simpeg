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
                                <tr>
                                    <th>No</th>
                                    <th>NIP</th>
                                    <th>Nama</th>
                                    <th>Agama</th>
                                    <th>Jenis Kelamin</th>
                                    <th>Status Perkawinan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pegawai as $key => $user)
                                <tr>
                                    <td class="text-center">{{ $key + 1 }}</td>
                                    <td>{{ $user->nip }}</td>
                                    <td>{{ $user->nama }}</td>
                                    <td>{{ $user->agama }}</td>
                                    <td>{{ $user->jenis_kelamin }}</td>
                                    <td>{{ $user->status_perkawinan }}</td>
                                    <td>
                                        @can('view riwayat jabatan')
                                        <a href="{{ url('riwayat_jabatan/'.$user->uuid) }}" class="btn btn-warning">Riwayat Jabatan</a>
                                        @endcan

                                        @can('view pegawai')
                                        <a href="{{ url('pegawai/'.$user->uuid.'/detail') }}" class="btn btn-primary">Detail</a>
                                        @endcan

                                        @can('update pegawai')
                                        <a href="{{ url('pegawai/'.$user->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                        @endcan

                                        @can('delete pegawai')
                                        <a href="{{ url('pegawai/'.$user->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('layouts.delete')

</x-app-layout>
