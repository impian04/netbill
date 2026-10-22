# Dokumentasi NetBill Customer API v1

Dokumentasi resmi REST API backend NetBill untuk aplikasi mobile pelanggan (React Native).

---

## 1. Lingkungan & Base URL

| Lingkungan | Base URL | Keterangan |
|---|---|---|
| **Android Emulator** | `http://10.0.2.2:8000/api/v1` | Alias host localhost pada Android Studio Emulator |
| **Perangkat Fisik (LAN / Wi-Fi)** | `http://<IP_LAN_KOMPUTER>:8000/api/v1` | Contoh: `http://192.168.1.50:8000/api/v1` |
| **Produksi *(Placeholder)*** | `https://api.netbill.id/api/v1` | Domain placeholder; sesuaikan dengan domain server rilis publik Anda |

### Header Wajib
```http
Accept: application/json
Content-Type: application/json
```
Untuk endpoint terproteksi, sertakan Bearer Token:
```http
Authorization: Bearer 1|xxxxxxxx
```

---

## 2. Aturan Data & Format

- **Envelope Standar**: Seluruh respons sukses data dibungkus dalam properti `"data": ...`. Khusus aksi tanpa data kembalian (logout, ganti password), mengembalikan `{"message": "..."}`.
- **Nominal Tagihan (`amount`)**: Integer rupiah murni (contoh: `165000` = Rp165.000, bukan string dan tanpa pemisah ribuan). Berasal dari kolom database `deferred_amount`.
- **Aturan Kata Sandi**: Minimal 8 karakter, wajib memuat kombinasi huruf dan angka (`min:8`, `letters`, `numbers`).
- **Nama Perangkat (`device_name`)**: String nama/model perangkat (contoh: `"Xiaomi Redmi Note 12"`, `"iPhone 14 Pro"`), panjang maksimal 100 karakter (`max:100`).
- **Alamat Pelanggan (`address`)**: Dapat memuat karakter baris baru (`\n`). Di React Native, render langsung dengan `<Text>{profile.address}</Text>` tanpa pemotongan agar detail blok/RT/RW tampil utuh.
- **Masa Berlaku Token**: Token Sanctum berlaku selama **30 hari**.
- **Multi-Perangkat**: Maksimal **5 sesi/token aktif** per pelanggan. Jika login di perangkat ke-6, token tertua otomatis dihapus.
- **Normalisasi Nomor Telepon**: Menerima format `08...`, `+62...`, atau `62...`. Backend otomatis menormalisasinya ke format `62...` (contoh: `08123456789` menjadi `628123456789`).
- **Jatuh Tempo (`due_date`)**: Bersifat opsional (`nullable`), bernilai string tanggal `YYYY-MM-DD` atau `null`. **Aplikasi mobile harus menyembunyikan label/tanggal jatuh tempo jika nilainya `null`** (misalnya pada tagihan lama atau paket tanpa batas jatuh tempo kaku).
- **Urutan Default Tagihan**: `GET /invoices` selalu diurutkan berdasarkan `period DESC` (periode terbaru tampil lebih awal).
- **Aturan Paginasi**: **Abaikan objek `links` dan array `meta.links`** pada respons paginasi tagihan, karena URL di dalamnya mengacu pada konfigurasi internal `APP_URL` backend (contoh: `http://localhost`). Aplikasi mobile **wajib hanya menggunakan properti `meta.current_page` dan `meta.last_page`** dari objek `meta`, lalu menyusun pemanggilan `GET /api/v1/invoices?page={page}` secara mandiri dengan Base URL aktif.

---

## 3. Status Layanan Pelanggan (`service_status`)

| Nilai Status | Perilaku pada Aplikasi Mobile |
|---|---|
| `ACTIVE` | Layanan aktif normal. Dapat login dan mengakses seluruh endpoint bertoken. |
| `SUSPENDED` | Layanan diisolir/ditangguhkan sementara. **Tetap diizinkan login** agar pelanggan dapat melihat rincian tagihan belum lunas dan menghubungi NOC. |
| `INACTIVE` | Layanan nonaktif/berhenti berlangganan. **Ditolak 403 Forbidden** (hanya setelah password valid pada saat login; jika password salah tetap 401). Token yang sudah terbit sebelumnya otomatis ditolak 403 di semua endpoint bertoken. |
| `PENDING` | Registrasi baru/menunggu instalasi fisik. Dapat melakukan aktivasi akun saat admin telah menerbitkan kode aktivasi. |

