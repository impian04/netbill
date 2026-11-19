# Panduan Desain & Antarmuka NetBill

Dokumen ini merangkum aturan baku antarmuka untuk pengembangan fitur dan modul di NetBill (Fase 17h).

---

## 1. Token Desain CSS (`resources/css/app.css`)

Semua dimensi layout, jarak konten, dan kontrol diatur secara terpusat melalui variabel CSS `:root`:

| Token CSS | Nilai Desktop | Nilai Mobile | Kegunaan |
|---|---|---|---|
| `--nb-page-padding` | `1.5rem` (24px) | `0.75rem` (12px) | Padding utama `#main-content` |
| `--nb-section-gap` | `1.5rem` (24px) | `1.5rem` (24px) | Jarak antar bagian (`.page-header` &rarr; `.card`) |
| `--nb-card-padding` | `1.25rem` (20px) | `1.25rem` (20px) | Padding internal `.card-body` |
| `--nb-page-title-size` | `1.25rem` | `1.25rem` | Ukuran font `.page-title` |
| `--nb-page-subtitle-size` | `0.85rem` | `0.85rem` | Ukuran font `.page-subtitle` |
| `--nb-control-sm-height` | `31px` | `31px` | Tinggi baku kontrol form `-sm` dan tombol `-sm` |

> **Perhatian**: Dilarang menggunakan pembungkus halaman ad-hoc seperti `container-fluid px-* py-*` atau utilitas margin/padding di level halaman. Jarak halaman diatur otomatis oleh komponen layout.

---

## 2. Struktur Wajib Halaman Baru

Setiap halaman modul baru wajib mengikuti kerangka baku berikut:

```blade
@extends('layouts.app')
@section('title', 'Judul Modul')

@section('content')
    <x-page-header title="Judul Modul" subtitle="Deskripsi ringkas modul">
        <x-slot:actions>
            <x-btn variant="primary" id="btn-tambah">Tambah Data</x-btn>
        </x-slot:actions>
    </x-page-header>

    <x-filter-card>
        <form class="row g-2 align-items-center">
            <!-- Gunakan form-control-sm dan form-select-sm -->
        </form>
    </x-filter-card>

    <x-table-card>
        <table id="my-table" class="table table-hover align-middle mb-0 w-100">
            <!-- Header dan Body -->
        </table>
    </x-table-card>

    @include('mymodule.partials.offcanvas')
@endsection
```

---

## 3. Komponen Tombol (`<x-btn>` & `<x-btn-icon>`)

### Tombol Bertulis (`<x-btn>`)
Digunakan untuk aksi utama, filter, modal, dan offcanvas. **Dilarang memakai ikon dekoratif pada tombol bertulis**.

- `variant="primary"` (`btn-primary`): Simpan, Tambah, Buat, Kirim, Cari.
- `variant="approve"` (`btn-success`): Setujui, Verifikasi, Tandai Dibayar, Tugaskan, Mulai.
- `variant="warning"` (`btn-warning`): Minta Perbaikan, Minta Survei Ulang.
- `variant="danger"` (`btn-danger`): Hapus, Tolak, Batalkan, Nonaktifkan.
- `variant="neutral"` (`btn-outline-secondary`): Batal, Tutup, Reset, Kembali.
- `variant="export"` (`btn-outline-primary`): Ekspor, Unduh.

### Tombol Aksi Baris Tabel (`<x-btn-icon>`)
Digunakan untuk baris tabel/list. Ikon saja dengan `title` dan `aria-label`:
- `view`: detail (`btn-outline-secondary`, `bi-eye`)
- `edit`: ubah (`btn-outline-primary`, `bi-pencil`)
- `delete`: hapus (`btn-outline-danger`, `bi-trash`)
- `route`, `call`, `copy`, `download`: utilitas baris.
- Pada DataTables client/server, panggil helper JavaScript: `window.btnIconHtml(action, attrs)` atau `window.btnHtml(variant, text, attrs)`.

---

## 4. Badge Status (`<x-status-badge>`)

