<x-app-layout>

    <div class="container mt-5">
        @if (Auth::user()->hasRole('super-admin'))
        <a href="{{ url('roles') }}" class="btn btn-primary mx-1">Roles</a>
        <a href="{{ url('permissions') }}" class="btn btn-info mx-1">Permissions</a>
        <a href="{{ url('users') }}" class="btn btn-warning mx-1">Users</a>
        @endif
        @if (Auth::user()->hasRole('admin'))
        <a href="{{ url('users') }}" class="btn btn-warning mx-1">Users</a>
        @endif
    </div>

    <div class="container mt-2">
        <div class="row">
            <div class="col-md-12">

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <div class="card mt-3">
                    <div class="card-header">
                        <h4>Permissions
                            @can('create permission')
                            <a href="{{ url('permissions/create') }}" class="btn btn-primary float-end">Tambah Permission</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('permissions/search') }}" method="POST">
                            @csrf
                            <div class="input-group mb-3">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama Permission">
                                <button type="submit" class="btn btn-primary">Cari</button>
                            </div>
                        </form> 
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Permission</th>
                                    <th width="40%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($permissions as $key => $permission)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $permission->name }}</td>
                                    <td>
                                        @can('update permission')
                                        <a href="{{ url('permissions/'.$permission->uuid.'/edit') }}" class="btn btn-success">Ubah</a>
                                        @endcan

                                        @can('delete permission')
                                        <a href="{{ url('permissions/'.$permission->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">Hapus</a>
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