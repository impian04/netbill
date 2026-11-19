@extends('layouts.app')

@section('title', 'Fee Marketing')

@section('content')

{{-- ── Page Header ─────────────────────────────────────────────── --}}
<x-page-header title="Fee Komisi Marketing" subtitle="Kelola dan salurkan komisi pemasaran pelanggan referral baru">
    <x-slot:actions>
        <x-btn variant="export" href="{{ route('export.marketing-fees') }}" id="btn-export-fees">Ekspor XLSX</x-btn>
        <x-btn variant="approve" class="d-none" id="btn-bulk-pay"><span id="bulk-pay-label">Tandai Dibayar</span></x-btn>
    </x-slot:actions>
</x-page-header>

{{-- ── Kartu Ringkasan (Stat Cards) ────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card stat-card-warning">
            <div class="stat-icon stat-icon-warning">
                </div>
            <div class="flex-grow-1">
                <div class="stat-value text-warning" id="sum-pending-amount">Rp0</div>
                <div class="stat-label">Menunggu Pembayaran (PENDING)</div>
                <div class="stat-help" id="sum-pending-count">0 transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card stat-card-success">
            <div class="stat-icon stat-icon-success">
                </div>
            <div class="flex-grow-1">
                <div class="stat-value text-success" id="sum-paid-amount">Rp0</div>
                <div class="stat-label">Telah Dibayar (PAID)</div>
                <div class="stat-help" id="sum-paid-count">0 transaksi</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="stat-card stat-card-primary">
            <div class="stat-icon stat-icon-primary">
                </div>
            <div class="flex-grow-1">
                <div class="stat-value text-primary" id="sum-total-amount">Rp0</div>
                <div class="stat-label">Total Fee Keseluruhan</div>
                <div class="stat-help" id="sum-total-count">0 transaksi</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Kartu Filter (row g-2) ──────────────────────────────────── --}}
<x-filter-card>
    <form id="filter-fees-form" class="row g-2 align-items-center">
        <div class="col-12 col-lg-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"></span>
                <input type="search" id="filter-fee-search" name="search" class="form-control form-control-sm"
                       placeholder="Cari marketing / pelanggan...">
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <input type="month" class="form-control form-control-sm" name="month" id="filter-fee-month" value="{{ date('Y-m') }}">
        </div>

        <div class="col-6 col-lg-2">
            <select name="status" id="filter-fee-status" class="form-select form-select-sm">
                <option value="">Semua Status</option>
                <option value="PENDING">Menunggu (PENDING)</option>
                <option value="PAID">Dibayar (PAID)</option>
            </select>
        </div>

        <div class="col-6 col-lg-2">
            <select name="marketing_user_id" id="filter-fee-marketing" class="form-select form-select-sm">
                <option value="">Semua Marketing</option>
                @foreach($marketingUsers as $mUser)
                    <option value="{{ $mUser->id }}">{{ $mUser->name }} ({{ $mUser->pop?->code }})</option>
                @endforeach
            </select>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="col-6 col-lg-2">
            <select name="pop_id" id="filter-fee-pop" class="form-select form-select-sm">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}">{{ $pop->code }} - {{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="col-6 col-lg-1 ms-lg-auto text-end">
            <x-btn variant="neutral" id="btn-reset-fee-filter" class="w-100">Reset</x-btn>
        </div>
    </form>
</x-filter-card>

{{-- ── Kartu Tabel DataTables Server-Side ───────────────────────── --}}
<x-table-card>
    <table id="table-marketing-fees" class="table table-hover align-middle mb-0 w-100">
        <thead>
            <tr>
                <th class="text-center">
                    <input type="checkbox" class="form-check-input" id="check-all-fees">
                </th>
                <th>Tgl Berhak</th>
                <th class="text-center">POP</th>
                <th>Marketing</th>
                <th>Pelanggan</th>
                <th>Nominal Fee</th>
                <th>Status</th>
                <th>Tgl Bayar</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</x-table-card>

{{-- ── Modal Ubah Nominal Fee ─────────────────────────────────── --}}
<div class="modal fade" id="modal-edit-amount" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Ubah Nominal Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-edit-amount">
                <div class="modal-body">
                    <input type="hidden" id="edit-fee-id">
                    <div class="mb-3">
                        <label class="form-label required">Nominal Fee (Rp)</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="number" id="input-edit-amount" class="form-control form-control-sm" required min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-btn variant="neutral" data-bs-dismiss="modal">Batal</x-btn>
                    <x-btn type="submit" variant="primary" id="btn-save-amount">Simpan</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Tandai Dibayar (Bulk / Single) ────────────────────── --}}
