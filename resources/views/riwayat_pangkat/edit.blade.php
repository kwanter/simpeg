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
                        <h4>Edit Riwayat Pangkat
                            <a href="{{ url('riwayat_pangkat/'.$pegawai->uuid) }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        <h2 class="fw-bold mb-4">Nama Pegawai : {{ $pegawai->nama }}</h2>
                        <form action="{{ url('riwayat_pangkat/'.$riwayatPangkat->uuid) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="pegawai_uuid" value="{{ $pegawai->uuid }}">

                            <div class="mb-3">
                                <label for="pangkat_golongan">Pangkat dan Golongan</label>
                                <select name="pangkat_golongan" class="form-control" required onchange="var selected = this.value.split('|'); document.getElementById('nama_pangkat').value = selected[0]; document.getElementById('golongan').value = selected[1];">
                                    <option value="">Pilih Pangkat dan Golongan</option>
                                    @if($pegawai->status_pegawai == 'PNS' || $pegawai->status_pegawai == 'CPNS' || $pegawai->status_pegawai == 'Hakim')
                                        <option value="I/a" {{ ($riwayatPangkat->pangkat_golongan == 'I/a') ? 'selected' : '' }}>Juru Muda (I/a)</option>
                                        <option value="I/b" {{ ($riwayatPangkat->pangkat_golongan == 'I/b') ? 'selected' : '' }}>Juru Muda Tingkat I (I/b)</option>
                                        <option value="I/c" {{ ($riwayatPangkat->pangkat_golongan == 'I/c') ? 'selected' : '' }}>Juru (I/c)</option>
                                        <option value="I/d" {{ ($riwayatPangkat->pangkat_golongan == 'I/d') ? 'selected' : '' }}>Juru Tingkat I (I/d)</option>
                                        <option value="II/a" {{ ($riwayatPangkat->pangkat_golongan == 'II/a') ? 'selected' : '' }}>Pengatur Muda (II/a)</option>
                                        <option value="II/b" {{ ($riwayatPangkat->pangkat_golongan == 'II/b') ? 'selected' : '' }}>Pengatur Muda Tingkat I (II/b)</option>
                                        <option value="II/c" {{ ($riwayatPangkat->pangkat_golongan == 'II/c') ? 'selected' : '' }}>Pengatur (II/c)</option>
                                        <option value="II/d" {{ ($riwayatPangkat->pangkat_golongan == 'II/d') ? 'selected' : '' }}>Pengatur Tingkat I (II/d)</option>
                                        <option value="III/a" {{ ($riwayatPangkat->pangkat_golongan == 'III/a') ? 'selected' : '' }}>Penata Muda (III/a)</option>
                                        <option value="III/b" {{ ($riwayatPangkat->pangkat_golongan == 'III/b') ? 'selected' : '' }}>Penata Muda Tingkat I (III/b)</option>
                                        <option value="III/c" {{ ($riwayatPangkat->pangkat_golongan == 'III/c') ? 'selected' : '' }}>Penata (III/c)</option>
                                        <option value="III/d" {{ ($riwayatPangkat->pangkat_golongan == 'III/d') ? 'selected' : '' }}>Penata Tingkat I (III/d)</option>
                                        <option value="IV/a" {{ ($riwayatPangkat->pangkat_golongan == 'IV/a') ? 'selected' : '' }}>Pembina (IV/a)</option>
                                        <option value="IV/b" {{ ($riwayatPangkat->pangkat_golongan == 'IV/b') ? 'selected' : '' }}>Pembina Tingkat I (IV/b)</option>
                                        <option value="IV/c" {{ ($riwayatPangkat->pangkat_golongan == 'IV/c') ? 'selected' : '' }}>Pembina Utama Muda (IV/c)</option>
                                        <option value="IV/d" {{ ($riwayatPangkat->pangkat_golongan == 'IV/d') ? 'selected' : '' }}>Pembina Utama Madya (IV/d)</option>
                                        <option value="IV/e" {{ ($riwayatPangkat->pangkat_golongan == 'IV/e') ? 'selected' : '' }}>Pembina Utama (IV/e)</option>
                                    @elseif ($pegawai->status_pegawai == 'PPPK')
                                        <option value="1" {{ ($riwayatPangkat->pangkat_golongan == '1') ? 'selected' : '' }}>Golongan I</option>
                                        <option value="2" {{ ($riwayatPangkat->pangkat_golongan == '2') ? 'selected' : '' }}>Golongan II</option>
                                        <option value="3" {{ ($riwayatPangkat->pangkat_golongan == '3') ? 'selected' : '' }}>Golongan III</option>
                                        <option value="4" {{ ($riwayatPangkat->pangkat_golongan == '4') ? 'selected' : '' }}>Golongan IV</option>
                                        <option value="5" {{ ($riwayatPangkat->pangkat_golongan == '5') ? 'selected' : '' }}>Golongan V</option>
                                        <option value="6" {{ ($riwayatPangkat->pangkat_golongan == '6') ? 'selected' : '' }}>Golongan VI</option>
                                        <option value="7" {{ ($riwayatPangkat->pangkat_golongan == '7') ? 'selected' : '' }}>Golongan VII</option>
                                        <option value="8" {{ ($riwayatPangkat->pangkat_golongan == '8') ? 'selected' : '' }}>Golongan VIII</option>
                                        <option value="9" {{ ($riwayatPangkat->pangkat_golongan == '9') ? 'selected' : '' }}>Golongan IX</option>
                                        <option value="9" {{ ($riwayatPangkat->pangkat_golongan == '9') ? 'selected' : '' }}>Golongan IX</option>
                                        <option value="10" {{ ($riwayatPangkat->pangkat_golongan == '10') ? 'selected' : '' }}>Golongan X</option>
                                        <option value="11" {{ ($riwayatPangkat->pangkat_golongan == '11') ? 'selected' : '' }}>Golongan XI</option>
                                        <option value="12" {{ ($riwayatPangkat->pangkat_golongan == '12') ? 'selected' : '' }}>Golongan XII</option>
                                        <option value="13" {{ ($riwayatPangkat->pangkat_golongan == '13') ? 'selected' : '' }}>Golongan XIII</option>
                                        <option value="14" {{ ($riwayatPangkat->pangkat_golongan == '14') ? 'selected' : '' }}>Golongan XIV</option>
                                        <option value="15" {{ ($riwayatPangkat->pangkat_golongan == '15') ? 'selected' : '' }}>Golongan XV</option>
                                        <option value="16" {{ ($riwayatPangkat->pangkat_golongan == '16') ? 'selected' : '' }}>Golongan XVI</option>
                                        <option value="17" {{ ($riwayatPangkat->pangkat_golongan == '17') ? 'selected' : '' }}>Golongan XVII</option>
                                    @else
                                        <option value="ppnpn">PPNPN</option>
                                    @endif
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="tmt">TMT</label>
                                <input type="date" name="tmt" value="{{ $riwayatPangkat->tmt->format('Y-m-d') }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="nomor_sk">Nomor SK</label>
                                <input type="text" name="nomor_sk" value="{{ $riwayatPangkat->nomor_sk }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="tanggal_sk">Tanggal SK</label>
                                <input type="date" name="tanggal_sk" value="{{ $riwayatPangkat->tanggal_sk->format('Y-m-d') }}" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="keterangan">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="3">{{ $riwayatPangkat->keterangan }}</textarea>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
