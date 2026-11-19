<x-offcanvas-form id="offcanvas-invoice" formId="form-invoice" title="Tambah Tagihan" titleId="offcanvasInvoiceTitle" submitId="btn-submit-invoice" submitText="Simpan">
    <div class="row g-3">
        {{-- Pelanggan (pilihan untuk daftar tagihan global atau info jika dari detail pelanggan) --}}
                <div class="col-12" id="invoice-customer-field">
                    <label for="inv_customer_id" class="form-label small fw-semibold">
                        Pelanggan <span class="text-danger">*</span>
                    </label>
                    <select id="inv_customer_id" name="customer_id" class="form-select form-select-sm" required>
                        <option value="">-- Pilih Pelanggan --</option>
                        @foreach ($customers ?? [] as $c)
                            <option value="{{ $c->id }}">{{ $c->customer_code }} - {{ $c->name }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Periode & Nominal (col-6) --}}
                <div class="col-6">
                    <label for="inv_period" class="form-label small fw-semibold">
                        Periode <span class="text-danger">*</span>
                    </label>
                    <input type="month" id="inv_period" name="period"
                           class="form-control form-control-sm"
                           value="{{ date('Y-m') }}" required>
                    <div class="invalid-feedback"></div>
                </div>

                <div class="col-6">
                    <label for="inv_deferred_amount" class="form-label small fw-semibold">
                        Nominal (Rp) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input type="number" id="inv_deferred_amount" name="deferred_amount"
                               class="form-control form-control-sm"
                               min="0" step="1000" placeholder="220000" required>
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Status Tagihan --}}
                <div class="col-12">
                    <label class="form-label small fw-semibold d-block">Status Tagihan <span class="text-danger">*</span></label>
                    <div class="d-flex gap-4 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="inv_status_unpaid" value="UNPAID" checked>
                            <label class="form-check-label small" for="inv_status_unpaid">
                                <x-status-badge type="invoice_status" status="UNPAID" />
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="status" id="inv_status_paid" value="PAID">
                            <label class="form-check-label small" for="inv_status_paid">
                                <x-status-badge type="invoice_status" status="PAID" />
                            </label>
                        </div>
                    </div>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Jatuh Tempo (col-6) --}}
                <div class="col-6">
                    <label for="inv_due_date" class="form-label small fw-semibold">
                        Jatuh Tempo
                    </label>
                    <input type="date" id="inv_due_date" name="due_date"
                           class="form-control form-control-sm">
                    <div class="form-text">Opsional (YYYY-MM-DD).</div>
                    <div class="invalid-feedback"></div>
                </div>

                {{-- Referensi Pembayaran (col-6) --}}
                <div class="col-6">
                    <label for="inv_payment_reference" class="form-label small fw-semibold">
                        Referensi Bayar
                    </label>
                    <input type="text" id="inv_payment_reference" name="payment_reference"
                           class="form-control form-control-sm"
                           placeholder="No. transfer/bukti" maxlength="100">
                    <div class="form-text">Opsional jika lunas.</div>
                    <div class="invalid-feedback"></div>
                </div>
    </div>
</x-offcanvas-form>
