# CoFund REST API — Overview & Standar Protokol

## 1. Judul & Deskripsi Modul

Modul Overview mendefinisikan konvensi umum, standar arsitektur RESTful API, format envelope response JSON global, kode status HTTP, header autentikasi, aturan sanitasi data, serta penanganan error global untuk seluruh antarmuka pemrograman aplikasi (API) CoFund.

**Base URL:** `http://localhost:8000/api/v1`  
**Protokol:** HTTP/1.1 & HTTPS  
**Format Data:** JSON (`application/json`)

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **Route Definitions** | `backend/routes/api.php` | Routing API terkelompok dengan prefix `v1` |
| **Exception Handler** | `backend/app/Exceptions/Handler.php` | Konversi otomatis Exception ke format JSON standar |
| **Auth Middleware** | `backend/app/Http/Middleware/Authenticate.php` | Middleware autentikasi token Sanctum |
| **Role Middleware** | `backend/app/Http/Middleware/RoleMiddleware.php` | Validasi peran pengguna (`admin`, `creator`, `backer`) |
| **Rate Limiter** | `backend/app/Providers/RouteServiceProvider.php` | Pembatasan frekuensi request (Throttling) |

### Diagram Alur Siklus Request & Response

```
Client Request (Vue 3 SPA)
        │
        ▼
[ Laravel Routing & Throttle Middleware ]
        │
        ├─► [ Header: Authorization Bearer Token Valid? ]
        │         │
        │         ├─► Tidak ──► HTTP 401 Unauthorized
        │         ▼
        ├─► [ Role Middleware Valid? ]
        │         │
        │         ├─► Tidak ──► HTTP 403 Forbidden
        │         ▼
        ├─► [ FormRequest Validation ]
        │         │
        │         ├─► Gagal ──► HTTP 422 Unprocessable Content
        │         ▼
        ├─► [ Service Layer & Business Logic ]
        │         │
        │         ├─► Database Transaction & Domain Events
        │         ▼
        └─► [ JSON Resource Transformation ]
                  │
                  ▼
          Standard JSON Envelope Response
```

---

## 3. Struktur File Terkait

```
backend/
├── app/
│   ├── Exceptions/
│   │   └── Handler.php
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php
│   │   │   └── RoleMiddleware.php
│   │   ├── Requests/
│   │   └── Resources/
│   └── Providers/
│       └── RouteServiceProvider.php
└── routes/
    └── api.php
```

---

## 4. Format Standar Response & Error Handling

### A. Format Response Sukses Tunggal (`200 OK` / `201 Created`)
```json
{
  "success": true,
  "message": "Operasi berhasil dijalankan",
  "data": {
    "id": 1,
    "name": "Contoh Data"
  }
}
```

### B. Format Response Koleksi Terpaginasi (`200 OK`)
```json
{
  "success": true,
  "data": [
    { "id": 1, "title": "Kampanye 1" },
    { "id": 2, "title": "Kampanye 2" }
  ],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 10,
      "total": 50
    }
  }
}
```

### C. Format Response Error Validasi (`422 Unprocessable Content`)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required."
    ],
    "target_amount": [
      "The target amount must be at least 100000."
    ]
  }
}
```

### D. Format Response Error Umum (`400`, `401`, `403`, `404`, `500`)
```json
{
  "success": false,
  "message": "Deskripsi kesalahan spesifik"
}
```

---

## 5. Skema Sumber Daya (Resource Schema)

### Global Response Interface
```typescript
interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  meta?: {
    pagination?: {
      current_page: number;
      last_page: number;
      per_page: number;
      total: number;
    };
  };
  errors?: Record<string, string[]>;
}
```

---

## 6. Pengujian Postman (Global Environment Setup)

### Konfigurasi Variabel Environment Postman:
- `base_url`: `http://localhost:8000/api/v1`
- `token_admin`: Token Sanctum admin
- `token_creator`: Token Sanctum creator
- `token_backer`: Token Sanctum backer

**Global Pre-request Script:**
```javascript
pm.request.headers.add({
    key: "Accept",
    value: "application/json"
});
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario Pengujian | Parameter / Header | Expected Output |
|---|---|---|---|
| 1 | Akses rute publik | Tanpa header auth | `200 OK` + Data publik |
| 2 | Akses rute terlindungi tanpa token | `Authorization: null` | `401 Unauthorized` |
| 3 | Akses rute role tidak sesuai | Token backer pada rute admin | `403 Forbidden` |
| 4 | Pengiriman payload tidak valid | Field wajib dikosongkan | `422 Unprocessable Content` |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Error | Penyebab | Solusi |
|---|---|---|
| `401 Unauthenticated` | Token tidak disertakan atau format `Bearer` salah | Pastikan header `Authorization: Bearer <token>` disertakan secara presisi. |
| `419 CSRF Token Mismatch` | Akses rute API via web route tanpa session cookie | Selalu gunakan URL dengan prefix `/api/v1/`. |
| `429 Too Many Requests` | Melebihi batasan rate limit (60 req/menit) | Tunggu 1 menit atau sesuaikan limit di `RouteServiceProvider`. |

---

## 9. Matriks RBAC (Role-Based Access Control)

| Modul Area | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| Eksplorasi Kampanye Publik | ✓ | ✓ | ✓ | ✓ |
| Dompet Virtual (Deposit/Withdraw) | ✗ | ✓ | ✓ | ✓ |
| Mendanai Kampanye (Backing) | ✗ | ✓ | ✓ (Bukan miliknya) | ✗ |
| Pembuatan & Manajemen Kampanye | ✗ | ✗ | ✓ | ✗ |
| Persetujuan & Moderasi Admin | ✗ | ✗ | ✗ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected HTTP | Expected Response |
|---|---|---|---|---|---|
| `TC-GEN-001` | Akses API dengan format JSON | Positive | Header `Accept: application/json` | `200 OK` | JSON Envelope valid |
| `TC-GEN-002` | Akses API tanpa Header Accept | Positive | Header default | `200 OK` | JSON response fallback |
| `TC-GEN-003` | Akses endpoint non-existent | Negative | URL `/api/v1/unknown-endpoint` | `404 Not Found` | Error not found |
| `TC-GEN-004` | Request berlebih secara cepat | Throttling | 70 request berturut-turut | `429 Too Many Requests` | Error rate limit |
