{{-- Announcement shared form --}}
<div class="mb-3">
    <label for="title" class="form-label fw-semibold">Judul <span class="text-danger">*</span></label>
    <input type="text" id="title" name="title" maxlength="120"
           class="form-control @error('title') is-invalid @enderror"
           value="{{ old('title', $announcement->title ?? '') }}">
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="body" class="form-label fw-semibold">Isi Pengumuman <span class="text-danger">*</span></label>
    <textarea id="body" name="body" rows="5" maxlength="1000"
              class="form-control @error('body') is-invalid @enderror"
              placeholder="Maks 1000 karakter">{{ old('body', $announcement->body ?? '') }}</textarea>
    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Ditampilkan di aplikasi mobile pelanggan.</div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label for="published_at" class="form-label fw-semibold">Tayang Mulai</label>
        <input type="datetime-local" id="published_at" name="published_at"
               class="form-control @error('published_at') is-invalid @enderror"
               value="{{ old('published_at', isset($announcement->published_at) ? $announcement->published_at->format('Y-m-d\TH:i') : '') }}">
        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Kosong = tampil langsung.</div>
    </div>
    <div class="col-md-4">
        <label for="expires_at" class="form-label fw-semibold">Kedaluwarsa</label>
        <input type="datetime-local" id="expires_at" name="expires_at"
               class="form-control @error('expires_at') is-invalid @enderror"
               value="{{ old('expires_at', isset($announcement->expires_at) ? $announcement->expires_at->format('Y-m-d\TH:i') : '') }}">
        @error('expires_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Kosong = tidak kedaluwarsa.</div>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                   name="is_active" value="1"
                   {{ old('is_active', $announcement->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="is_active">Aktif</label>
        </div>
    </div>
</div>
