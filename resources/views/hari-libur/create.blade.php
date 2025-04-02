<x-app-layout>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Tambah Hari Libur
                            <a href="{{ route('hari-libur.index') }}" class="btn btn-danger float-end">Kembali</a>
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

                        <form action="{{ route('hari-libur.store') }}" method="POST">
                            @csrf

                            <div id="hari-libur-container">
                                <div class="hari-libur-item border p-3 mb-3 rounded">
                                    <div class="mb-3">
                                        <label for="tanggal_0" class="form-label">Tanggal</label>
                                        <input type="date" name="hari_libur[0][tanggal]" id="tanggal_0" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="nama_0" class="form-label">Nama Hari Libur</label>
                                        <input type="text" name="hari_libur[0][nama]" id="nama_0" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="jenis_0" class="form-label">Jenis</label>
                                        <select name="hari_libur[0][jenis]" id="jenis_0" class="form-control" required>
                                            <option value="">Pilih Jenis</option>
                                            @foreach($jenisLibur as $jenis)
                                                <option value="{{ $jenis }}">{{ $jenis }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="keterangan_0" class="form-label">Keterangan</label>
                                        <textarea name="hari_libur[0][keterangan]" id="keterangan_0" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="button" id="add-more" class="btn btn-success">Tambah Hari Libur Lainnya</button>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">Simpan Semua</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let counter = 1;
            const container = document.getElementById('hari-libur-container');
            const addButton = document.getElementById('add-more');

            addButton.addEventListener('click', function() {
                const template = `
                    <div class="hari-libur-item border p-3 mb-3 rounded">
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-danger remove-item">Hapus</button>
                        </div>
                        <div class="mb-3">
                            <label for="tanggal_${counter}" class="form-label">Tanggal</label>
                            <input type="date" name="hari_libur[${counter}][tanggal]" id="tanggal_${counter}" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="nama_${counter}" class="form-label">Nama Hari Libur</label>
                            <input type="text" name="hari_libur[${counter}][nama]" id="nama_${counter}" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="jenis_${counter}" class="form-label">Jenis</label>
                            <select name="hari_libur[${counter}][jenis]" id="jenis_${counter}" class="form-control" required>
                                <option value="">Pilih Jenis</option>
                                @foreach($jenisLibur as $jenis)
                                    <option value="{{ $jenis }}">{{ $jenis }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="keterangan_${counter}" class="form-label">Keterangan</label>
                            <textarea name="hari_libur[${counter}][keterangan]" id="keterangan_${counter}" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                `;

                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = template;
                container.appendChild(tempDiv.firstElementChild);

                counter++;

                // Add event listeners to remove buttons
                document.querySelectorAll('.remove-item').forEach(button => {
                    button.addEventListener('click', function() {
                        this.closest('.hari-libur-item').remove();
                    });
                });
            });
        });
    </script>
</x-app-layout>