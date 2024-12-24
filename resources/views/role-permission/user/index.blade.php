<x-app-layout>
    @if (Auth::user()->hasRole('super-admin'))
    <div class="container mt-5">
        <a href="{{ url('roles') }}" class="btn btn-primary mx-1">Roles</a>
        <a href="{{ url('permissions') }}" class="btn btn-info mx-1">Permissions</a>
        <a href="{{ url('users') }}" class="btn btn-warning mx-1">Users</a>
    </div>
    @endif
    @if (Auth::user()->hasRole('admin'))
    <div class="container mt-5">
        <a href="{{ url('users') }}" class="btn btn-warning mx-1">Data User</a>
    </div>
    @endif

    <div class="container mt-2">
        <div class="row">
            <div class="col-md-12">

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <div class="card mt-3">
                    <div class="card-header">
                        <h4>Users
                            @can('create user')
                            <a href="{{ url('users/create') }}" class="btn btn-primary float-end">Tambah User</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('users/search') }}" method="POST">
                            @csrf
                            <div class="input-group mb-3">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama atau Email">
                                <button type="submit" class="btn btn-primary">Cari</button>
                            </div>
                        </form>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Roles</th>
                                    <th>Status</th>
                                    <th>Pegawai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $key => $user)
                                <tr>
                                    <td class="text-center">{{ $key + 1 }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if (!empty($user->getRoleNames()))
                                            @foreach ($user->getRoleNames() as $rolename)
                                                <label class="badge bg-primary mx-1" css-tengah>{{ $rolename }}</label>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->status == 1)
                                            <label class="badge bg-success css-tengah">Aktif</label>
                                        @else
                                            <label class="badge bg-danger css-tengah">Tidak Aktif</label>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $pegawai->where('nip', $user->nip)->first()->nama ?? '-' }}
                                    </td>
                                    <td>
                                        @can('update user')
                                        <a href="{{ url('users/'.$user->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                        @endcan

                                        @can('delete user')
                                        <a href="{{ url('users/'.$user->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
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