Badge status mengacu pada pemetaan tunggal di `config/ui.php`. Memakai 5 kelas semantik konsisten:
- `bg-success`: selesai, lunas, aktif, feasible, verified.
- `bg-warning`: pending, proses, menunggu bayar, partial, in-progress.
- `bg-danger`: ditolak, batal, suspend, nonaktif, expired, urgent, kendala.
- `bg-info`: baru, open, informasi.
- `bg-secondary`: draft, closed, neutral, final.

Blade: `<x-status-badge type="service_status" :status="$item->status" />`  
JavaScript DataTables: `window.statusBadge(type, status)`

---

## 5. Larangan Khusus (Zero-Slop & Zero-Emoji)

1. **Tanpa Karakter Emoji**: Dilarang menggunakan emoji atau karakter piktograf Unicode (`\p{Extended_Pictographic}`) di seluruh view, file JS, pesan toast, lang, dan seeders.
2. **Tanpa Ikon Dekoratif**: Dilarang meletakkan `<i class="bi ...">` di judul halaman, kartu statistik, header card/offcanvas, label form, atau di dalam tombol bertulis. Ikon hanya diperbolehkan pada menu sidebar, tombol aksi baris (`<x-btn-icon>`), dan indikator fungsional (chevron/sort).

---

## 6. Penyeragaman Offcanvas (`<x-offcanvas-form>`) — Fase 17m

SEMUA elemen `.offcanvas` di seluruh sistem NetBill **wajib** menggunakan komponen baku `<x-offcanvas-form>`. Dilarang menulis markup `div.offcanvas` langsung di file view.

### Larangan Mutlak Lebar Kustom Ad-Hoc (Baku Seragam 600px)
Mulai Fase 17m, seluruh offcanvas NetBill memakai standar lebar seragam 600px (`.offcanvas-end` 600px via override `--bs-offcanvas-width: 600px;` pada desktop & tablet, dan 100% pada layar mobile &le;576px).

1. **Lebar Baku Seragam 600px**: Dilarang menetapkan lebar kustom per halaman (`--nb-offcanvas-width-*`, kelas `crud-offcanvas`, atau properti CSS `width`/`min-width`/`max-width` kustom di view). Seluruh offcanvas menggunakan variabel seragam `--bs-offcanvas-width: 600px;`.
2. **Tanpa Prop Size**: Komponen `<x-offcanvas-form>` tidak lagi menerima prop `size="md|lg"`. Seluruh offcanvas memiliki lebar baku yang 100% seragam.
3. **Atribut Bawaan**: Komponen secara otomatis menyertakan `data-bs-scroll="true"` dan `data-bs-backdrop="true"`.
4. **Header & Footer Sticky**: Bagian header (`.offcanvas-header`) dan footer (`.offcanvas-footer`) memiliki styling `position: sticky` dengan background `#f8fafc`.
5. **Gaya Form Internal & Adaptasi 600px**:
   - Form input dibuat satu atau dua kolom proporsional menggunakan grid Bootstrap (`col-12` / `col-md-6`).
   - Detail foto memakai grid thumbnail (`row row-cols-2 g-2` atau `row-cols-3`) yang membuka foto resolusi penuh di tab baru lewat route berotorisasi.
   - Tabel di dalam offcanvas wajib dibungkus `.table-responsive` atau ditumpuk vertikal.
   - Dilarang ada horizontal overflow (`scrollWidth > clientWidth`) pada body offcanvas di resolusi 360px maupun 1280px.

### Contoh Penggunaan
```blade
{{-- Form Standar & Detail Seragam (Lebar Baku 600px) --}}
<x-offcanvas-form id="offcanvas-item" formId="form-item" title="Tambah Data">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label small fw-semibold">Nama <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required>
        </div>
    </div>
</x-offcanvas-form>

{{-- Detail / Riwayat / Verifikasi --}}
<x-offcanvas-form id="offcanvas-detail" title="Detail Data">
    {{-- Konten detail satu kolom / responsif --}}
    <x-slot:footer>
        <x-btn variant="neutral" data-bs-dismiss="offcanvas">Tutup</x-btn>
    </x-slot:footer>
</x-offcanvas-form>
```

