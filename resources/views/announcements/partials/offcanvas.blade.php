<div class="offcanvas offcanvas-end crud-offcanvas" tabindex="-1" id="offcanvas-announcement" data-bs-scroll="true" data-bs-backdrop="true" aria-labelledby="offcanvasAnnouncementTitle">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasAnnouncementTitle">Buat Pengumuman</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <form id="form-announcement" novalidate>
        @csrf
        <input type="hidden" name="_method" value="POST">

        <div class="offcanvas-body">
            <div class="row g-3">
                {{-- Judul --}}
                <div class="col-12">
                    <label for="ann_title" class="form-label small fw-semibold">
                        Judul Pengumuman <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="ann_title" name="title" maxlength="120"
                           class="form-control form-control-sm"
                           placeholder="Contoh: Pemeliharaan Jaringan Rutin" required>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Isi Pengumuman --}}
                <div class="col-12">
                    <label for="ann_body" class="form-label small fw-semibold">
                        Isi Pengumuman <span class="text-danger">*</span>
                    </label>
                    <textarea id="ann_body" name="body" rows="5" maxlength="1000"
                              class="form-control form-control-sm"
                              placeholder="Tulis pesan pengumuman untuk pelanggan (maks. 1000 karakter)" required></textarea>
                    <div class="form-text" style="font-size:0.72rem;">Ditampilkan pada aplikasi mobile pelanggan.</div>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Tayang Mulai & Kedaluwarsa (col-6) --}}
                <div class="col-6">
                    <label for="ann_published_at" class="form-label small fw-semibold">
                        Tayang Mulai
                    </label>
                    <input type="datetime-local" id="ann_published_at" name="published_at"
                           class="form-control form-control-sm">
                    <div class="form-text" style="font-size:0.72rem;">Kosong = tampil langsung.</div>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="ann_expires_at" class="form-label small fw-semibold">
                        Kedaluwarsa
                    </label>
                    <input type="datetime-local" id="ann_expires_at" name="expires_at"
                           class="form-control form-control-sm">
                    <div class="form-text" style="font-size:0.72rem;">Kosong = selamanya.</div>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Status Aktif (Switch) --}}
                <div class="col-12">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="ann_is_active" name="is_active" value="1" checked>
                        <label class="form-check-label small fw-semibold" for="ann_is_active">Status Aktif</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-submit-announcement">
                <i class="bi bi-save me-1"></i>Simpan
            </button>
        </div>
    </form>
</div>
