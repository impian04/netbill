# Dokumentasi NetBill Customer API v1

## Base URL & Header

- **Base URL**: `http://localhost:8000/api/v1` (Production: `https://<domain>/api/v1`)
- **Headers Wajib**:
  ```http
  Accept: application/json
  Content-Type: application/json
  Authorization: Bearer <token>
  ```
  *(Header Authorization diperlukan pada endpoint terproteksi)*

---

## Catatan Penting
- **Field `amount`**: Nilai berupa integer rupiah murni (contoh `165000` = Rp165.000). Bersumber dari kolom database `deferred_amount`.
- **Format Tanggal**: ISO 8601 UTC untuk timestamp, `YYYY-MM` untuk periode tagihan, `YYYY-MM-DD` untuk due date.
- **Single Session**: Login baru akan mencabut token sebelumnya dari akun pelanggan yang sama.

---

## Alur Aktivasi → Login

1. Admin membuat kode aktivasi sekali pakai 8 karakter (misal `ABCD-EFGH`).
2. Aplikasi mobile mengirim `POST /auth/activate`. Jika sukses, token langsung dikembalikan tanpa perlu login ulang.
3. Untuk login berikutnya di sesi mendatang, gunakan `POST /auth/login`.

---

## Daftar Endpoint

### 1. Aktivasi Akun
`POST /auth/activate` (Tanpa Auth)

**Request Body:**
```json
{
  "customer_code": "SLX-BT-00001",
  "phone": "08123456789",
  "activation_code": "ABCD-EFGH",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "device_name": "Samsung Galaxy A54"
}
```
**Respons (200 OK):**
```json
{
  "token": "1|qW8e...plainTextToken",
  "profile": {
    "customer_code": "SLX-BT-00001",
    "name": "Budi Santoso",
    "phone": "628123456789",
    "address": "Jl. Merdeka No. 10",
    "package": "50 Mbps",
    "billing_type": "Bulanan",
    "service_status": "ACTIVE",
    "subscribed_at": "2024-01-15"
  }
}
```

---

### 2. Login
`POST /auth/login` (Tanpa Auth)

**Request Body:**
```json
{
  "customer_code": "SLX-BT-00001",
  "password": "Password123!",
  "device_name": "Samsung Galaxy A54"
}
```
**Respons (200 OK):**
```json
{
  "token": "2|rT9u...plainTextToken",
  "profile": {
    "customer_code": "SLX-BT-00001",
    "name": "Budi Santoso",
    "phone": "628123456789",
    "address": "Jl. Merdeka No. 10",
    "package": "50 Mbps",
    "billing_type": "Bulanan",
    "service_status": "ACTIVE",
    "subscribed_at": "2024-01-15"
  }
}
```

---

### 3. Logout
`POST /auth/logout` (Bearer Auth)

**Respons (200 OK):**
```json
{
  "message": "Logout berhasil."
}
```

---

### 4. Profil Saya
`GET /me` (Bearer Auth)

**Respons (200 OK):**
```json
{
  "customer_code": "SLX-BT-00001",
  "name": "Budi Santoso",
  "phone": "628123456789",
  "address": "Jl. Merdeka No. 10",
  "package": "50 Mbps",
  "billing_type": "Bulanan",
  "service_status": "ACTIVE",
  "subscribed_at": "2024-01-15"
}
```

---

### 5. Ganti Password
`POST /me/password` (Bearer Auth)

**Request Body:**
```json
{
  "current_password": "Password123!",
  "password": "NewSecret456!",
  "password_confirmation": "NewSecret456!"
}
```
**Respons (200 OK):**
```json
{
  "message": "Password berhasil diperbarui."
}
```

---

### 6. Home / Dashboard Ringkasan
`GET /home` (Bearer Auth)