---

## 7. Mekanisme Filter (`<x-filter-card>`) & Parameter URL — Fase 17k

Semua form filter wajib dibungkus dalam `<x-filter-card>` dengan memilih mode yang sesuai:

### Mode Filter
1. **`mode="instant"` (Bawaan)**:
   - Digunakan pada seluruh tabel DataTables server-side dan daftar kartu kecil.
   - Pilihan `select`, tanggal, dan `checkbox` langsung memperbarui hasil seketika.
   - Input teks pencarian menggunakan debounce 400ms (minimal 2 karakter atau kosong).
   - Tombol hanya "Reset" (`variant="neutral"`).
   - Sinkronisasi URL otomatis menggunakan `history.replaceState` (tanpa reload halaman).
   - Pengecualian: Halaman Cari Pelanggan NOC (`/customer-lookup`) yang memiliki rate-limit backend (30 req/menit) menggunakan submit eksplisit (Enter / tombol Cari, min 3 karakter).
2. **`mode="apply"`**:
   - Khusus untuk halaman server-rendered agregat/chart (Dashboard).
   - Tombol "Filter" (`variant="primary"`) dan "Reset" (`variant="neutral"`).
   - Menjalankan submit GET standar.

### Standarisasi Nama Parameter URL
| Parameter Standar | Alias Lama (Ditoleransi di Backend) | Keterangan |
|---|---|---|
| `q` | `search`, `keyword` | Kata kunci pencarian teks |
| `status` | `service_status` | Status utama (pelanggan, tiket, survei, dsb.) |
| `from` | `start_date`, `date_from` | Batas awal tanggal (format `YYYY-MM-DD`) |
| `to` | `end_date`, `date_to` | Batas akhir tanggal (format `YYYY-MM-DD`) |
| `period` | - | Rentang waktu dashboard (`today`, `7d`, `30d`, `this_month`, dll.) |
| `pop_id` | - | ID POP (angka/integer) |

### Kontrak Integrasi Backend & Frontend
- **Tanpa HTTP 500**: Query string sampah atau invalid di-sanitize menggunakan trait `SanitizesFilterInput`. Nilai non-numerik untuk ID atau enum yang tidak terdaftar ditolak secara diam-diam.
- **Tanpa Double Request**: Saat pemuatan awal halaman dengan URL query berfilter, `hydrateFormFromUrl` mengisi nilai form terlebih dahulu sebelum inisialisasi AJAX DataTables, sehingga hanya tepat 1 HTTP request yang dikirim.
- **Ekspor Sinkron**: Tombol Ekspor selalu otomatis menyinkronkan query string aktif sehingga data yang diunduh persis sesuai filter yang sedang tampil.

---

## 8. Skala Tipografi Baku Seluruh Aplikasi — Fase 17l

Untuk menjaga konsistensi visual dan hierarki yang proporsional di seluruh aplikasi, NetBill membakukan skala tipografi ke dalam **maksimal 6 ukuran** (satuan `rem`, basis 16px):

| Token CSS | Nilai | Piksel | Berat (Weight) | Tinggi Baris (Line-Height) | Peran Komponen |
|---|---|---|---|---|---|
| `--nb-font-2xl` | `1.5rem` | 24px | 700 (Bold) | 1.25 (`--nb-lh-tight`) | Nilai metrik stat card (`.stat-value`) |
| `--nb-font-xl` | `1.25rem` | 20px | 700 (Bold) | 1.25 (`--nb-lh-tight`) | Judul halaman utama (`.page-title`, `h1`) |
| `--nb-font-lg` | `1.125rem` | 18px | 600 / 700 | 1.25 (`--nb-lh-tight`) | Judul modal SweetAlert (`.swal2-title`), Judul kartu (`.card-header`, `.card-title`), Judul offcanvas (`.offcanvas-title`), Subheading |
| `--nb-font-base` | `0.875rem` | 14px | 400 / 600 | 1.5 (`--nb-lh-base`) | Isi tabel (`td`), label form (`.form-label`), kontrol form (`.form-control`, `.form-select`), tombol (`.btn-sm`), teks kartu tugas (`x-task-card`), sidebar nav, isi modal SweetAlert & toast |
| `--nb-font-sm` | `0.8125rem` | 13px | 400 / 600 | 1.45 (`--nb-lh-normal`) | Subjudul halaman (`.page-subtitle`), header tabel (`th`), teks bantu form (`.form-text`, `.form-help`), info & paginasi DataTables, label stat card |
| `--nb-font-xs` | `0.75rem` | 12px | 600 (Semibold) | 1.2 / 1.45 | **Batas Minimum Mutlak**: Badge status (`<x-status-badge>`), pesan error form (`.invalid-feedback`), teks bantu metrik, kode monospace |

