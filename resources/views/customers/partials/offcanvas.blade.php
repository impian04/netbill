<x-offcanvas-form id="offcanvas-customer" formId="form-customer" title="Tambah Pelanggan" titleId="offcanvasCustomerTitle" submitId="btn-submit-customer" submitText="Simpan">
    <div class="row g-3">
        {{-- POP & Kode Pelanggan (col-6 berpasangan) --}}
                <div class="col-6">
                    <label for="cust_pop_id" class="form-label small fw-semibold">
                        POP <span class="text-danger">*</span>
                    </label>
                    @if(auth()->user()->isSuperAdmin())
                        <select name="pop_id" id="cust_pop_id" class="form-select form-select-sm" required>
                            @foreach($pops ?? [] as $pop)
                                <option value="{{ $pop->id }}">POP {{ $pop->code }} - {{ $pop->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="hidden" name="pop_id" id="cust_pop_id" value="{{ auth()->user()->pop_id }}">
                        <input type="text" class="form-control form-control-sm bg-light" value="POP {{ auth()->user()->pop?->code }} - {{ auth()->user()->pop?->name }}" readonly>
                    @endif
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="cust_customer_code" class="form-label small fw-semibold">
                        Kode Pelanggan
                    </label>
                    <input type="text" id="cust_customer_code" name="customer_code"
                           class="form-control form-control-sm font-monospace text-uppercase bg-light"
                           placeholder="JCN-KTU-0001 (Otomatis)" readonly>
                    <div class="form-text">Dibuat otomatis oleh sistem.</div>
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
                            <option value="{{ $ss }}" {{ $ss === 'PENDING' ? 'selected' : '' }}>{{ $ss }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- ODC & ODP (col-6 berpasangan) --}}
                <div class="col-6">
                    <label for="cust_odc_id" class="form-label small fw-semibold">
                        ODC
                    </label>
                    <select name="odc_id" id="cust_odc_id" class="form-select form-select-sm">
                        <option value="">Pilih ODC</option>
                        @foreach ($odcs ?? [] as $odc)
                            <option value="{{ $odc->id }}" data-pop-id="{{ $odc->pop_id }}">{{ $odc->name }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="cust_odp_id" class="form-label small fw-semibold">
                        ODP
                    </label>
                    <select name="odp_id" id="cust_odp_id" class="form-select form-select-sm" disabled>
                        <option value="">Pilih ODP (Pilih ODC dahulu)</option>
                        @foreach ($odps ?? [] as $odp)
                            <option value="{{ $odp->id }}" data-pop-id="{{ $odp->pop_id }}" data-odc-id="{{ $odp->odc_id }}">{{ $odp->name }}</option>
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

                {{-- Koordinat Lokasi --}}
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="cust_coordinates" class="form-label small fw-semibold mb-0">
                            Koordinat Lokasi (Latitude, Longitude)
                        </label>
                        <x-btn variant="neutral" size="sm" id="btn-get-current-loc" class="py-0 px-2 fs-xs">
                            Gunakan Lokasi Saya
                        </x-btn>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" id="cust_coordinates" name="coordinates" class="form-control form-control-sm font-monospace" placeholder="Contoh: 0.5071, 101.4478">
                        <x-btn variant="neutral" size="sm" href="#" target="_blank" rel="noopener noreferrer" class="d-none" id="btn-open-map-link" title="Buka di Google Maps">
                            Buka di Peta
                        </x-btn>
                    </div>
                    <div class="form-text">Derajat desimal: lat (-11.5 s/d 6.5), lng (94.0 s/d 141.5). Pisahkan koma atau spasi.</div>
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

                {{-- Berkas KTP --}}
                <div class="col-12">
                    <label for="cust_ktp_file" class="form-label small fw-semibold">
                        Berkas KTP (Foto/Scan)
                    </label>
                    <div id="cust_ktp_preview_wrapper" class="mb-2 p-2 border rounded bg-light d-none align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <div>
                                <a href="#" target="_blank" rel="noopener noreferrer" id="cust_ktp_view_link" class="small fw-semibold text-decoration-none">Lihat Berkas KTP Tersimpan</a>
                                <div class="text-muted fs-xs">Tersimpan privat & aman.</div>
                            </div>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="cust_delete_ktp" name="delete_ktp" value="1">
                            <label class="form-check-label small text-danger" for="cust_delete_ktp">Hapus KTP</label>
                        </div>
                    </div>
                    <input type="file" id="cust_ktp_file" name="ktp_file" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text fs-xs">Format JPG, PNG, atau WebP (maks. 3 MB). Metadata lokasi/EXIF otomatis dihapus.</div>
                    <div class="invalid-feedback"></div>
                </div>
    </div>
</x-offcanvas-form>
