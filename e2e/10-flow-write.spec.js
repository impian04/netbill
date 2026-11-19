/**
 * 10-flow-write.spec.js — Fase 17i
 * E2E Alur Tulis NetBill (@flow)
 * 
 * Pengujian serial untuk alur bisnis tulis multi-role:
 * - Alur A: Pendaftaran Marketing EP -> Approval Admin EP -> Survei Layak -> Penugasan Teknisi ->
 *           Pengerjaan & Upload Foto -> Verifikasi Admin -> Status ACTIVE & Riwayat 4 Tahap & Fee Marketing ->
 *           Multi-tenant isolation (KTU 404/hidden).
 * - Alur B: NOC Tiket Masalah Koneksi -> Kunjungan Teknisi -> Penugasan -> Pengerjaan Teknisi ->
 *           Verifikasi Admin -> Tiket IN_PROGRESS -> Selesai & Tutup Tiket.
 * - Alur C: Pendaftaran Admin (PENDING) -> Survei Tidak Layak -> Pembatalan Pelanggan (INACTIVE) -> Tanpa Fee.
 * - Alur D: Pendaftaran Marketing 2 -> Minta Perbaikan -> Perbaikan & Kirim Ulang -> Approval (Tanpa Duplikasi).
 */

import { test, expect } from '@playwright/test';
import { loginAs, attachErrorMonitor, assertNoErrors, waitForNetworkIdle } from './helpers.js';

// Sample 1x1 base64 JPEG buffer untuk simulasi upload foto teknisi
const SAMPLE_JPG_BUFFER = Buffer.from(
    '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
    'base64'
);

// Bilangan acak untuk memastikan data unik per sesi pengujian
const RUN_ID = Math.floor(1000 + Math.random() * 9000);

