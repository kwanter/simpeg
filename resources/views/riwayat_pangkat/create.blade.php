<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h4>Tambah Riwayat Pangkat
                            <a href="{{ url('riwayat_pangkat/'.$pegawai->uuid) }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-4">Nama Pegawai : {{ $pegawai->nama }}</h2>
                        <form action="{{ route('riwayat_pangkat.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="pegawai_uuid" value="{{ $pegawai->uuid }}">

                            <div class="mb-3">
                                <label for="pangkat_golongan">Pangkat dan Golongan</label>
                                <select name="pangkat_golongan" class="form-control" required>
                                    <option value="">Pilih Pangkat dan Golongan</option>
                                    @if($pegawai->status_pegawai == 'PNS' || $pegawai->status_pegawai == 'CPNS' || $pegawai->status_pegawai == 'Hakim')
                                        <option value="I/a">Juru Muda (I/a)</option>
                                        <option value="I/b">Juru Muda Tingkat I (I/b)</option>
                                        <option value="I/c">Juru (I/c)</option>
                                        <option value="I/d">Juru Tingkat I (I/d)</option>
                                        <option value="II/a">Pengatur Muda (II/a)</option>
                                        <option value="II/b">Pengatur Muda Tingkat I (II/b)</option>
                                        <option value="II/c">Pengatur (II/c)</option>
                                        <option value="II/d">Pengatur Tingkat I (II/d)</option>
                                        <option value="III/a">Penata Muda (III/a)</option>
                                        <option value="III/b">Penata Muda Tingkat I (III/b)</option>
                                        <option value="III/c">Penata (III/c)</option>
                                        <option value="III/d">Penata Tingkat I (III/d)</option>
                                        <option value="IV/a">Pembina (IV/a)</option>
                                        <option value="IV/b">Pembina Tingkat I (IV/b)</option>
                                        <option value="IV/c">Pembina Utama Muda (IV/c)</option>
                                        <option value="IV/d">Pembina Utama Madya (IV/d)</option>
                                        <option value="IV/e">Pembina Utama (IV/e)</option>
                                    @elseif ($pegawai->status_pegawai == 'PPPK')
                                        <option value="1">Golongan I</option>
                                        <option value="2">Golongan II</option>
                                        <option value="3">Golongan III</option>
                                        <option value="4">Golongan IV</option>
                                        <option value="5">Golongan V</option>
                                        <option value="6">Golongan VI</option>
                                        <option value="7">Golongan VII</option>
                                        <option value="8">Golongan VIII</option>
                                        <option value="9">Golongan IX</option>
                                        <option value="10">Golongan X</option>
                                        <option value="11">Golongan XI</option>
                                        <option value="12">Golongan XII</option>
                                        <option value="13">Golongan XIII</option>
                                        <option value="14">Golongan XIV</option>
                                        <option value="15">Golongan XV</option>
                                        <option value="16">Golongan XVI</option>
                                        <option value="17">Golongan XVII</option>
                                    @else
                                        <option value="ppnpn">PPNPN</option>
                                    @endif
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="tmt">TMT</label>
                                <input type="date" name="tmt" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="nomor_sk">Nomor SK</label>
                                <input type="text" name="nomor_sk" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="tanggal_sk">Tanggal SK</label>
                                <input type="date" name="tanggal_sk" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="3"></textarea>
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
