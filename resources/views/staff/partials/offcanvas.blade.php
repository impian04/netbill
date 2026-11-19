<x-offcanvas-form
    id="offcanvas-staff"
    formId="form-staff"
    title="Tambah Staf"
    titleId="offcanvasStaffTitle"
    submitId="btn-submit-staff"
    submitText="Simpan"
    cancelText="Batal">

    <div class="row g-3">
        {{-- Nama Lengkap --}}
        <div class="col-12">
            <label for="staff_name" class="form-label small fw-semibold">
                Nama Lengkap <span class="text-danger">*</span>
            </label>
            <input type="text" id="staff_name" name="name" maxlength="100"
                   class="form-control form-control-sm"
                   placeholder="Nama lengkap staf" required>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Alamat Email --}}
        <div class="col-12">
            <label for="staff_email" class="form-label small fw-semibold">
                Alamat Email <span class="text-danger">*</span>
            </label>
            <input type="email" id="staff_email" name="email" maxlength="150"
                   class="form-control form-control-sm"
                   placeholder="nama@jcn.net.id" required>
            <div class="form-text">Email digunakan untuk login dan pengiriman instruksi akses.</div>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Nomor Telepon --}}
        <div class="col-12">
            <label for="staff_phone" class="form-label small fw-semibold">
                Nomor Telepon / WhatsApp
            </label>
            <input type="text" id="staff_phone" name="phone" maxlength="20"
                   class="form-control form-control-sm"
                   placeholder="Contoh: 081234567890">
            <div class="invalid-feedback"></div>
        </div>

        {{-- Peran / Role --}}
        <div class="col-12">
            <label for="staff_role" class="form-label small fw-semibold">
                Peran (Role) <span class="text-danger">*</span>
            </label>
            <select id="staff_role" name="role" class="form-select form-select-sm" required>
                <option value="">-- Pilih Peran --</option>
                @foreach($roles ?? [] as $role)
                    <option value="{{ $role->value }}" data-requires-pop="{{ $role->requiresPop() ? '1' : '0' }}">
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback"></div>
        </div>

        {{-- POP (Wilayah Layanan) --}}
        <div class="col-12" id="staff_pop_group">
            <label for="staff_pop_id" class="form-label small fw-semibold">
                POP (Point of Presence) <span class="text-danger" id="staff_pop_required_star">*</span>
            </label>
            <select id="staff_pop_id" name="pop_id" class="form-select form-select-sm">
                <option value="">-- Pilih POP --</option>
                @foreach($pops ?? [] as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
            <div class="form-text" id="staff_pop_help">Super Admin & NOC bekerja lintas POP (tanpa batasan POP).</div>
            <div class="invalid-feedback"></div>
        </div>

        {{-- Status Aktif --}}
        <div class="col-12">
            <div class="form-check form-switch mt-1">
                <input class="form-check-input" type="checkbox" role="switch" id="staff_is_active" name="is_active" value="1" checked>
                <label class="form-check-label small fw-semibold" for="staff_is_active">Status Aktif</label>
            </div>
            <div class="form-text" id="staff_self_help" style="display:none;">Anda tidak dapat menonaktifkan akun Anda sendiri.</div>
            <div class="invalid-feedback"></div>
        </div>
    </div>
</x-offcanvas-form>