**Respons (200 OK):**
```json
{
  "customer": {
    "customer_code": "SLX-BT-00001",
    "name": "Budi Santoso",
    "package": "50 Mbps",
    "service_status": "ACTIVE"
  },
  "billing_summary": {
    "unpaid_count": 1,
    "unpaid_total_amount": 165000,
    "latest_unpaid_invoice": {
      "id": 12,
      "invoice_number": "INV-202610-000012",
      "period": "2026-10",
      "amount": 165000,
      "status": "UNPAID",
      "due_date": "2026-10-20",
      "paid_at": null,
      "payment_reference": null
    }
  },
  "announcements": [
    {
      "id": 1,
      "title": "Pemeliharaan Jaringan Rutin",
      "body": "Akan dilakukan maintenance pada 25 Oktober 2026 pukul 01:00 - 04:00 WIB.",
      "published_at": "2026-10-20T08:00:00.000000Z"
    }
  ]
}
```

---

### 7. Daftar Tagihan
`GET /invoices?period=2026-10&status=UNPAID&per_page=15` (Bearer Auth)

**Query Parameters:**
- `period` *(opsional)*: Format `YYYY-MM`
- `status` *(opsional)*: `PAID` atau `UNPAID`
- `per_page` *(opsional)*: Integer 1-50 (default: 15)

**Respons (200 OK):**
```json
{
  "data": [
    {
      "id": 12,
      "invoice_number": "INV-202610-000012",
      "period": "2026-10",
      "amount": 165000,
      "status": "UNPAID",
      "due_date": "2026-10-20",
      "paid_at": null,
      "payment_reference": null
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1 }
}
```

---

### 8. Detail Tagihan
`GET /invoices/{id}` (Bearer Auth)

*(Catatan: Proteksi IDOR aktif. Mengakses tagihan milik pelanggan lain mengembalikan status 404)*

**Respons (200 OK):**
```json
{
  "id": 12,
  "invoice_number": "INV-202610-000012",
  "period": "2026-10",
  "amount": 165000,
  "status": "UNPAID",
  "due_date": "2026-10-20",
  "paid_at": null,
  "payment_reference": null,
  "customer": {
    "customer_code": "SLX-BT-00001",
    "name": "Budi Santoso",
    "address": "Jl. Merdeka No. 10",
    "package": "50 Mbps"
  }
}
```

---

### 9. Kontak Dukungan NOC
`GET /support-contacts` (Bearer Auth)

**Respons (200 OK):**
```json
{
  "data": {
    "connection": {
      "channel": "WhatsApp Gangguan Koneksi",
      "number": "62811223344"
    },
    "payment": {
      "channel": "WhatsApp Konfirmasi Pembayaran",
      "number": "62811223355"
    },
    "installation": {
      "channel": "WhatsApp Pasang Baru / Relokasi",
      "number": "62811223366"
    }
  }
}
```

---

## Format Error & Kode Status

Semua respons error selalu berupa JSON bersih tanpa kebocoran stack trace/internal framework:

| Kode Status | Keterangan | Contoh Body |
|---|---|---|
| **401 Unauthorized** | Token hilang / tidak valid / kedaluwarsa | `{"message": "Tidak terautentikasi. Silakan login terlebih dahulu."}` |
| **403 Forbidden** | Akun berstatus INACTIVE | `{"message": "Akun Anda tidak aktif. Hubungi layanan pelanggan."}` |
| **404 Not Found** | Data tidak ada atau milik pelanggan lain (IDOR) | `{"message": "Data tidak ditemukan."}` |
| **422 Unprocessable** | Validasi gagal / data input salah | `{"message": "Data yang dimasukkan tidak valid atau kode salah."}` |
| **429 Too Many Req** | Rate limit auth (5×/menit) terlampaui | `{"message": "Terlalu banyak percobaan. Coba lagi dalam 1 menit."}` |
| **500 Server Error** | Kesalahan tak terduga di server | `{"message": "Terjadi kesalahan pada server."}` |
