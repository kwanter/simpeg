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
                        <h4>Data Jabatan
                            @can('create jabatan')
                            <a href="{{ url('jabatan/create') }}" class="btn btn-primary float-end">Tambah Jabatan</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('jabatan/search') }}" method="POST">
                            @csrf
                            <div class="input-group mb-3">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama Jabatan">
                                <button type="submit" class="btn btn-primary">Cari</button>
                            </div>
                        </form>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Jabatan</th>
                                    <th>Deskripsi Jabatan</th>
                                    <th>Parent Jabatan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $sortedJabatans = $jabatans->sortBy('parent_uuid');
                                    $counter = 1;
                                @endphp
                                @foreach ($sortedJabatans as $jabatan)
                                <tr>
                                    <td class="text-center">{{ $counter++ }}</td>
                                    <td>{{ $jabatan->nama }}</td>
                                    <td>{{ $jabatan->deskripsi }}</td>
                                    <td>{{ $jabatan->parent->deskripsi ?? '-' }}</td>
                                    <td>
                                        @can('update jabatan')
                                        <a href="{{ url('jabatan/'.$jabatan->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                        @endcan

                                        @can('delete jabatan')
                                        <a href="{{ url('jabatan/'.$jabatan->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
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