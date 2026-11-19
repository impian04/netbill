<x-offcanvas-form
    id="offcanvas-pop"
    formId="form-pop"
    title="Tambah POP"
    titleId="offcanvasPopTitle"
    submitId="btn-submit-pop"
    submitText="Simpan"
    cancelText="Batal">

    <div class="row g-3">
        {{-- Kode POP --}}
        <div class="col-12">
            <label for="pop_code" class="form-label small fw-semibold">
                Kode POP <span class="text-danger">*</span>
            </label>
            <input type="text" id="pop_code" name="code" maxlength="4"
                   class="form-control form-control-sm text-uppercase"
                   placeholder="Contoh: KTU, EP, BKN (2-4 huruf kapital)" required>
            <div class="form-text" id="pop_code_help">2-4 huruf kapital, unik. Tidak dapat diubah setelah memiliki pelanggan.</div>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Nama POP --}}
        <div class="col-12">
            <label for="pop_name" class="form-label small fw-semibold">
                Nama POP <span class="text-danger">*</span>
            </label>
            <input type="text" id="pop_name" name="name" maxlength="100"
                   class="form-control form-control-sm"
                   placeholder="Contoh: POP Koto Tuo" required>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Kode VA --}}
        <div class="col-12">
            <label for="pop_va_code" class="form-label small fw-semibold">
                Kode VA (Virtual Account)
            </label>
            <input type="text" id="pop_va_code" name="va_code" maxlength="3"
                   class="form-control form-control-sm"
                   placeholder="2-3 digit angka (opsional)">
            <div class="form-text">Nomor awalan virtual account per POP (2-3 digit angka unik).</div>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Status Aktif --}}
        <div class="col-12">
            <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" role="switch" id="pop_is_active" name="is_active" value="1" checked>
                <label class="form-check-label small fw-semibold" for="pop_is_active">Status Aktif</label>
            </div>
        </div>
    </div>
</x-offcanvas-form>
