{{-- Shared form fields for customer create/edit --}}
<div class="row g-3">

    {{-- Kode Pelanggan --}}
    <div class="col-md-3">
        <label for="code_prefix" class="form-label fw-semibold">Prefix Kode</label>
        <input type="text" id="code_prefix" name="code_prefix"
               class="form-control font-monospace"
               value="{{ old('code_prefix', isset($customer) ? '' : 'SLX-GS') }}"
               placeholder="SLX-GS"
               oninput="suggestCode(this.value)"
               {{ isset($customer) ? 'disabled' : '' }}>
        <div class="form-text">Ubah prefix untuk saran kode otomatis.</div>
    </div>

    <div class="col-md-3">
        <label for="customer_code" class="form-label fw-semibold">
            Kode Pelanggan <span class="text-danger">*</span>
        </label>
        <input type="text" id="customer_code" name="customer_code"
               class="form-control font-monospace @error('customer_code') is-invalid @enderror"
               value="{{ old('customer_code', $customer->customer_code ?? $nextCode ?? '') }}"
               placeholder="SLX-GS-00001"
               style="text-transform:uppercase;"
               oninput="this.value=this.value.toUpperCase()">
        @error('customer_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Huruf kapital, angka, tanda hubung.</div>
    </div>

    <div class="col-md-6">
        <label for="name" class="form-label fw-semibold">
            Nama Pelanggan <span class="text-danger">*</span>
        </label>
        <input type="text" id="name" name="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $customer->name ?? '') }}"
               placeholder="Nama lengkap pelanggan">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="phone" class="form-label fw-semibold">
            Nomor Telepon {{ isset($customer) ? '' : '<span class="text-danger">*</span>' }}
        </label>
        <div class="input-group">
            <span class="input-group-text text-muted">+</span>
            <input type="tel" id="phone" name="phone"
                   class="form-control @error('phone') is-invalid @enderror"
                   value="{{ old('phone', $customer->phone ?? '') }}"
                   placeholder="628123456789">
        </div>
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Format: 62xxxxxxxxxx (tanpa + atau 0 di depan).</div>
    </div>

    <div class="col-md-4">
        <label for="service_status" class="form-label fw-semibold">
            Status Layanan <span class="text-danger">*</span>
        </label>
        <select id="service_status" name="service_status"
                class="form-select @error('service_status') is-invalid @enderror">
            @foreach (\App\Models\Customer::SERVICE_STATUSES as $ss)
                <option value="{{ $ss }}" @selected(old('service_status', $customer->service_status ?? 'ACTIVE') === $ss)>
                    {{ $ss }}
                </option>
            @endforeach
        </select>
        @error('service_status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="subscribed_at" class="form-label fw-semibold">
            Tanggal Mulai Berlangganan <span class="text-danger">*</span>
        </label>
        <input type="date" id="subscribed_at" name="subscribed_at"
               class="form-control @error('subscribed_at') is-invalid @enderror"
               value="{{ old('subscribed_at', isset($customer) ? $customer->subscribed_at->format('Y-m-d') : '') }}"
               max="{{ date('Y-m-d') }}">
        @error('subscribed_at')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label fw-semibold">
            Alamat <span class="text-danger">*</span>
        </label>
        <textarea id="address" name="address" rows="2"
                  class="form-control @error('address') is-invalid @enderror"
                  placeholder="Alamat lengkap pelanggan">{{ old('address', $customer->address ?? '') }}</textarea>
        @error('address')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="package" class="form-label fw-semibold">
            Paket <span class="text-danger">*</span>
        </label>
        <input type="text" id="package" name="package" list="package-list"
               class="form-control @error('package') is-invalid @enderror"
               value="{{ old('package', $customer->package ?? '') }}"
               placeholder="Contoh: 10 Mbps">
        <datalist id="package-list">
            @foreach ($packages as $pkg)
                <option value="{{ $pkg }}">
            @endforeach
        </datalist>
        @error('package')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="billing_type" class="form-label fw-semibold">
            Jenis Tagihan <span class="text-danger">*</span>
        </label>
        <input type="text" id="billing_type" name="billing_type" list="billing-type-list"
               class="form-control @error('billing_type') is-invalid @enderror"
               value="{{ old('billing_type', $customer->billing_type ?? '') }}"
               placeholder="Contoh: Bulanan">
        <datalist id="billing-type-list">
            @foreach ($billingTypes as $bt)
                <option value="{{ $bt }}">
            @endforeach
        </datalist>
        @error('billing_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

</div>