---

## 4. Alur Autentikasi

### A. Alur Aktivasi Pertama Kali
1. Admin menerbitkan kode aktivasi 8 karakter (misal: `ABCD-EFGH`) dengan masa berlaku 7 hari.
2. Aplikasi mobile mengirimkan `POST /auth/activate`.
3. Jika berhasil, server mengembalikan token dan profil pelanggan. Pelanggan langsung masuk tanpa perlu login ulang.
4. *Proteksi Brute-Force*: Jika salah memasukkan kode 5 kali berturut-turut, akun terkunci sementara selama 15 menit. Jika salah hingga 100 kali, akun terkunci permanen dan pelanggan wajib meminta kode aktivasi baru ke admin. Respons error ke aplikasi tetap berbentuk pesan generik 422.

### B. Alur Login Rutin
1. Pelanggan memasukkan Kode Pelanggan dan Kata Sandi pada `POST /auth/login`.
2. Token baru diterbitkan (maksimal 5 perangkat aktif, token terlama dipangkas jika melampaui kuota).

### C. Alur Lupa Password
1. Pelanggan melapor ke admin atau kantor layanan NetBill.
2. Admin men-generate ulang kode aktivasi baru melalui dashboard NetBill (tindakan ini otomatis mencabut semua token sesi dan mereset kata sandi).
3. Pelanggan memanggil kembali endpoint `POST /auth/activate` dengan kode aktivasi baru untuk mengatur kata sandi baru.

---

## 5. Daftar Endpoint & Contoh Respons Asli

### 1. Aktivasi Akun
`POST /auth/activate` (Publik / Rate Limit Auth)

**Request Body:**
```json
{
  "customer_code": "SLX-TG-00004",
  "phone": "08690160440",
  "activation_code": "ABCD-EFGH",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "device_name": "Xiaomi Redmi Note 12"
}
```
**Respons Sukses (200 OK):**
```json
{
  "data": {
    "token": "1|xxxxxxxx",
    "profile": {
      "customer_code": "SLX-TG-00004",
      "name": "Nathen Sporer",
      "address": "9946 Wiegand Square Suite 053\nHoegerview, NE 83664-1126",
      "phone": "628690160440",
      "package": "100 Mbps",
      "billing_type": "Bulanan",
      "service_status": "ACTIVE",
      "subscribed_at": "2024-11-28"
    }
  }
}
```

---

### 2. Login
`POST /auth/login` (Publik / Rate Limit Auth)

**Request Body:**
```json
{
  "customer_code": "SLX-BT-00001",
  "password": "Password123!",
  "device_name": "Xiaomi Redmi Note 12"
}
```
**Respons Sukses (200 OK):**
```json
{
  "data": {
    "token": "1|xxxxxxxx",
    "profile": {
      "customer_code": "SLX-BT-00001",
      "name": "Dr. Melyssa Durgan",
      "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
      "phone": "628123456789",
      "package": "100 Mbps",
      "billing_type": "Bulanan",
      "service_status": "ACTIVE",
      "subscribed_at": "2024-05-16"
    }
  }
}
```

---

### 3. Profil Pelanggan
`GET /me` (Bearer Auth)

**Respons Sukses (200 OK):**
```json
{
  "data": {
    "customer_code": "SLX-BT-00001",
    "name": "Dr. Melyssa Durgan",
    "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
    "phone": "628123456789",
    "package": "100 Mbps",
    "billing_type": "Bulanan",
    "service_status": "ACTIVE",
    "subscribed_at": "2024-05-16"
  }
}
```

---

### 4. Ringkasan Home / Dashboard
`GET /home` (Bearer Auth)

*Catatan:*
- `latest_unpaid`: Daftar tagihan belum lunas (`status = UNPAID`), dibatasi maksimal 5 item, diurutkan dari periode terbaru (`period DESC`).
- `announcements`: Daftar pengumuman jaringan atau info penting aktif yang dipublikasikan oleh tim operasional NetBill.

