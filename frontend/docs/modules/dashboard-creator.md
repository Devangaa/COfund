# Modul Frontend: Dasbor Inisiator / Kreator (Creator Dashboard Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Dasbor Inisiator menyediakan ruang kerja terpadu bagi pembuat kampanye (*creator workspace*) untuk memantau performa penggalangan dana, mengelola draf proyek, mengajukan peninjauan (*submit review*), mempublikasikan kabar berkala (*milestone updates*), dan melihat kalkulasi estimasi pencairan dana bersih.

**Base Route:** `/creator`

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Page** | `frontend/src/pages/creator/CreatorDashboardPage.vue` | Tampilan dasbor analitik & tabel kampanye kreator |
| | `frontend/src/pages/creator/CreateCampaignPage.vue` | Form pembuatan proyek baru |
| **Domain Components** | `frontend/src/components/campaign/CampaignUpdateList.vue` | Pengelolaan artikel kabar proyek |
| | `frontend/src/components/common/StatusBadge.vue` | Indikator visual status kampanye |
| **Pinia Store** | `frontend/src/stores/useCampaignStore.js` | Fetching kampanye milik sendiri (`scope=mine`) |
| **Service Layer** | `frontend/src/services/creatorService.js` | Endpoint analitik `/api/v1/creator/statistics` |
| **Layout** | `frontend/src/layouts/MainLayout.vue` | Layout utama dengan header navigation |

### Diagram Alur Siklus Kerja Kreator

```
Inisiator Masuk ke /creator/dashboard
        │
        ├─► [ Ambil Statistik Performa: GET /api/v1/creator/statistics ]
        │     └─► Render Kartu Metrik: Total Dana, Backer, Estimasi Bersih
        │
        └─► [ Ambil Daftar Kampanye: GET /api/v1/campaigns?scope=mine ]
              │
              ├─► Status DRAFT   ──► Tombol "Edit" & "Ajukan Review Admin"
              ├─► Status REVIEW  ──► Status "Sedang Ditinjau Admin"
              ├─► Status ACTIVE  ──► Tombol "Tulis Kabar Proyek" & Pantau Capaian
              └─► Status SUCCESS ──► Informasi "Dana Siap Dicairkan (Disbursement)"
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── components/
│   └── campaign/
│       └── CampaignUpdateList.vue
├── pages/
│   └── creator/
│       ├── CreateCampaignPage.vue
│       ├── CreatorDashboardPage.vue
│       └── EditCampaignPage.vue
├── services/
│   ├── campaignService.js
│   └── creatorService.js
└── stores/
    └── useCampaignStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Dasbor Inisiator (`/creator/dashboard`)
- **Meta:** `{ requiresAuth: true, role: 'creator', requiresVerified: true }`
- **Fitur Utama:**
  - 4 Kartu Metrik Ringkasan (*Total Dana Terkumpul*, *Proyek Aktif*, *Total Backer*, *Estimasi Bersih 95%*).
  - Tabel Daftar Kampanye Saya dengan filter status (`Semua`, `Draft`, `Review`, `Active`, `Success`).
  - Tombol aksi cepat: Buat Kampanye Baru, Edit Draf, Ajukan Review, Tulis Kabar Proyek.

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface CreatorDashboardState {
  statistics: {
    total_campaigns: number;
    active_campaigns: number;
    successful_campaigns: number;
    failed_campaigns: number;
    total_funds_raised: number;
    total_backers: number;
    platform_fee_rate: number;
    estimated_net_funds: number;
  };
  myCampaigns: Array<Campaign>;
  isLoading: boolean;
}
```

---

## 6. Pengujian Unit & Komponen (Vitest Script)

```javascript
import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import CreatorDashboardPage from '@/pages/creator/CreatorDashboardPage.vue'

describe('CreatorDashboardPage.vue', () => {
  it('renders creator statistics widgets properly', () => {
    const wrapper = mount(CreatorDashboardPage)
    expect(wrapper.exists()).toBe(true)
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Klik 'Ajukan Review' pada draf | Klik tombol | Status kampanye berubah menjadi 'Review' & toast sukses |
| 2 | Klik 'Buat Kampanye' | Klik tombol | Redirect ke halaman form pembuatan `/campaigns/create` |
| 3 | Filter tab 'Draft' | Klik tab Draft | Tabel hanya menampilkan kampanye berstatus draf |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| Halaman redirect ke `/404` saat diakses | Role akun masih `backer` | Lakukan upgrade akun ke kreator terlebih dahulu melalui menu navigasi. |
| Tidak bisa submit review | Kampanye belum memiliki foto atau reward tier | Lengkapi minimal 1 foto dan 1 reward tier sebelum mengajukan review. |

---

## 9. Matriks RBAC

| Rute Halaman | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `/creator/dashboard` | Redirect Login | Redirect 404 | ✓ | Redirect 404 |
| `/campaigns/create` | Redirect Login | Redirect 404 | ✓ | Redirect 404 |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input | Expected Result |
|---|---|---|---|---|
| `FE-CRE-001` | Render widget analitik | Positive | Akses `/creator/dashboard` | Menampilkan 4 kartu ringkasan metrik |
| `FE-CRE-002` | Akses tanpa role creator | Security | Role akun backer | Navigation Guard me-redirect ke 404 |
