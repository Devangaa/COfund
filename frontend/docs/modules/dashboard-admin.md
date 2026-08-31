# Modul Frontend: Dasbor Administrator (Admin Dashboard Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Dasbor Administrator menyediakan ruang kendali pengawasan platform bagi admin, meliputi pemantauan analitik platform global, peninjauan proposal kampanye (persetujuan, penolakan, dan pembatalan paksa), serta manajemen status akun pengguna (*suspend/unsuspend*).

**Base Route:** `/admin`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Pages** | `frontend/src/pages/admin/AdminDashboardPage.vue` | Tampilan dasbor analitik metrik platform |
| | `frontend/src/pages/admin/AdminCampaignsPage.vue` | Tabel manajemen & review kampanye |
| | `frontend/src/pages/admin/AdminUsersPage.vue` | Tabel manajemen & suspend user |
| **Domain Modals** | `frontend/src/components/admin/RejectDialog.vue` | Dialog modal input alasan penolakan proyek |
| | `frontend/src/components/admin/UserDetailModal.vue` | Dialog modal rincian profil & statistik user |
| **Pinia Store** | `frontend/src/stores/useAdminStore.js` | State data admin & aksi moderasi |
| **Service Layer** | `frontend/src/services/adminService.js` | Wrapper API endpoint `/api/v1/admin/*` |
| **Layout** | `frontend/src/layouts/AdminLayout.vue` | Layout khusus sidebar navigasi admin |

### Diagram Alur Peninjauan Kampanye oleh Admin

```
Admin Membuka /admin/campaigns
        │
        ▼
[ Pilih Kampanye dengan Status REVIEW ]
        │
        ├─► [ Klik Setujui (Approve) ]
        │     └─► PUT /api/v1/admin/campaigns/{slug}/approve ──► Status ACTIVE & Toast Hijau
        │
        ├─► [ Klik Tolak (Reject) ]
        │     └─► Buka RejectDialog.vue
        │           └─► Input Alasan Penolakan
        │                 └─► PUT /api/v1/admin/campaigns/{slug}/reject ──► Status DRAFT
        │
        └─► [ Klik Batalkan Paksa (Force Fail) ]
              └─► PUT /api/v1/admin/campaigns/{slug}/force-fail ──► Pemicu Refund Otomatis
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── components/
│   └── admin/
│       ├── RejectDialog.vue
│       └── UserDetailModal.vue
├── layouts/
│   └── AdminLayout.vue
├── pages/
│   └── admin/
│       ├── AdminCampaignsPage.vue
│       ├── AdminDashboardPage.vue
│       └── AdminUsersPage.vue
├── services/
│   └── adminService.js
└── stores/
    └── useAdminStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Dasbor Admin (`/admin/dashboard`)
- **Meta:** `{ requiresAuth: true, role: 'admin', layout: 'admin' }`
- **Fitur:** Metrik total pengguna, kampanye aktif, total dana terkumpul, dan akumulasi platform fee 5%.

---

### 4.2 Halaman Kelola Kampanye (`/admin/campaigns`)
- **Fitur:** Filter tab status (`Semua`, `Review`, `Active`, `Success`, `Failed`), tombol aksi Cepat (*Setujui*, *Tolak*, *Force Fail*).

---

### 4.3 Halaman Kelola Pengguna (`/admin/users`)
- **Fitur:** Pencarian nama/email user, filter role, tombol aksi *Suspend* dan *Unsuspend*, serta modal detail pengguna.

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface AdminState {
  platformStats: {
    total_users: number;
    total_backers: number;
    total_creators: number;
    total_campaigns: number;
    active_campaigns: number;
    successful_campaigns: number;
    failed_campaigns: number;
    total_funds_collected: number;
    total_platform_fees: number;
    platform_fee_rate: number;
  };
  users: Array<User>;
  isLoading: boolean;
}
```

---

## 6. Pengujian Unit & Komponen (Vitest Script)

```javascript
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue'

describe('AdminDashboardPage.vue', () => {
  it('mounts admin dashboard component without crashing', () => {
    const wrapper = mount(AdminDashboardPage)
    expect(wrapper.exists()).toBe(true)
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Admin klik setujui kampanye | Klik tombol 'Setujui' | Muncul dialog konfirmasi & status berubah jadi Active |
| 2 | Admin tolak kampanye | Isi alasan di RejectDialog | Kampanye kembali ke draf & kreator menerima alasan |
| 3 | Non-admin akses `/admin/dashboard` | Login sebagai backer | Dialihkan ke halaman 404 / Unauthorized |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Menu admin tidak muncul di navbar | User yang login bukan role `admin` | Pastikan login menggunakan akun dengan `role = admin`. |

---

## 9. Matriks RBAC

| Rute Halaman | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `/admin/*` | Redirect Login | Redirect 404 | Redirect 404 | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected Result |
|---|---|---|---|---|
| `FE-ADM-001` | Akses workspace admin | Positive | Login admin | Menampilkan sidebar dan dasbor admin |
| `FE-ADM-002` | Akses tanpa izin | Security | Login non-admin | Navigation guard memblokir rute (404) |