<div class="modal fade" id="modal-pay-fees" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Tandai Fee Dibayar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-pay-fees">
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">
                        Menandai pembayaran untuk <strong id="pay-target-count">0</strong> transaksi fee marketing terpilih.
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Tanggal Pembayaran</label>
                        <input type="date" id="input-pay-date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan Pembayaran (Opsional)</label>
                        <textarea id="input-pay-note" class="form-control form-control-sm" rows="2" placeholder="Nomor referensi mutasi / transfer, bukti bayar"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-btn variant="neutral" data-bs-dismiss="modal">Batal</x-btn>
                    <x-btn type="submit" variant="approve" id="btn-confirm-pay">
                        Konfirmasi Pembayaran
                    </x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editAmountModal = {
        show: () => window.showModal('#modal-edit-amount'),
        hide: () => window.hideModal('#modal-edit-amount'),
    };
    const payFeesModal = {
        show: () => window.showModal('#modal-pay-fees'),
        hide: () => window.hideModal('#modal-pay-fees'),
    };
    const checkAll = document.getElementById('check-all-fees');
    const bulkPayBtn = document.getElementById('btn-bulk-pay');
    const bulkPayLabel = document.getElementById('bulk-pay-label');
    let selectedFeeIds = [];

    function updateBulkPayButton() {
        const checkedBoxes = document.querySelectorAll('.fee-checkbox:checked');
        selectedFeeIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

        if (selectedFeeIds.length > 0) {
            bulkPayBtn.classList.remove('d-none');
            bulkPayLabel.textContent = `Tandai Dibayar (${selectedFeeIds.length})`;
        } else {
            bulkPayBtn.classList.add('d-none');
        }
    }

    const table = window.initServerTable('#table-marketing-fees', {
        ajaxUrl: '{{ route('marketing-fees.index') }}',
        filterForm: '#filter-fees-form',
        searchInput: '#filter-fee-search',
        resetButton: '#btn-reset-fee-filter',
        pageLength: 15,
        order: [[1, 'desc']],
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data, type, row) {
                    if (row.is_paid) {
                        return '';
                    }
                    return `<input type="checkbox" class="form-check-input fee-checkbox" value="${row.id}">`;
                }
            },
            { data: 'eligible_at', name: 'eligible_at' },
            { data: 'pop_code', name: 'pop.code', className: 'text-center fw-semibold' },
            { data: 'marketing_name', name: 'marketingUser.name' },
            {
                data: null,
                name: 'customer.name',
                render: function (data, type, row) {
                    return `<div><span class="badge bg-secondary font-monospace">${row.customer_code}</span> <span class="fw-semibold">${row.customer_name}</span></div>`;
                }
            },
            {
                data: 'amount_format',
                name: 'amount',
                render: function (data, type, row) {
                    let html = `<span class="fw-bold">${data}</span>`;
                    if (!row.is_paid) {
                        html += ` <button type="button" class="btn btn-xs btn-link p-0 text-decoration-none btn-edit-amount" data-id="${row.id}" data-amount="${row.amount || 0}" title="Ubah Nominal"></button>`;
                    }
                    return html;
                }
            },
            {
                data: 'status',
                name: 'status',
                render: function (data) {
                    return window.statusBadge('fee_status', data);
                }
            },
            {
                data: 'paid_at',
                name: 'paid_at',
                render: function (data, type, row) {
                    if (!data) return '<span class="text-muted small">-</span>';
                    let text = `<span>${data}</span>`;
                    if (row.paid_note) {
                        text += ` `;
                    }
                    return text;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end',
                render: function (data, type, row) {
                    if (!row.is_paid) {
                        return window.btnHtml('approve', 'Bayar', {
                            'class': 'btn-pay-single',
                            'data-id': row.id,
                            'title': 'Tandai Dibayar'
                        });
                    }
                    if (row.can_unpay) {
                        return window.btnHtml('danger', 'Batal', {
                            'class': 'btn-unpay',
                            'data-id': row.id,
                            'title': 'Batalkan Pembayaran'
                        });
                    }
                    return '<span class="text-muted small">-</span>';
                }
            }
        ]
    });

    // Update summary when datatable reloads
    $('#table-marketing-fees').on('xhr.dt', function (e, settings, json) {
        if (json && json.summary) {
            const s = json.summary;
            document.getElementById('sum-pending-amount').textContent = 'Rp' + Number(s.pending_amount).toLocaleString('id-ID');
            document.getElementById('sum-pending-count').textContent = s.pending_count + ' transaksi';

            document.getElementById('sum-paid-amount').textContent = 'Rp' + Number(s.paid_amount).toLocaleString('id-ID');
            document.getElementById('sum-paid-count').textContent = s.paid_count + ' transaksi';

            document.getElementById('sum-total-amount').textContent = 'Rp' + Number(s.total_amount).toLocaleString('id-ID');
            document.getElementById('sum-total-count').textContent = s.total_count + ' transaksi';
        }
    });

    $('#table-marketing-fees').on('draw.dt', function () {
        checkAll.checked = false;
        updateBulkPayButton();
    });

    // Check all checkbox
    checkAll.addEventListener('change', function () {
        const checkboxes = document.querySelectorAll('.fee-checkbox');
        checkboxes.forEach(cb => { cb.checked = checkAll.checked; });
        updateBulkPayButton();
    });

    $(document).on('change', '.fee-checkbox', updateBulkPayButton);

    // Edit Amount
    $(document).on('click', '.btn-edit-amount', function () {
        const id = $(this).data('id');
        const amount = $(this).data('amount');
        document.getElementById('edit-fee-id').value = id;
        document.getElementById('input-edit-amount').value = amount;
        editAmountModal.show();
    });

    document.getElementById('form-edit-amount')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('edit-fee-id').value;
        const amount = document.getElementById('input-edit-amount').value;

        try {
            const data = await window.apiRequest(`{{ url('marketing-fees') }}/${id}/amount`, {
                method: 'PUT',
                body: { amount: amount }
            });

            editAmountModal.hide();
            window.toast('success', data.message || 'Nominal fee berhasil diperbarui.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message || 'Gagal mengubah nominal.');
        }
    });

    // Pay Single
    $(document).on('click', '.btn-pay-single', function () {
        const id = $(this).data('id');
        selectedFeeIds = [parseInt(id)];
        document.getElementById('pay-target-count').textContent = '1';
        payFeesModal.show();
    });

    // Bulk Pay Button
    bulkPayBtn.addEventListener('click', function () {
        if (selectedFeeIds.length === 0) return;
        document.getElementById('pay-target-count').textContent = selectedFeeIds.length;
        payFeesModal.show();
    });

    // Confirm Pay Form
    document.getElementById('form-pay-fees')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const paidAt = document.getElementById('input-pay-date').value;
        const paidNote = document.getElementById('input-pay-note').value;

        try {
            const data = await window.apiRequest("{{ route('marketing-fees.bulk-pay') }}", {
                method: 'POST',
                body: {
                    fee_ids: selectedFeeIds,
                    paid_at: paidAt,
                    paid_note: paidNote
                }
            });

            payFeesModal.hide();
            window.toast('success', data.message || 'Pembayaran berhasil disimpan.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message || 'Gagal menandai pembayaran.');
        }
    });

    // Unpay
    $(document).on('click', '.btn-unpay', async function () {
        const id = $(this).data('id');
        const confirm = await window.confirmDialog({
            title: 'Batalkan Pembayaran Fee?',
            text: 'Status fee akan dikembalikan menjadi PENDING.',
            confirmButtonText: 'Ya, Batalkan',
            confirmButtonClass: 'btn btn-danger',
            icon: 'warning'
        });

        if (!confirm || !confirm.isConfirmed) return;

        try {
            const data = await window.apiRequest(`{{ url('marketing-fees') }}/${id}/unpay`, {
                method: 'POST'
            });

            window.toast('success', data.message || 'Tanda pembayaran dibatalkan.');
            table.ajax.reload(null, false);
        } catch (err) {
            window.toast('error', err.message || 'Gagal membatalkan status pembayaran.');
        }
    });

    // Export XLSX Trigger
    document.getElementById('btn-export-fees')?.addEventListener('click', function () {
        const month = $('#filter-fee-month').val();
        if (!month) {
            window.toast('warning', 'Pilih bulan periode terlebih dahulu pada filter.');
            return;
        }

        const params = new URLSearchParams();
        params.append('month', month);

        const mId = $('#filter-fee-marketing').val();
        if (mId) params.append('marketing_user_id', mId);

        if ($('#filter-fee-pop').length && $('#filter-fee-pop').val()) {
            params.append('pop_id', $('#filter-fee-pop').val());
        }

        window.location.href = "{{ route('export.marketing-fees') }}?" + params.toString();
    });
});
</script>
@endpush
