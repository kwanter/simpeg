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
                        <h4>Ubah Data Pegawai
                            <a href="{{ url('pegawai') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <form action="{{ url('pegawai/'.$pegawai->uuid) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
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
                                <input type="text" name="nip" class="form-control" value="{{ $pegawai->nip }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">Nama</label>
                                <input type="text" name="nama" class="form-control" value="{{ $pegawai->nama }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">Agama</label>
                                <select name="agama" class="form-control">
                                    <option value="">Pilih Agama</option>
                                    <option value="Islam" {{ $pegawai->agama == 'Islam' ? 'selected' : '' }}>Islam</option>
                                    <option value="Kristen" {{ $pegawai->agama == 'Kristen' ? 'selected' : '' }}>Kristen</option>
                                    <option value="Katolik" {{ $pegawai->agama == 'Katolik' ? 'selected' : '' }}>Katolik</option>
                                    <option value="Budha" {{ $pegawai->agama == 'Budha' ? 'selected' : '' }}>Budha</option>
                                    <option value="Hindu" {{ $pegawai->agama == 'Hindu' ? 'selected' : '' }}>Hindu</option>
                                    <option value="Konghucu" {{ $pegawai->agama == 'Konghucu' ? 'selected' : '' }}>Konghucu</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="form-control">
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="Laki-laki" {{ $pegawai->jenis_kelamin == 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                                    <option value="Perempuan" {{ $pegawai->jenis_kelamin == 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Alamat</label>
                                <input type="text" name="alamat" class="form-control" value="{{ $pegawai->alamat }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">No. HP</label>
                                <input type="text" name="no_hp" class="form-control" value="{{ $pegawai->no_hp }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">Status Pegawai</label>
                                <select name="status_pegawai" class="form-control">
                                    <option value="">Pilih Status Pegawai</option>
                                    <option value="Hakim" {{ $pegawai->status_pegawai == 'Hakim' ? 'selected' : '' }}>Hakim</option>
                                    <option value="CPNS" {{ $pegawai->status_pegawai == 'CPNS' ? 'selected' : '' }}>CPNS (Calon Pegawai Negara Sipil)</option>
                                    <option value="PNS" {{ $pegawai->status_pegawai == 'PNS' ? 'selected' : '' }}>PNS (Pengangkatan Negara Sipil)</option>
                                    <option value="PPPK" {{ $pegawai->status_pegawai == 'PPPK' ? 'selected' : '' }}>PPPK (Pegawai Pemerintah Dengan Perjanjian Kerja)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Status Perkawinan</label>
                                <select name="status_perkawinan" class="form-control">
                                    <option value="">Pilih Status Perkawinan</option>
                                    <option value="Kawin" {{ $pegawai->status_perkawinan == 'Kawin' ? 'selected' : '' }}>Kawin</option>
                                    <option value="Belum Kawin" {{ $pegawai->status_perkawinan == 'Belum Kawin' ? 'selected' : '' }}>Belum Kawin</option>
                                    <option value="Duda" {{ $pegawai->status_perkawinan == 'Duda' ? 'selected' : '' }}>Duda</option>
                                    <option value="Janda" {{ $pegawai->status_perkawinan == 'Janda' ? 'selected' : '' }}>Janda</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="">Tempat Lahir</label>
                                <input type="text" name="tempat_lahir" class="form-control" value="{{ $pegawai->tempat_lahir }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">Tanggal Lahir</label>
                                <input type="date" name="tanggal_lahir" class="form-control" value="{{ $pegawai->tanggal_lahir }}" />
                            </div>
                            <div class="mb-3">
                                <label for="">Foto</label>
                                <input type="file" name="foto" class="form-control" accept="image/*" />
                                @if($pegawai->foto)
                                    <img src="{{ asset('storage/pic/pegawai/' . $pegawai->foto) }}" alt="Current Photo" class="mt-2" style="max-width: 200px;">
                                @endif
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