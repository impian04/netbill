{{-- Shared invoice form fields --}}
<div class="row g-3">

    <div class="col-md-4">
        <label for="period" class="form-label fw-semibold">
            Periode <span class="text-danger">*</span>
        </label>
        <input type="month" id="period" name="period"
               class="form-control @error('period') is-invalid @enderror"
               value="{{ old('period', isset($invoice) ? $invoice->period->format('Y-m') : '') }}"
               max="{{ date('Y-m') }}">
        @error('period')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="deferred_amount" class="form-label fw-semibold">
            Nominal Ditangguhkan (Rp) <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text">Rp</span>
            <input type="number" id="deferred_amount" name="deferred_amount"
                   class="form-control @error('deferred_amount') is-invalid @enderror"
                   value="{{ old('deferred_amount', $invoice->deferred_amount ?? '') }}"
                   min="0" step="1000" placeholder="220000">
            @error('deferred_amount')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">Nominal tagihan ke pelanggan.</div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 mt-1">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="status" id="status-unpaid"
                       value="UNPAID" {{ old('status', $invoice->status ?? 'UNPAID') === 'UNPAID' ? 'checked' : '' }}>
                <label class="form-check-label" for="status-unpaid">
                    <span class="badge badge-unpaid px-2 py-1">Belum Bayar</span>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="status" id="status-paid"
                       value="PAID" {{ old('status', $invoice->status ?? '') === 'PAID' ? 'checked' : '' }}>
                <label class="form-check-label" for="status-paid">
                    <span class="badge badge-paid px-2 py-1">Lunas</span>
                </label>
            </div>
        </div>
        @error('status')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="due_date" class="form-label fw-semibold">Jatuh Tempo</label>
        <input type="date" id="due_date" name="due_date"
               class="form-control @error('due_date') is-invalid @enderror"
               value="{{ old('due_date', isset($invoice->due_date) ? $invoice->due_date->format('Y-m-d') : '') }}">
        @error('due_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label for="payment_reference" class="form-label fw-semibold">Referensi Pembayaran</label>
        <input type="text" id="payment_reference" name="payment_reference"
               class="form-control @error('payment_reference') is-invalid @enderror"
               value="{{ old('payment_reference', $invoice->payment_reference ?? '') }}"
               placeholder="Nomor transfer / bukti pembayaran" maxlength="100">
        @error('payment_reference')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Diisi jika pembayaran sudah diterima (opsional).</div>
    </div>

    @isset($invoice)
        @if ($invoice->invoice_number)
            <div class="col-12">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small fw-semibold">No. Tagihan:</span>
                    <code class="small text-primary">{{ $invoice->invoice_number }}</code>
                    @if ($invoice->paid_at)
                        <span class="text-muted small">· Dibayar: {{ tgl_indo($invoice->paid_at) }} {{ $invoice->paid_at->format('H:i') }}</span>
                    @endif
                </div>
            </div>
        @endif
    @endisset

</div>