**Respons Sukses (200 OK):**
```json
{
  "data": {
    "unpaid_count": 1,
    "total_unpaid_amount": 220000,
    "latest_unpaid": [
      {
        "id": 3,
        "invoice_number": "INV-202608-000003",
        "period": "2026-08",
        "due_date": null,
        "amount": 220000,
        "status": "UNPAID",
        "paid_at": null,
        "payment_reference": null,
        "customer_code": "SLX-BT-00001",
        "customer_name": "Dr. Melyssa Durgan",
        "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
        "package": "100 Mbps"
      }
    ],
    "announcements": [
      {
        "id": 1,
        "title": "Pemeliharaan Jaringan Rutin",
        "body": "Halo Pelanggan NetBill,\n\nKami akan melakukan peningkatan infrastruktur pada hari Minggu, 25 Oktober 2026 pukul 01:00 - 04:00 WIB. Selama periode ini, layanan internet mungkin mengalami interupsi sesaat.\n\nTerima kasih atas pengertian Anda.",
        "published_at": "2026-10-20T08:00:00+00:00",
        "expires_at": null
      }
    ]
  }
}
```

---

### 5. Riwayat & Daftar Tagihan
`GET /invoices?period=2026-10&status=PAID&per_page=15` (Bearer Auth)

**Query Parameters:**
- `period` *(opsional)*: Filter format `YYYY-MM`
- `status` *(opsional)*: `PAID` atau `UNPAID`
- `per_page` *(opsional)*: Jumlah item per halaman 1–50 (default: 15)
- `page` *(opsional)*: Nomor halaman paginasi

