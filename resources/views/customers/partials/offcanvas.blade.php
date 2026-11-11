<div class="offcanvas offcanvas-end crud-offcanvas" tabindex="-1" id="offcanvas-customer" data-bs-scroll="true" data-bs-backdrop="true" aria-labelledby="offcanvasCustomerTitle">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasCustomerTitle">Tambah Pelanggan</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>

    <form id="form-customer" novalidate>
        @csrf
        <input type="hidden" name="_method" value="POST">

        <div class="offcanvas-body">
            <div class="row g-3">
                {{-- Prefix & Kode Pelanggan (col-6 berpasangan) --}}
                <div class="col-6">
                    <label for="cust_code_prefix" class="form-label small fw-semibold text-muted">Prefix Kode</label>
                    <input type="text" id="cust_code_prefix" name="code_prefix"
                           class="form-control form-control-sm font-monospace"
                           value="SLX-GS" placeholder="SLX-GS">
                    <div class="form-text" style="font-size:0.72rem;">Ubah untuk saran otomatis.</div>
                </div>

                <div class="col-6">
                    <label for="cust_customer_code" class="form-label small fw-semibold">
                        Kode Pelanggan <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="cust_customer_code" name="customer_code"
                           class="form-control form-control-sm font-monospace text-uppercase"
                           placeholder="SLX-GS-00001" required>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Nama Pelanggan --}}
                <div class="col-12">
                    <label for="cust_name" class="form-label small fw-semibold">
                        Nama Lengkap <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="cust_name" name="name"
                           class="form-control form-control-sm"
                           placeholder="Nama lengkap pelanggan" required>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Nomor Telepon & Status Layanan (col-6) --}}
                <div class="col-6">
                    <label for="cust_phone" class="form-label small fw-semibold">
                        Nomor Telepon
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text text-muted">+</span>
                        <input type="tel" id="cust_phone" name="phone"
                               class="form-control form-control-sm"
                               placeholder="628123456789">
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="cust_service_status" class="form-label small fw-semibold">
                        Status Layanan <span class="text-danger">*</span>
                    </label>
                    <select id="cust_service_status" name="service_status" class="form-select form-select-sm" required>
                        @foreach (\App\Models\Customer::SERVICE_STATUSES as $ss)
                            <option value="{{ $ss }}">{{ $ss }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Paket & Jenis Tagihan (col-6) --}}
                <div class="col-6">
                    <label for="cust_package" class="form-label small fw-semibold">
                        Paket <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="cust_package" name="package" list="cust-package-list"
                           class="form-control form-control-sm" placeholder="Contoh: 10 Mbps" required>
                    <datalist id="cust-package-list">
                        @foreach ($packages ?? [] as $pkg)
                            <option value="{{ $pkg }}">
                        @endforeach
                    </datalist>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="cust_billing_type" class="form-label small fw-semibold">
                        Jenis Tagihan <span class="text-danger">*</span>
                    </label>
                    <input type="text" id="cust_billing_type" name="billing_type" list="cust-billing-list"
                           class="form-control form-control-sm" placeholder="Contoh: Bulanan" required>
                    <datalist id="cust-billing-list">
                        @foreach ($billingTypes ?? [] as $bt)
                            <option value="{{ $bt }}">
                        @endforeach
                    </datalist>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Tanggal Mulai Berlangganan --}}
                <div class="col-12">
                    <label for="cust_subscribed_at" class="form-label small fw-semibold">
                        Tanggal Mulai Berlangganan <span class="text-danger">*</span>
                    </label>
                    <input type="date" id="cust_subscribed_at" name="subscribed_at"
                           class="form-control form-control-sm"
                           value="{{ date('Y-m-d') }}"
                           max="{{ date('Y-m-d') }}" required>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Alamat --}}
                <div class="col-12">
                    <label for="cust_address" class="form-label small fw-semibold">
                        Alamat Lengkap <span class="text-danger">*</span>
                    </label>
                    <textarea id="cust_address" name="address" rows="3"
                              class="form-control form-control-sm"
                              placeholder="Alamat jalan, nomor rumah, RT/RW, kelurahan" required></textarea>
                    <div class="invalid-feedback"></div>
                </div>
            </div>
        </div>

        <div class="offcanvas-footer">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Batal</button>
            <button type="submit" class="btn btn-sm btn-primary px-3" id="btn-submit-customer">
                <i class="bi bi-save me-1"></i>Simpan
            </button>
        </div>
    </form>
</div>
