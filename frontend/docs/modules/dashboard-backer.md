# Modul Frontend: Dasbor Donatur (Backer Dashboard Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Dasbor Donatur menyediakan ringkasan aktivitas kontribusi bagi pendukung proyek (*backer*), mencakup akumulasi dana yang telah didonasikan, riwayat dukungan kampanye (*backing history*), dan tab kabar pembaruan proyek yang didukung.

**Base Route:** `/dashboard`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Page** | `frontend/src/pages/backer/BackerDashboardPage.vue` | Tampilan ringkasan metrik donasi & daftar backing |
| **Domain Components** | `frontend/src/components/common/StatusBadge.vue` | Indikator status transaksi backing |
| | `frontend/src/components/common/Pagination.vue` | Navigasi halaman riwayat donasi |
| **Service Layer** | `frontend/src/services/backerService.js` | Endpoint analitik `/api/v1/backer/statistics` |
| | `frontend/src/services/backingService.js` | Endpoint daftar backing `/api/v1/backings` |
| **Layout** | `frontend/src/layouts/MainLayout.vue` | Layout utama aplikasi |

### Diagram Alur Antarmuka Donatur

```
Backer Membuka /dashboard
        │
        ├─► [ Ambil Statistik Donatur: GET /api/v1/backer/statistics ]
        │     └─► Tampilkan Metrik: Total Donasi, Jumlah Backing, Kampanye Didukung
        │
        └─► [ Ambil Riwayat Backing: GET /api/v1/backings ]
              └─► Tampilkan Tabel / Kartu Riwayat Donasi & Tautan ke Kampanye
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── pages/
│   └── backer/
│       ├── BackerDashboardPage.vue
│       ├── NotificationsPage.vue
│       └── TransactionsPage.vue
├── services/
│   ├── backerService.js
│   └── backingService.js
└── stores/
    └── useAuthStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Dasbor Donatur (`/dashboard`)
- **Meta:** `{ requiresAuth: true }`
- **Fitur Utama:**
  - 3 Kartu Metrik Ringkasan (*Total Dana Didonasikan*, *Total Dukungan Transaksi*, *Jumlah Proyek Didukung*).
  - Tabel Daftar Donasi dengan link langsung ke halaman kampanye.
  - Tautan cepat ke menu Dompet Saldo Virtual (`/wallet`).

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface BackerDashboardState {
  statistics: {
    total_backed: number;
    total_refunded: number;
    total_backings: number;
    total_campaigns_backed: number;
  };
  backings: Array<Backing>;
  isLoading: boolean;
}
```

---

## 6. Pengujian Unit & Komponen (Vitest Script)

```javascript
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import BackerDashboardPage from '@/pages/backer/BackerDashboardPage.vue'

describe('BackerDashboardPage.vue', () => {
  it('mounts backer dashboard correctly', () => {
    const wrapper = mount(BackerDashboardPage)
    expect(wrapper.exists()).toBe(true)
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Akses dasbor donatur | Buka `/dashboard` | Menampilkan ringkasan metrik donasi pengguna |
| 2 | Klik nama kampanye pada riwayat | Klik tautan | Berpindah ke halaman detail kampanye |
| 3 | Akses tanpa login | Buka `/dashboard` | Redirect otomatis ke halaman login |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Riwayat donasi kosong | Pengguna belum pernah mendanai proyek | Kunjungi menu Jelajahi Kampanye (`/campaigns`) untuk mulai mendanai. |

---

## 9. Matriks RBAC

| Rute Halaman | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `/dashboard` | Redirect Login | ✓ | ✓ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected Result |
|---|---|---|---|---|
| `FE-BAC-001` | Render dasbor donatur | Positive | User login | Menampilkan ringkasan metrik |
| `FE-BAC-002` | Akses tanpa token | Security | Guest | Redirect ke login |