test.describe.serial('E2E Alur Tulis NetBill @flow', () => {
    // Shared state antar langkah dalam alur
    let customerACode = '';
    let customerAId = '';
    let customerAName = `Pelanggan Flow A ${RUN_ID}`;
    let customerAPhone = `08129999${RUN_ID}`;

    let customerCCode = '';
    let customerCId = '';
    let customerCName = `Pelanggan Flow C ${RUN_ID}`;
    let customerCPhone = `628138888${RUN_ID}`;

    let customerDCode = '';
    let customerDName = `Pelanggan Flow D ${RUN_ID}`;
    let customerDPhone = `08197777${RUN_ID}`;

    // =========================================================================
    // ALUR A: Jalur Utama Pemasangan Baru & Aktivasi
    // =========================================================================

    test('Alur A.1: Marketing POP EP mendaftarkan calon pelanggan baru', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'marketing_ep');

        await page.goto('/my/applications');
        await page.waitForSelector('#table-marketing-applications', { timeout: 10_000 });

        // Buka form pendaftaran baru
        await page.click('#btn-add-application');
        await page.waitForSelector('#offcanvas-application.show', { timeout: 5_000 });

        // Isi form pendaftaran
        await page.fill('#app-name', customerAName);
        await page.fill('#app-phone', customerAPhone);
        await page.fill('#app-address', `Jl. Merpati No. ${RUN_ID}, Empat Puluh`);
        await page.fill('#app-coordinates', '0.512345, 101.456789');
        await page.fill('#app-package', 'Paket Hemat 20 Mbps');

        // Submit form pendaftaran
        const [response] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/my/applications') && resp.request().method() === 'POST'),
            page.click('#btn-submit-application'),
        ]);

        expect(response.status()).toBeLessThan(400);

        // Tunggu offcanvas tertutup
        await page.waitForSelector('#offcanvas-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur A.2: Admin POP EP mereview & menyetujui pendaftaran (terbit kode JCN)', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/customer-applications');
        await page.waitForSelector('#table-admin-applications', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Cari aplikasi pelanggan A
        const searchInput = page.locator('#filter-admin-app-search');
        await searchInput.fill(customerAName);
        await waitForNetworkIdle(page);

        // Buka offcanvas review
        const reviewBtn = page.locator('.btn-review-app').first();
        await expect(reviewBtn).toBeVisible({ timeout: 5_000 });
        await reviewBtn.click();

        await page.waitForSelector('#offcanvas-review-application.show', { timeout: 5_000 });

        // Klik Setujui Pendaftaran
        await page.click('#btn-approve-app');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        // Tangkap respons approve untuk mencatat kode JCN
        const [approveResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/approve') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);

        expect(approveResp.status()).toBeLessThan(400);
        const data = await approveResp.json();
        expect(data.success).toBeTruthy();
        expect(data.customer_code).toMatch(/^JCN-EP-\d+$/);
        customerACode = data.customer_code;

        // Tunggu offcanvas tertutup
        await page.waitForSelector('#offcanvas-review-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        // Ambil ID pelanggan dari daftar pelanggan (/customers)
        await page.goto('/customers');
        await page.waitForSelector('#customers-table', { timeout: 10_000 });
        await page.fill('#filter-search', customerACode);
        await waitForNetworkIdle(page);

        const customerRowLink = page.locator('#customers-table tbody tr a[href*="/customers/"]').first();
        await expect(customerRowLink).toBeVisible({ timeout: 5_000 });
        const href = await customerRowLink.getAttribute('href');
        customerAId = href ? href.split('/').pop() : '';
        expect(customerAId).toBeTruthy();

        assertNoErrors(errors);
    });

    test('Alur A.3: Surveyor POP EP mengisi hasil survei Layak (Mobile Viewport)', async ({ page }) => {
        // Mode mobile 390x844
        await page.setViewportSize({ width: 390, height: 844 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'surveyor_ep');

        await page.goto('/survey-queue');
        await page.waitForSelector('#queueCardsWrapper', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Filter pencarian kode pelanggan
        await page.fill('#queue_filter_q', customerACode);
        await page.click('#filterQueueForm button[type="submit"]');
        await waitForNetworkIdle(page);

        // Klik Mulai Survei
        const startSurveyBtn = page.locator(`.btn-start-survey[data-code="${customerACode}"]`);
        await expect(startSurveyBtn).toBeVisible({ timeout: 5_000 });
        await startSurveyBtn.click();

        await page.waitForSelector('#surveyOffcanvas.show', { timeout: 5_000 });

        // Pilih ODC EP pertama yang tersedia
        const odcSelect = page.locator('#survey_odc_id');
        await odcSelect.selectOption({ index: 1 });
        await odcSelect.dispatchEvent('change');

        // Pilih ODP yang cocok dengan ODC terpilih
        const selectedOdc = await odcSelect.inputValue();
        const matchingOdp = page.locator(`#survey_odp_id option[data-odc="${selectedOdc}"]`).first();
        const odpVal = await matchingOdp.getAttribute('value');
        await page.locator('#survey_odp_id').selectOption(odpVal);

        // Koordinat
        await page.fill('#survey_coordinates', '0.512345, 101.456789');

        // Hasil survei: Layak (default FEASIBLE)
        await page.check('#result_feasible');

        // Pastikan customer_id terisi
        await page.evaluate((id) => {
            const el = document.getElementById('survey_customer_id');
            if (el && !el.value && id) el.value = id;
        }, customerAId);

        // Material 1 (sudah ada 1 baris default dari JS)
        const firstMatSelect = page.locator('#materials_tbody tr .material-select').first();
        await firstMatSelect.selectOption({ index: 1 });
        await page.locator('#materials_tbody tr input[type="number"]').first().fill('10');

        // Tambah material 2 berbeda
        await page.click('#btn-add-material');
        const rows = page.locator('#materials_tbody tr');
        await expect(rows).toHaveCount(2);

        const secondMatSelect = page.locator('#materials_tbody tr .material-select').nth(1);
        await secondMatSelect.selectOption({ index: 2 });
        await page.locator('#materials_tbody tr input[type="number"]').nth(1).fill('2');

        // Submit form survei
        const [surveyResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/my/surveys') && resp.request().method() === 'POST'),
            page.click('#surveyOffcanvas button[type="submit"]'),
        ]);

        if (surveyResp.status() >= 400) {
            console.error('Survey Error Response:', await surveyResp.text());
        }

        expect(surveyResp.status()).toBeLessThan(400);

        // Tunggu redirect ke /my/surveys selesai
        await page.waitForURL(url => url.toString().includes('/my/surveys'), { timeout: 10_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur A.4: Admin POP EP melihat status SUBMITTED & menugaskan Teknisi EP', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/surveys');
        await page.waitForSelector('#surveysTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Cari survei pelanggan A
        const tableCard = page.locator('#surveysTable');
        const detailBtn = tableCard.locator(`tr:has-text("${customerACode}") .btn-detail`).first();
        await expect(detailBtn).toBeVisible({ timeout: 5_000 });
        await detailBtn.click();

        await page.waitForSelector('#detailSurveyOffcanvas.show', { timeout: 5_000 });

        // Pastikan status SUBMITTED dan tombol Tugaskan Teknisi tampil
        const btnAssign = page.locator('#btn-assign-technician');
        await expect(btnAssign).toBeVisible({ timeout: 5_000 });
        await btnAssign.click();

        // Offcanvas assign technician
        await page.waitForSelector('#assignTechnicianOffcanvas.show', { timeout: 5_000 });

        // Pilih teknisi EP
        const techSelect = page.locator('#assign_technician_id');
        const techOptions = await techSelect.locator('option:not([value=""])').all();
        expect(techOptions.length).toBeGreaterThan(0);
        await techSelect.selectOption({ index: 1 });

        // Submit penugasan
        const [assignResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/assign-technician') && resp.request().method() === 'POST'),
            page.click('#assignTechnicianOffcanvas button[type="submit"]'),
        ]);

        expect(assignResp.status()).toBeLessThan(400);
        await page.waitForSelector('#assignTechnicianOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur A.5: Teknisi EP memulai tugas, mengunggah 2 foto, dan submit verifikasi (Mobile Viewport)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'technician_ep');

        await page.goto('/my/tasks');
        await page.waitForSelector('#tasksCardsWrapper', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Cari tugas pelanggan A
        await page.fill('#task_filter_q', customerACode);
        await page.click('#filterTasksForm button[type="submit"]');
        await waitForNetworkIdle(page);

        // Jika tombol Mulai tampil, klik Mulai
        const startBtn = page.locator('.btn-start-task').first();
        if (await startBtn.isVisible()) {
            const [startResp] = await Promise.all([
                page.waitForResponse(resp => resp.url().includes('/start') && resp.request().method() === 'POST'),
                startBtn.click(),
            ]);
            expect(startResp.status()).toBeLessThan(400);
            await expect(page.locator('.btn-start-task')).toHaveCount(0, { timeout: 5_000 });
            await waitForNetworkIdle(page);
        }

        // Buka lembar kerja
        const openTaskBtn = page.locator('.btn-open-task').first();
        await expect(openTaskBtn).toBeVisible({ timeout: 5_000 });
        await openTaskBtn.click();

        await page.waitForSelector('#taskWorkOffcanvas.show', { timeout: 5_000 });

        // Unggah Foto 1: Instalasi
        await page.setInputFiles('#photo_file_input', {
            name: 'foto1_instalasi.jpg',
            mimeType: 'image/jpeg',
            buffer: SAMPLE_JPG_BUFFER,
        });
        await page.selectOption('#photo_stage_select', 'INSTALASI');
        await page.fill('#photo_caption_input', 'Pemasangan kabel dropcore');

        const [photo1Resp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/photos') && resp.request().method() === 'POST'),
            page.click('#btn-upload-photo'),
        ]);
        expect(photo1Resp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        // Tunggu thumbnail foto 1 muncul di galeri
        await page.waitForSelector('#tk_photos_gallery img', { timeout: 5_000 });

        // Unggah Foto 2: Aktivasi
        await page.setInputFiles('#photo_file_input', {
            name: 'foto2_aktivasi.jpg',
            mimeType: 'image/jpeg',
            buffer: SAMPLE_JPG_BUFFER,
        });
        await page.selectOption('#photo_stage_select', 'AKTIVASI');
        await page.fill('#photo_caption_input', 'Modem ONT menyala');

        const [photo2Resp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/photos') && resp.request().method() === 'POST'),
            page.click('#btn-upload-photo'),
        ]);
        expect(photo2Resp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        // Verifikasi counter minimal 2 foto
        await expect(page.locator('#tk_photo_counter')).toContainText('2 / 12 Foto');

        // Isi form penyelesaian laporan
        const todayStr = new Date().toISOString().slice(0, 10);
        await page.fill('#tk_completed_on', todayStr);
        await page.fill('#tk_technician_notes', 'Pemasangan selesai rapi, redaman -18 dBm.');

        // Kirim untuk verifikasi
        const [submitTaskResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/submit') && resp.request().method() === 'POST'),
            page.click('#btn-submit-task'),
        ]);
        expect(submitTaskResp.status()).toBeLessThan(400);

        await page.waitForLoadState('load');
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur A.6: Admin POP EP memverifikasi hasil pekerjaan teknisi', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/work-orders');
        await page.waitForSelector('#workOrdersTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Buka detail penugasan pelanggan A
        const row = page.locator(`#workOrdersTable tr:has-text("${customerACode}")`);
        await expect(row).toBeVisible({ timeout: 5_000 });
        await row.locator('.btn-detail-wo').click();

        await page.waitForSelector('#detailWorkOrderOffcanvas.show', { timeout: 5_000 });

        // Verifikasi foto tampil (bukan placeholder)
        const photoImgs = page.locator('#wo_photos_gallery img');
        await expect(photoImgs).toHaveCount(2);
        for (let i = 0; i < 2; i++) {
            const src = await photoImgs.nth(i).getAttribute('src');
            expect(src).toContain('/files/work-orders/');
        }

        // Klik tombol Verifikasi
        const btnVerify = page.locator('#btn-verify-wo');
        await expect(btnVerify).toBeVisible({ timeout: 5_000 });
        await btnVerify.click();

        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [verifyResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/verify') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);

        expect(verifyResp.status()).toBeLessThan(400);
        await page.waitForSelector('#detailWorkOrderOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur A.7: Verifikasi UI detail pelanggan ACTIVE, 4 tahap riwayat, dan Fee Marketing', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        // Buka halaman detail pelanggan
        await page.goto(`/customers/${customerAId}`);
        await page.waitForSelector('.page-header', { timeout: 10_000 });

        // 1. Status pelanggan ACTIVE
        const statusBadge = page.locator('.card-header:has-text("Rincian Profil Pelanggan") .badge');
        await expect(statusBadge).toContainText(/aktif|active/i);

        // 2. Tanggal mulai berlangganan terisi
        const subDateEl = page.locator('div:text-is("Mulai Berlangganan") + div');
        const subDateText = await subDateEl.textContent();
        expect(subDateText?.trim()).not.toBe('—');
        expect(subDateText?.trim().length).toBeGreaterThan(3);

        // 3. Kartu Riwayat Pemasangan menampilkan 4 tahap
        const historyCard = page.locator('.card:has-text("Riwayat Pemasangan & Aktivasi")');
        await expect(historyCard).toBeVisible();
        await expect(historyCard.locator('strong:has-text("1. Pendaftaran")')).toBeVisible();
        await expect(historyCard.locator('strong:has-text("2. Survei Lapangan")')).toBeVisible();
        await expect(historyCard.locator('strong:has-text("3. Penugasan Teknisi")')).toBeVisible();
        await expect(historyCard.locator('strong:has-text("4. Verifikasi & Aktivasi")')).toBeVisible();

        // 4. Halaman Fee Marketing menampilkan tepat 1 baris untuk marketing EP
        await page.goto('/marketing-fees');
        await page.waitForSelector('#table-marketing-fees', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        await page.fill('#filter-fee-search', customerACode);
        await waitForNetworkIdle(page);

        const feeRows = page.locator('#table-marketing-fees tbody tr');
        await expect(feeRows).toHaveCount(1);
        await expect(feeRows.first()).toContainText(customerACode);
        await expect(feeRows.first()).toContainText('Marketing EP');

        assertNoErrors(errors);
    });

    test('Alur A.8: Isolasi Multi-Tenant (Admin KTU tidak melihat & 404, Surveyor/Teknisi KTU bersih)', async ({ page }) => {
        // Admin KTU mencari kode pelanggan EP di /customers -> tidak muncul
        await loginAs(page, 'admin_ktu');
        await page.goto('/customers');
        await page.waitForSelector('#customers-table', { timeout: 10_000 });
        await page.fill('#filter-search', customerACode);
        await waitForNetworkIdle(page);

        const customerRows = page.locator('#customers-table tbody tr:not(:has-text("No matching records")):not(:has-text("Tidak ada data")):not(:has-text("No data available")):not(:has(.dt-empty)):not(:has(.dataTables_empty))');
        expect(await customerRows.count()).toBe(0);

        // Admin KTU membuka URL detail langsung -> harus 404
        const resp = await page.goto(`/customers/${customerAId}`);
        expect(resp.status()).toBe(404);

        // Surveyor KTU tidak melihat antrean survei pelanggan EP
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAs(page, 'surveyor_ktu');
        await page.goto('/survey-queue');
        await page.waitForSelector('#queueCardsWrapper', { timeout: 10_000 });
        await page.fill('#queue_filter_q', customerACode);
        await page.click('#filterQueueForm button[type="submit"]');
        await waitForNetworkIdle(page);
        await expect(page.locator(`.btn-start-survey[data-code="${customerACode}"]`)).toHaveCount(0);

        // Teknisi KTU tidak melihat tugas penugasan pelanggan EP
        await loginAs(page, 'technician_ktu');
        await page.goto('/my/tasks');
        await page.waitForSelector('#tasksCardsWrapper', { timeout: 10_000 });
        await page.fill('#task_filter_q', customerACode);
        await page.click('#filterTasksForm button[type="submit"]');
        await waitForNetworkIdle(page);
        await expect(page.locator(`.btn-open-task:has-text("${customerACode}")`)).toHaveCount(0);
    });

    // =========================================================================
    // ALUR B: Kunjungan Perbaikan (Repair Work Order)
    // =========================================================================

    test('Alur B.1: NOC membuat tiket Masalah Koneksi, ubah ke IN_PROGRESS, dan minta kunjungan teknisi', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'noc');

        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Klik Buat Tiket
        await page.click('#btn-create-ticket');
        await page.waitForSelector('#ticketFormOffcanvas.show', { timeout: 5_000 });

        // Cari pelanggan A
        await page.fill('#customer_search_input', customerACode);
        const searchResultItem = page.locator(`#customer-search-results .list-group-item:has-text("${customerACode}")`).first();
        await expect(searchResultItem).toBeVisible({ timeout: 5_000 });
        await searchResultItem.click();

        // Pilih Kategori: CONNECTION
        await page.selectOption('#form_category', 'CONNECTION');
        await page.fill('#form_subject', `Koneksi putus LOS merah Flow B ${RUN_ID}`);
        await page.fill('#form_description', 'Lampu LOS modem berkedip merah, perlu perbaikan fisik kabel optik.');

        // Submit Buat Tiket
        const [ticketResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/tickets') && resp.request().method() === 'POST'),
            page.click('#ticketFormOffcanvas button[type="submit"]'),
        ]);

        expect(ticketResp.status()).toBeLessThan(400);
        await page.waitForSelector('#ticketFormOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        // Buka detail tiket
        const ticketRow = page.locator(`#ticketsTable tr:has-text("Koneksi putus LOS merah Flow B ${RUN_ID}")`);
        await expect(ticketRow).toBeVisible({ timeout: 5_000 });
        await ticketRow.locator('.btn-detail').first().click();

        await page.waitForSelector('#ticketDetailOffcanvas.show', { timeout: 5_000 });

        // Ubah status ke IN_PROGRESS
        await page.selectOption('#dtl_target_status', 'IN_PROGRESS');
        await page.click('#btn-save-status');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [statusResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/status') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);
        expect(statusResp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        // Minta Kunjungan Teknisi
        const visitContainer = page.locator('#field_visit_request_container');
        await expect(visitContainer).toBeVisible({ timeout: 5_000 });
        await page.fill('#dtl_field_visit_note', 'Periksa jalur kabel dropcore tiang 2 ke rumah pelanggan.');
        await page.click('#btn-request-field-visit');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [visitResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/request-field-visit') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);
        expect(visitResp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur B.2: Admin POP EP menugaskan teknisi untuk Work Order Perbaikan (REPAIR)', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/work-orders');
        await page.waitForSelector('#workOrdersTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Filter ke jenis REPAIR
        await page.selectOption('#filter_type', 'REPAIR');
        await waitForNetworkIdle(page);

        // Cari pelanggan A
        const repairRow = page.locator(`#workOrdersTable tr:has-text("${customerACode}")`).first();
        await expect(repairRow).toBeVisible({ timeout: 5_000 });
        await repairRow.locator('.btn-detail-wo').first().click();

        await page.waitForSelector('#detailWorkOrderOffcanvas.show', { timeout: 5_000 });

        // Klik Tugaskan Teknisi
        const btnAssignRepair = page.locator('#btn-assign-repair-wo');
        await expect(btnAssignRepair).toBeVisible({ timeout: 5_000 });
        await btnAssignRepair.click();

        await page.waitForSelector('#reassignTechnicianOffcanvas.show', { timeout: 5_000 });
        await page.waitForTimeout(500);

        // Pilih Teknisi EP
        const techSelect = page.locator('#reassign_technician_id');
        await techSelect.selectOption({ index: 1 });

        // Submit penugasan repair
        const [assignRepairResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/assign-repair') && resp.request().method() === 'POST'),
            page.locator('#reassignTechnicianForm').evaluate(form => form.requestSubmit()),
        ]);

        expect(assignRepairResp.status()).toBeLessThan(400);
        await page.waitForSelector('#reassignTechnicianOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur B.3: Teknisi EP mengunggah 1 foto perbaikan & kirim laporan (Mobile Viewport)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'technician_ep');

        await page.goto('/my/tasks');
        await page.waitForSelector('#tasksCardsWrapper', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Cari tugas perbaikan
        await page.fill('#task_filter_q', customerACode);
        await page.click('#filterTasksForm button[type="submit"]');
        await waitForNetworkIdle(page);

        // Jika tombol Mulai tampil, klik Mulai
        const startBtn = page.locator('.btn-start-task').first();
        if (await startBtn.isVisible()) {
            const [startResp] = await Promise.all([
                page.waitForResponse(resp => resp.url().includes('/start') && resp.request().method() === 'POST'),
                startBtn.click(),
            ]);
            expect(startResp.status()).toBeLessThan(400);
            await expect(page.locator('.btn-start-task')).toHaveCount(0, { timeout: 5_000 });
            await waitForNetworkIdle(page);
        }

        // Buka lembar kerja
        const openTaskBtn = page.locator('.btn-open-task').first();
        await expect(openTaskBtn).toBeVisible({ timeout: 5_000 });
        await openTaskBtn.click();

        await page.waitForSelector('#taskWorkOffcanvas.show', { timeout: 5_000 });

        // Unggah 1 Foto: PERBAIKAN
        await page.setInputFiles('#photo_file_input', {
            name: 'foto_repair.jpg',
            mimeType: 'image/jpeg',
            buffer: SAMPLE_JPG_BUFFER,
        });
        await page.selectOption('#photo_stage_select', 'PERBAIKAN');
        await page.fill('#photo_caption_input', 'Penyambungan kembali kabel putus');

        const [photoResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/photos') && resp.request().method() === 'POST'),
            page.click('#btn-upload-photo'),
        ]);
        expect(photoResp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        // Isi form pengiriman
        const todayStr = new Date().toISOString().slice(0, 10);
        await page.fill('#tk_completed_on', todayStr);
        await page.fill('#tk_technician_notes', 'Kabel dropcore disambung ulang dengan splicer, LOS normal.');

        const [submitResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/submit') && resp.request().method() === 'POST'),
            page.click('#btn-submit-task'),
        ]);
        expect(submitResp.status()).toBeLessThan(400);

        await page.waitForLoadState('load');
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur B.4: Admin memverifikasi, status pelanggan tetap ACTIVE & Fee tetap 1 baris, NOC resolve & close tiket', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const errors = attachErrorMonitor(page);

        // 1. Admin memverifikasi WO repair
        await loginAs(page, 'admin_ep');
        await page.goto('/work-orders');
        await page.waitForSelector('#workOrdersTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        await page.selectOption('#filter_type', 'REPAIR');
        await waitForNetworkIdle(page);

        const repairRow = page.locator(`#workOrdersTable tr:has-text("${customerACode}")`).first();
        await expect(repairRow).toBeVisible({ timeout: 5_000 });
        await repairRow.locator('.btn-detail-wo').first().click();

        await page.waitForSelector('#detailWorkOrderOffcanvas.show', { timeout: 5_000 });

        const btnVerify = page.locator('#btn-verify-wo');
        await expect(btnVerify).toBeVisible({ timeout: 5_000 });
        await btnVerify.click();

        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });
        const [verifyResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/verify') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);
        expect(verifyResp.status()).toBeLessThan(400);
        await page.waitForSelector('#detailWorkOrderOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        // 2. Status pelanggan tetap ACTIVE
        await page.goto(`/customers/${customerAId}`);
        await page.waitForSelector('.page-header', { timeout: 10_000 });
        const statusBadge = page.locator('.card-header:has-text("Rincian Profil Pelanggan") .badge');
        await expect(statusBadge).toContainText(/aktif|active/i);

        // 3. Fee Marketing tetap tepat 1 baris
        await page.goto('/marketing-fees');
        await page.waitForSelector('#table-marketing-fees', { timeout: 10_000 });
        await waitForNetworkIdle(page);
        await page.fill('#filter-fee-search', customerACode);
        await waitForNetworkIdle(page);
        const feeRows = page.locator('#table-marketing-fees tbody tr');
        await expect(feeRows).toHaveCount(1);

        // 4. NOC menyelesaikan (RESOLVED) lalu menutup tiket (CLOSED)
        await loginAs(page, 'noc');
        await page.goto('/tickets');
        await page.waitForSelector('#ticketsTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        const ticketRow = page.locator(`#ticketsTable tr:has-text("${customerACode}")`).first();
        await expect(ticketRow).toBeVisible({ timeout: 5_000 });
        await ticketRow.locator('.btn-detail').first().click();

        await page.waitForSelector('#ticketDetailOffcanvas.show', { timeout: 5_000 });

        // Tiket sudah kembali IN_PROGRESS setelah perbaikan diverifikasi
        // Resolve ticket
        await page.selectOption('#dtl_target_status', 'RESOLVED');
        await page.fill('#dtl_resolution_input', 'Kabel optik telah selesai disambung oleh teknisi lapangan.');
        await page.click('#btn-save-status');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [resolveResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/status') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);
        expect(resolveResp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        // Close ticket
        await page.selectOption('#dtl_target_status', 'CLOSED');
        await page.click('#btn-save-status');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [closeResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/status') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);
        expect(closeResp.status()).toBeLessThan(400);
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    // =========================================================================
    // ALUR C: Survei Tidak Layak & Pembatalan Pelanggan
    // =========================================================================

    test('Alur C.1: Admin EP membuat pelanggan langsung (status PENDING)', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/customers');
        await page.waitForSelector('#customers-table', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Klik Tambah Pelanggan
        await page.click('#btn-open-create-customer');
        await page.waitForSelector('#offcanvas-customer.show', { timeout: 5_000 });

        const todayStr = new Date().toISOString().slice(0, 10);
        await page.fill('#cust_name', customerCName);
        await page.fill('#cust_phone', customerCPhone);
        await page.fill('#cust_subscribed_at', todayStr);
        await page.selectOption('#cust_service_status', 'PENDING');
        await page.fill('#cust_package', 'Paket 20 Mbps');
        await page.fill('#cust_billing_type', 'Bulanan');
        await page.fill('#cust_address', `Jl. Terpencil No. ${RUN_ID}, Dusun Jauh`);

        const [createResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/customers') && resp.request().method() === 'POST'),
            page.click('#btn-submit-customer'),
        ]);

        expect(createResp.status()).toBeLessThan(400);
        const data = await createResp.json();
        customerCCode = data.data?.customer_code || data.customer_code;
        customerCId = data.data?.id || data.id;

        await page.waitForSelector('#offcanvas-customer', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur C.2: Surveyor EP mengirim hasil Tidak Layak dengan catatan (Mobile Viewport)', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'surveyor_ep');

        await page.goto('/survey-queue');
        await page.waitForSelector('#queueCardsWrapper', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        await page.fill('#queue_filter_q', customerCName);
        await page.click('#filterQueueForm button[type="submit"]');
        await waitForNetworkIdle(page);

        const startBtn = page.locator(`.btn-start-survey[data-name="${customerCName}"]`);
        await expect(startBtn).toBeVisible({ timeout: 5_000 });
        await startBtn.click();

        await page.waitForSelector('#surveyOffcanvas.show', { timeout: 5_000 });

        // Pilih Tidak Layak
        await page.check('#result_not_feasible');

        // Pastikan customer_id terisi
        await page.evaluate((id) => {
            const el = document.getElementById('survey_customer_id');
            if (el && !el.value && id) el.value = id;
        }, customerCId);

        await page.fill('#survey_result_note', 'Jarak ke ODP terdekat lebih dari 800m dan tidak tersedia tiang.');

        const [surveyResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/my/surveys') && resp.request().method() === 'POST'),
            page.click('#surveyOffcanvas button[type="submit"]'),
        ]);

        expect(surveyResp.status()).toBeLessThan(400);
        await page.waitForURL(url => url.toString().includes('/my/surveys'), { timeout: 10_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur C.3: Admin Batalkan Pelanggan -> INACTIVE, hilang dari antrean survei, tanpa Fee Marketing', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/surveys');
        await page.waitForSelector('#surveysTable', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        // Buka survei pelanggan C
        const surveyRow = page.locator(`#surveysTable tr:has-text("${customerCName}")`);
        await expect(surveyRow).toBeVisible({ timeout: 5_000 });
        await surveyRow.locator('.btn-detail').first().click();

        await page.waitForSelector('#detailSurveyOffcanvas.show', { timeout: 5_000 });

        // Klik Batalkan Pelanggan
        const btnCancel = page.locator('#btn-cancel-customer');
        await expect(btnCancel).toBeVisible({ timeout: 5_000 });
        await btnCancel.click();

        // Prompt alasan pembatalan
        await page.waitForSelector('.swal2-textarea', { timeout: 5_000 });
        await page.locator('.swal2-textarea').fill('Di luar jangkauan jaringan dan biaya ekspansi terlalu tinggi.');

        const [cancelResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/cancel-customer') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);

        expect(cancelResp.status()).toBeLessThan(400);
        await page.waitForSelector('#detailSurveyOffcanvas', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        // Verifikasi status pelanggan menjadi INACTIVE
        await page.goto(`/customers/${customerCId}`);
        await page.waitForSelector('.page-header', { timeout: 10_000 });
        const statusBadge = page.locator('.card-header:has-text("Rincian Profil Pelanggan") .badge');
        await expect(statusBadge).toContainText(/nonaktif|inactive/i);

        // Verifikasi hilang dari Antrean Survei
        await loginAs(page, 'surveyor_ep');
        await page.goto('/survey-queue');
        await page.waitForSelector('#queueCardsWrapper', { timeout: 10_000 });
        await page.fill('#queue_filter_q', customerCName);
        await page.click('#filterQueueForm button[type="submit"]');
        await waitForNetworkIdle(page);
        await expect(page.locator(`.btn-start-survey[data-name="${customerCName}"]`)).toHaveCount(0);

        // Verifikasi tidak ada baris Fee Marketing
        await loginAs(page, 'admin_ep');
        await page.goto('/marketing-fees');
        await page.waitForSelector('#table-marketing-fees', { timeout: 10_000 });
        await waitForNetworkIdle(page);
        await page.fill('#filter-fee-search', customerCName);
        await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/marketing-fees') && resp.request().method() === 'GET'),
            page.press('#filter-fee-search', 'Enter'),
        ]);
        await waitForNetworkIdle(page);
        const feeRows = page.locator('#table-marketing-fees tbody tr:not(:has-text("No matching records")):not(:has-text("Tidak ada data")):not(:has-text("No data available")):not(:has(.dt-empty)):not(:has(.dataTables_empty))');
        expect(await feeRows.count()).toBe(0);

        assertNoErrors(errors);
    });

    // =========================================================================
    // ALUR D: Revisi Pendaftaran Marketing Lain & Persetujuan
    // =========================================================================

    test('Alur D.1: Marketing 2 EP mendaftarkan calon pelanggan baru', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'marketing2_ep');

        await page.goto('/my/applications');
        await page.waitForSelector('#table-marketing-applications', { timeout: 10_000 });

        await page.click('#btn-add-application');
        await page.waitForSelector('#offcanvas-application.show', { timeout: 5_000 });

        await page.fill('#app-name', customerDName);
        await page.fill('#app-phone', customerDPhone);
        await page.fill('#app-address', `Alamat Belum Lengkap ${RUN_ID}`);
        await page.fill('#app-package', 'Paket Bisnis 50 Mbps');

        const [resp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/my/applications') && resp.request().method() === 'POST'),
            page.click('#btn-submit-application'),
        ]);

        expect(resp.status()).toBeLessThan(400);
        await page.waitForSelector('#offcanvas-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur D.2: Admin EP meminta perbaikan (catatan wajib)', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/customer-applications');
        await page.waitForSelector('#table-admin-applications', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        await page.fill('#filter-admin-app-search', customerDName);
        await waitForNetworkIdle(page);

        const reviewBtn = page.locator('.btn-review-app').first();
        await expect(reviewBtn).toBeVisible({ timeout: 5_000 });
        await reviewBtn.click();

        await page.waitForSelector('#offcanvas-review-application.show', { timeout: 5_000 });

        // Klik Perbaikan
        await page.click('#btn-revision-app');
        await page.waitForSelector('.swal2-textarea', { timeout: 5_000 });
        await page.locator('.swal2-textarea').fill('Mohon lengkapi alamat pemasangan dengan nomor rumah.');

        const [revResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/request-revision') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);

        expect(revResp.status()).toBeLessThan(400);
        await page.waitForSelector('#offcanvas-review-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur D.3: Marketing 2 EP mengubah dan mengirim ulang pendaftaran', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'marketing2_ep');

        await page.goto('/my/applications');
        await page.waitForSelector('#table-marketing-applications', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        const editBtn = page.locator('.btn-edit-app').first();
        await expect(editBtn).toBeVisible({ timeout: 5_000 });
        await editBtn.click();

        await page.waitForSelector('#offcanvas-application.show', { timeout: 5_000 });

        // Verifikasi catatan perbaikan tampil
        await expect(page.locator('#review-note-container')).toBeVisible();

        // Ubah alamat
        await page.fill('#app-address', `Jl. Cendrawasih No. 12, Samping Lapangan ${RUN_ID}`);

        const [updateResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/my/applications') && (resp.request().method() === 'PUT' || resp.request().method() === 'POST')),
            page.click('#btn-submit-application'),
        ]);

        expect(updateResp.status()).toBeLessThan(400);
        await page.waitForSelector('#offcanvas-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        assertNoErrors(errors);
    });

    test('Alur D.4: Admin EP menyetujui pendaftaran revisi & verifikasi tanpa duplikasi', async ({ page }) => {
        const errors = attachErrorMonitor(page);
        await loginAs(page, 'admin_ep');

        await page.goto('/customer-applications');
        await page.waitForSelector('#table-admin-applications', { timeout: 10_000 });
        await waitForNetworkIdle(page);

        await page.fill('#filter-admin-app-search', customerDName);
        await waitForNetworkIdle(page);

        const reviewBtn = page.locator('.btn-review-app').first();
        await expect(reviewBtn).toBeVisible({ timeout: 5_000 });
        await reviewBtn.click();

        await page.waitForSelector('#offcanvas-review-application.show', { timeout: 5_000 });

        await page.click('#btn-approve-app');
        await page.waitForSelector('.swal2-confirm', { timeout: 5_000 });

        const [approveResp] = await Promise.all([
            page.waitForResponse(resp => resp.url().includes('/approve') && resp.request().method() === 'POST'),
            page.click('.swal2-confirm'),
        ]);

        expect(approveResp.status()).toBeLessThan(400);
        const data = await approveResp.json();
        expect(data.customer_code).toMatch(/^JCN-EP-\d+$/);
        customerDCode = data.customer_code;

        await page.waitForSelector('#offcanvas-review-application', { state: 'hidden', timeout: 5_000 });
        await waitForNetworkIdle(page);

        // Verifikasi di /customers: tepat 1 baris, tidak ada duplikat
        await page.goto('/customers');
        await page.waitForSelector('#customers-table', { timeout: 10_000 });
        await page.fill('#filter-search', customerDName);
        await waitForNetworkIdle(page);

        const custRows = page.locator('#customers-table tbody tr:not(:has-text("No matching records")):not(:has-text("Tidak ada data")):not(:has-text("No data available")):not(:has(.dt-empty)):not(:has(.dataTables_empty))');
        await expect(custRows).toHaveCount(1);
        await expect(custRows.first()).toContainText(customerDCode);

        assertNoErrors(errors);
    });
});
