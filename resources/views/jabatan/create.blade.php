<x-app-layout>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">

                @if ($errors->any())
                <ul class="alert alert-warning">
                    @foreach ($errors->all() as $error)
                        <li>{{$error}}</li>
                    @endforeach
                </ul>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4>Tambah Jabatan
                            <a href="{{ url('jabatan') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('jabatan') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @if ($errors->any())
                                <div class="alert alert-danger">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="mb-3">
                            <div class="mb-3">
                                <label for="">Nama</label>
                                <input type="text" name="nama" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control"></textarea>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Parent Jabatan</label>
                                <select name="parent_uuid" class="form-control">
                                    <option value="">Pilih Parent Jabatan (Opsional)</option>
                                    @foreach ($jabatans as $jabatan)
                                        <option value="{{ $jabatan->uuid }}">{{ $jabatan->nama }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>