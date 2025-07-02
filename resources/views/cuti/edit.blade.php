<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>
                            @if(($cuti->status == 'Disetujui Verifikator' || $cuti->status == 'Disetujui Pimpinan' || $cuti->status == 'Disetujui Atasan Pimpinan') && (empty($cuti->no_surat_cuti) || auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin')))
                                Edit Nomor Surat Cuti
                            @else
                                Edit Pengajuan Cuti
                            @endif
                            <a href="{{ route('cuti.index') }}" class="btn btn-danger float-end">Kembali</a>
                        </h4>
                    </div>
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('cuti.update', $cuti->uuid) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')

                            @if(($cuti->status == 'Disetujui Verifikator' || $cuti->status == 'Disetujui Pimpinan' || $cuti->status == 'Disetujui Atasan Pimpinan') && (empty($cuti->no_surat_cuti) || auth()->user()->hasRole('super-admin') || auth()->user()->hasRole('admin')))
                                <div class="mb-3">
                                    <label for="no_surat_cuti" class="form-label">Nomor Surat Cuti</label>
                                    <input type="text" name="no_surat_cuti" id="no_surat_cuti" class="form-control @error('no_surat_cuti') is-invalid @enderror" value="{{ old('no_surat_cuti', $cuti->no_surat_cuti) }}">
                                    @error('no_surat_cuti')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary">Simpan Nomor Surat</button>
                                </div>
                            @else
                                <!-- Regular edit form fields here -->
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
