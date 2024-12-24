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
                        <h4>Tambah Pegawai
                            <a href="{{ url('pegawai') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('pegawai') }}" method="POST" enctype="multipart/form-data">
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
                                <label for="">NIP</label>
                                <input type="text" name="nip" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">Nama</label>
                                <input type="text" name="nama" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">Agama</label>
                                <select name="agama" class="form-control">
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam">Islam</option>
                                    <option value="Kristen">Kristen</option>
                                    <option value="Katolik">Katolik</option>
                                    <option value="Budha">Budha</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Konghucu">Konghucu</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-control">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Alamat</label>
                                <input type="text" name="alamat" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">No. HP</label>
                                <input type="text" name="no_hp" class="form-control" />
                            </div>
                            <div class="mb-3"></div>
                                <label for="">Status Pegawai</label>
                                <select name="status_pegawai" class="form-control">
                                    <option value="">Pilih Status Pegawai</option>
                                    <option value="Hakim">Hakim</option>
                                    <option value="CPNS">CPNS (Calon Pegawai Negara Sipil)</option>
                                    <option value="PNS">PNS (Pengangkatan Negara Sipil)</option>
                                    <option value="PPPK">PPPK (Pegawai Pemerintah Dengan Perjanjian Kerja)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Status Perkawinan</label>
                                <select name="status_perkawinan" class="form-control">
                                    <option value="">Pilih Status Perkawinan</option>
                                    <option value="Kawin">Kawin</option>
                                    <option value="Belum Kawin">Belum Kawin</option>
                                    <option value="Duda">Duda</option>
                                    <option value="Janda">Janda</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control" />
                            </div>
                            <div class="mb-3">
                                <label for="">Foto</label>
                                <input type="file" name="foto" class="form-control" accept="image/*" />
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