### Larangan & Aturan Tipografi
1. **Dilarang Font-Size Inline**: Tidak diperbolehkan menyematkan `style="font-size: ..."` di file view Blade mana pun.
2. **Dilarang Kelas Ad-hoc**: Dilarang menggunakan kelas Bootstrap `fs-1` sampai `fs-6` di file view baru. Seluruh ukuran font diatur secara terpusat oleh token komponen.
3. **Batas Minimum 12px**: Tidak ada teks yang boleh tampil di bawah `0.75rem` (12px). Aturan tag `small`, `code`, dan `badge` dikunci pada minimal `var(--nb-font-xs)` (12px).
4. **Maksimal 7 Ukuran per Halaman**: Setiap halaman hanya boleh memuat variasi teks dari skala 6 ukuran di atas, menjamin tampilan yang bersih, tenang, dan profesional.

---

## 9. Menjalankan Tes E2E Alur Tulis (Playwright) — Fase 17i

Untuk memvalidasi integrasi end-to-end seluruh alur bisnis tulis aplikasi (stateful write flows) secara serial terhadap database pengujian khusus (`db_netbill_e2e`):

```bash
# Menjalankan hanya suite alur tulis (@flow)
npm run e2e:flow

# Menjalankan seluruh suite E2E (termasuk kontrak UI dan alur tulis)
npm run e2e
```

### Skenario yang Diuji dalam Alur Tulis (@flow):
- **Alur A (Jalur Utama)**: Pendaftaran Marketing POP EP &rarr; Approval Admin EP (terbit kode `JCN-EP-xxxx`) &rarr; Survei Layak oleh Surveyor EP (ODC, ODP, 2 material) &rarr; Admin menugaskan Teknisi EP &rarr; Teknisi mengerjakan tugas & unggah 2 foto dokumentasi &rarr; Admin verifikasi penugasan &rarr; Status pelanggan `ACTIVE`, `subscribed_at` terisi, 4 tahap riwayat pemasangan tampil & tepat 1 baris Fee Marketing &rarr; Proteksi multi-tenant (Admin/Surveyor/Teknisi POP lain tidak dapat melihat atau mengakses 404).
- **Alur B (Kunjungan Perbaikan)**: NOC membuat tiket perbaikan koneksi untuk pelanggan Alur A &rarr; Ubah status ke `IN_PROGRESS` & minta kunjungan teknisi &rarr; Admin menugaskan teknisi repair &rarr; Teknisi unggah 1 foto & kirim &rarr; Admin verifikasi &rarr; Tiket kembali `IN_PROGRESS`, pelanggan tetap `ACTIVE`, fee tetap 1 baris &rarr; NOC menyelesaikan dan menutup tiket.
- **Alur C (Tidak Layak)**: Admin mendaftarkan pelanggan `PENDING` langsung &rarr; Surveyor kirim hasil `NOT_FEASIBLE` &rarr; Admin batalkan pelanggan &rarr; Status `INACTIVE`, hilang dari antrean survei, dan tanpa fee marketing.
- **Alur D (Revisi Pendaftaran)**: Marketing lain mengajukan calon pelanggan &rarr; Admin meminta perbaikan dengan catatan wajib &rarr; Marketing memperbaiki data & mengirim ulang &rarr; Admin menyetujui (terbit JCN baru tanpa duplikasi pelanggan).