**Respons Sukses (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-202610-000001",
      "period": "2026-10",
      "due_date": null,
      "amount": 165000,
      "status": "PAID",
      "paid_at": "2026-10-22T05:19:38+00:00",
      "payment_reference": null,
      "customer_code": "SLX-BT-00001",
      "customer_name": "Dr. Melyssa Durgan",
      "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
      "package": "100 Mbps"
    },
    {
      "id": 2,
      "invoice_number": "INV-202609-000002",
      "period": "2026-09",
      "due_date": null,
      "amount": 110000,
      "status": "PAID",
      "paid_at": "2026-10-22T05:19:38+00:00",
      "payment_reference": null,
      "customer_code": "SLX-BT-00001",
      "customer_name": "Dr. Melyssa Durgan",
      "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
      "package": "100 Mbps"
    }
  ],
  "links": {
    "first": "http://localhost/api/v1/invoices?page=1",
    "last": "http://localhost/api/v1/invoices?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {
        "url": null,
        "label": "&laquo; Sebelumnya",
        "page": null,
        "active": false
      },
      {
        "url": "http://localhost/api/v1/invoices?page=1",
        "label": "1",
        "page": 1,
        "active": true
      },
      {
        "url": null,
        "label": "Berikutnya &raquo;",
        "page": null,
        "active": false
      }
    ],
    "path": "http://localhost/api/v1/invoices",
    "per_page": 15,
    "to": 2,
    "total": 2
  }
}
```

---

### 6. Rincian Tagihan
`GET /invoices/{id}` (Bearer Auth)

*(Proteksi IDOR: Mengakses tagihan milik pelanggan lain mengembalikan status 404)*

**Respons Sukses (200 OK):**
```json
{
  "data": {
    "id": 1,
    "invoice_number": "INV-202610-000001",
    "period": "2026-10",
    "due_date": null,
    "amount": 165000,
    "status": "PAID",
    "paid_at": "2026-10-22T05:19:38+00:00",
    "payment_reference": null,
    "customer_code": "SLX-BT-00001",
    "customer_name": "Dr. Melyssa Durgan",
    "address": "3537 Bradtke Junction Apt. 240\nWest Melynaberg, PA 38926",
    "package": "100 Mbps"
  }
}
```

---

### 7. Kontak Layanan NOC
`GET /support-contacts` (Bearer Auth)

**Respons Sukses (200 OK):**
```json
{
  "data": [
    {
      "category": "connection",
      "label": "Masalah Koneksi",
      "phone": "62811223344"
    },
    {
      "category": "payment",
      "label": "Masalah Pembayaran",
      "phone": "62811223355"
    },
    {
      "category": "installation",
      "label": "Bantuan Pemasangan/Pemasaran",
      "phone": "62811223366"
    }
  ]
}
```

---

### 8. Ganti Kata Sandi
`POST /me/password` (Bearer Auth)

*(Tindakan ini otomatis mencabut token sesi di perangkat lain, menyisakan sesi perangkat saat ini)*

**Request Body:**
```json
{
  "current_password": "Password123!",
  "password": "NewSecret456!",
  "password_confirmation": "NewSecret456!"
}
```
**Respons Sukses (200 OK):**
```json
{
  "message": "Password berhasil diperbarui."
}
```

---

### 9. Logout
`POST /auth/logout` (Bearer Auth)

**Respons Sukses (200 OK):**
```json
{
  "message": "Logout berhasil."
}
```

---

## 6. Standarisasi Respons Error & Kode Status

Semua error selalu berbentuk JSON bersih tanpa eksposur detail internal framework:

### A. 401 Unauthorized — Sesi Kedaluwarsa / Belum Login
```json
{
  "message": "Tidak terautentikasi. Silakan login terlebih dahulu."
}
```

### B. 401 Unauthorized — Kredensial Login Salah
```json
{
  "message": "Kode pelanggan atau password salah."
}
```

### C. 403 Forbidden — Akun Nonaktif (`INACTIVE`)
*(Pada `/auth/login`, respons ini hanya muncul setelah password valid; jika password salah tetap 401. Pada endpoint bertoken, token yang sudah terbit langsung ditolak dengan status 403).*
```json
{
  "message": "Akun Anda tidak aktif. Hubungi layanan pelanggan."
}
```

### D. 404 Not Found — Tagihan Tidak Ditemukan / Proteksi IDOR
```json
{
  "message": "Data tidak ditemukan."
}
```

### E. 422 Unprocessable Entity — Aktivasi Gagal (Generik)
*(Digunakan seragam saat kode salah, kedaluwarsa, akun terkunci sementara 15 menit [tiap 5x salah] atau permanen [100x salah], atau nomor telepon tidak cocok)*
```json
{
  "message": "Kode tidak valid atau sudah tidak berlaku. Hubungi admin untuk kode baru."
}
```

### F. 422 Unprocessable Entity — Kesalahan Validasi Input Form
```json
{
  "message": "Kode pelanggan wajib diisi. (dan 2 kesalahan lainnya)",
  "errors": {
    "customer_code": [
      "Kode pelanggan wajib diisi."
    ],
    "password": [
      "Kata sandi wajib diisi."
    ],
    "device_name": [
      "Nama perangkat wajib diisi."
    ]
  }
}
```

### G. 429 Too Many Requests — Pembatasan Akses (Rate Limiting)
- Endpoint Auth (`/auth/activate`, `/auth/login`): Dibatasi **5 kali/menit per kombinasi customer_code + IP** dan **30 kali/menit per IP**.
- Endpoint Pelanggan: Dibatasi **60 kali/menit per token**.
```json
{
  "message": "Terlalu banyak percobaan. Coba lagi dalam 1 menit."
}
```

### H. 500 Internal Server Error
```json
{
  "message": "Terjadi kesalahan pada server."
}
```

---

## 7. Catatan Khusus Pengembang Mobile (React Native)

1. **Penyimpanan Token Aman**:
   - Simpan token hasil autentikasi menggunakan **Secure Storage terenkripsi hardware**:
     - iOS: **Keychain Services** (`react-native-keychain` / `expo-secure-store`).
     - Android: **EncryptedSharedPreferences / Android Keystore**.
   - **JANGAN PERNAH** menyimpan token di `AsyncStorage` biasa tanpa enkripsi.
2. **Keamanan Logging**:
   - Jangan mencetak token ke `console.log` maupun mengirimkan token mentah ke pelacak analitik/crash (Sentry, Firebase Crashlytics, dll).
3. **Penanganan Error 401 & 403 Global**:
   - Pasang interceptor HTTP (Axios / Fetch).
   - **Status 401 Unauthorized**: Interceptor otomatis bersihkan token dan kembali ke Login **hanya untuk endpoint bertoken** (misalnya `GET /me`, `GET /invoices`, `GET /home`). Pada endpoint publik/auth (`POST /auth/login` dan `POST /auth/activate`), status 401 adalah akibat kredensial salah; tangani secara lokal di form tanpa me-reset sesi atau memicu redirect global.
   - **Status 403 Forbidden (`INACTIVE`)**: Jika menerima status 403 dari endpoint bertoken (karena admin menonaktifkan status layanan pelanggan), tampilkan pesan error dari server (`"Akun Anda tidak aktif. Hubungi layanan pelanggan."`) dan lakukan **logout lokal** (hapus token dari secure storage serta arahkan kembali ke layar Login).
4. **Enkripsi Jaringan**:
   - Wajib gunakan protokol HTTPS di server produksi untuk mencegah penyadapan Bearer Token.
