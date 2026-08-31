# Modul Frontend: Autentikasi & Akun Pengguna (Auth Frontend Module)

## 1. Judul & Deskripsi Modul

Modul Frontend Autentikasi mengelola seluruh antarmuka interaksi login, registrasi, lupa password, reset password, verifikasi email, dan upgrade role donatur menjadi inisiator (*Upgrade to Creator*) dengan validasi formulir deklaratif (**Vee-Validate + Yup**) dan penyimpanan status sesi reaktif (**Pinia `useAuthStore`**).

**Base Route:** `/` (Public & Auth Layout)

---

## 2. Arsitektur Modul

### Komponen Terkait

| Komponen | Lokasi File | Deskripsi |
|---|---|---|
| **View Pages** | `frontend/src/pages/auth/LoginPage.vue` | Tampilan form login email & password |
| | `frontend/src/pages/auth/RegisterPage.vue` | Tampilan form registrasi akun baru |
| | `frontend/src/pages/auth/ForgotPasswordPage.vue` | Form permintaan link reset password |
| | `frontend/src/pages/auth/ResetPasswordPage.vue` | Form input kata sandi baru |
| | `frontend/src/pages/auth/VerifyEmailPage.vue` | Tampilan notifikasi status verifikasi email |
| **Pinia Store** | `frontend/src/stores/useAuthStore.js` | State token, objek user, role, dan saldo |
| **Composable** | `frontend/src/composables/useAuth.js` | Abstraksi logika form submit & state login |
| **Service Layer** | `frontend/src/services/authService.js` | Wrapper request axios ke endpoint `/api/v1/*` |
| **Layout** | `frontend/src/layouts/AuthLayout.vue` | Layout khusus berpusat dengan panel branding |

### Diagram Alur Siklus State Autentikasi

```
User Submit Form Login
        │
        ▼
[ Vee-Validate Schema Yup ] ──(Gagal)──► Tampilkan Pesan Error di Input
        │ (Valid)
        ▼
[ useAuthStore.login(credentials) ]
        │
        ▼
[ authService.login() ] ──► HTTP POST /api/v1/login
        │
        ├─► Sukses (HTTP 200)
        │     ├─► Simpan Token di localStorage ('cofund_token')
        │     ├─► Simpan Data User di Pinia state (`user.value`)
        │     ├─► Tampilkan Toast Sukses
        │     └─► Redirect ke Dashboard sesuai Role
        │
        └─► Gagal (HTTP 422 / 403)
              └─► Tampilkan Toast Notifikasi Error
```

---

## 3. Struktur File Terkait

```
frontend/src/
├── composables/
│   └── useAuth.js
├── layouts/
│   └── AuthLayout.vue
├── pages/
│   └── auth/
│       ├── ForgotPasswordPage.vue
│       ├── LoginPage.vue
│       ├── RegisterPage.vue
│       ├── ResetPasswordPage.vue
│       └── VerifyEmailPage.vue
├── services/
│   └── authService.js
└── stores/
    └── useAuthStore.js
```

---

## 4. Rincian Rute & Halaman (Route Pages)

### 4.1 Halaman Login (`/login`)
- **Meta:** `{ guestOnly: true, layout: 'auth' }`
- **Schema Validasi Yup:**
  ```javascript
  const schema = yup.object({
    email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
    password: yup.string().required('Password wajib diisi').min(8, 'Minimal 8 karakter'),
  })
  ```
- **Aksi Pasca Login:** Redirect otomatis ke `/admin/dashboard` jika admin, `/creator/dashboard` jika creator, atau `/dashboard` jika backer.

---

### 4.2 Halaman Registrasi (`/register`)
- **Meta:** `{ guestOnly: true, layout: 'auth' }`
- **Schema Validasi Yup:**
  ```javascript
  const schema = yup.object({
    name: yup.string().required('Nama lengkap wajib diisi').min(3, 'Minimal 3 karakter'),
    email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
    password: yup.string().required('Password wajib diisi').min(8, 'Minimal 8 karakter'),
    password_confirmation: yup.string().oneOf([yup.ref('password')], 'Konfirmasi password tidak cocok'),
  })
  ```

---

## 5. Skema Sumber Daya (State Interface)

```typescript
interface AuthState {
  token: string | null;
  user: {
    id: number;
    name: string;
    email: string;
    role: 'backer' | 'creator' | 'admin';
    balance: string;
    email_verified_at: string | null;
    is_suspended: boolean;
  } | null;
  isLoading: boolean;
  isAuthenticated: boolean;
}
```

---

## 6. Pengujian Unit & Komponen (Vitest Script)

```javascript
import { setActivePinia, createPinia } from 'pinia'
import { describe, it, expect, beforeEach } from 'vitest'
import { useAuthStore } from '@/stores/useAuthStore'

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
  })

  it('initial state should be unauthenticated', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.role).toBe('guest')
    expect(store.balance).toBe(0)
  })
})
```

---

## 7. Kasus Pengujian Ringkas

| No | Skenario UI | Input | Hasil UI yang Diharapkan |
|---|---|---|---|
| 1 | Submit login email kosong | Klik Masuk | Tampil teks merah "Email wajib diisi" di bawah input |
| 2 | Submit password < 8 karakter | Password "123" | Tampil teks merah "Minimal 8 karakter" |
| 3 | Login berhasil | Kredensial valid | Muncul toast hijau & redirect ke Dashboard |
| 4 | Logout akun | Klik tombol Keluar | Token terhapus & dialihkan ke halaman Login |

---

## 8. Pemecahan Masalah (Troubleshooting)

| Gejala / Masalah | Penyebab | Solusi |
|---|---|---|
| User sudah login tapi terlempar ke login | Token di `localStorage` kadaluarsa atau tidak valid | Sistem secara otomatis membersihkan sesi dan meminta login ulang via `router.beforeEach`. |
| Saldo di navbar tidak terupdate | `authStore.fetchMe()` belum terpanggil | Panggil `authStore.fetchMe()` setelah transaksi sukses. |

---

## 9. Matriks RBAC Navigasi Frontend

| Halaman | Guest | Backer | Creator | Admin |
|---|---|---|---|---|
| `/login` & `/register` | ✓ | Redirect ke Dashboard | Redirect ke Dashboard | Redirect ke Dashboard |
| `/dashboard` | ✗ | ✓ | ✓ | ✓ |
| `/creator/dashboard` | ✗ | ✗ (Redirect 404) | ✓ | ✗ |
| `/admin/dashboard` | ✗ | ✗ (Redirect 404) | ✗ | ✓ |

---

## 10. Matriks Kasus Pengujian Detail (Test Cases)

| Test ID | Skenario | Kategori | Input Form | Expected Result |
|---|---|---|---|---|
| `FE-AUTH-001` | Form login submit valid | Positive | Email & Password valid | Simpan token ke storage & redirect |
| `FE-AUTH-002` | Form login kredensial salah | Negative | Password keliru | Tampilkan toast error merah |
| `FE-AUTH-003` | Route guard halaman terlindungi | Security | Akses `/wallet` tanpa login | Redirect ke `/login?redirect=/wallet` |
