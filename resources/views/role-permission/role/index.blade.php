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
        <a href="{{ url('users') }}" class="btn btn-warning mx-1">Users</a>
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
                        <h4>
                            Roles
                            @can('create role')
                            <a href="{{ url('roles/create') }}" class="btn btn-primary float-end">Tambah Role</a>
                            @endcan
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('roles/search') }}" method="POST">
                            @csrf
                            <div class="input-group mb-3">
                                <input type="text" name="search" class="form-control" placeholder="Cari Nama Role">
                                <button type="submit" class="btn btn-primary">Cari</button>
                            </div>
                        </form>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Role</th>
                                    <th width="40%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roles as $key => $role)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $role->name }}</td>
                                    <td>
                                        <a href="{{ url('roles/'.$role->uuid.'/give-permissions') }}" class="btn btn-warning">
                                            Atur Role atau Permission
                                        </a>

                                        @can('update role')
                                        <a href="{{ url('roles/'.$role->uuid.'/edit') }}" class="btn btn-success">
                                            Ubah
                                        </a>
                                        @endcan

                                        @can('delete role')
                                        <a href="{{ url('roles/'.$role->uuid.'/delete') }}" class="btn btn-danger mx-2" data-toggle="modal" data-target="#deleteModal">
                                            Hapus
                                        </a>
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