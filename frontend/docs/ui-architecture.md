# Arsitektur Frontend CoFund (Vue 3 SPA)

Dokumen ini menjelaskan arsitektur antarmuka pengguna (*frontend*) aplikasi CoFund yang dibangun sebagai *Single Page Application* (SPA) menggunakan **Vue 3 Composition API**, **Pinia State Management**, **Vue Router v4**, **Axios Service Layer**, dan **Tailwind CSS v3**.

---

## 1. Pola Aliran Data & Tanggung Jawab Layer

CoFund menerapkan prinsip pemisahan tanggung jawab yang ketat (*Strict Layering Principle*):

```
┌────────────────────────────────────────────────────────┐
│                   Vue 3 Components                     │
│           (Pages / Layouts / Domain UI)                │
└───────────────────────────┬────────────────────────────┘
                            │ Memanggil Action / Reactive State
┌───────────────────────────▼────────────────────────────┐
│                    Composables Layer                   │
│        (useAuth, useCampaign, useWallet, useAdmin)     │
└───────────────────────────┬────────────────────────────┘
                            │ Mengakses Global State / Logic
┌───────────────────────────▼────────────────────────────┐
│                       Pinia Stores                     │
│   (useAuthStore, useCampaignStore, useWalletStore)     │
└───────────────────────────┬────────────────────────────┘
                            │ Memanggil Service per Modul
┌───────────────────────────▼────────────────────────────┐
│                      Service Layer                     │
│       (authService, campaignService, walletService)    │
└───────────────────────────┬────────────────────────────┘
                            │ Axios Singleton Instance
┌───────────────────────────▼────────────────────────────┐
│                   services/api.js                      │
│        (Request Interceptors & Token Injection)        │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP JSON Request
                     Laravel REST API
```

> [!IMPORTANT]
> **Aturan Layering:**
> Komponen dan Composable **tidak boleh** melakukan pemanggilan `axios` secara langsung. Seluruh interaksi jaringan harus melalui Service Layer (`services/*Service.js`) yang mengonsumsi instance terpusat di `services/api.js`.

---

## 2. Struktur Folder `frontend/src/`

```
frontend/src/
├── assets/                   # main.css, icon styling, font imports
├── components/
│   ├── admin/                # Admin domain modals (RejectDialog, UserDetailModal)
│   ├── campaign/             # Campaign UI (CampaignCard, RewardTierCard, BackingDialog, Updates)
│   ├── common/               # Reusable atomic UI (Navbar, Footer, ProgressBar, StatusBadge, SkeletonLoader, EmptyState, Pagination, ImageCropperModal, GlobalToastContainer, GlobalConfirmModal)
│   └── wallet/               # Wallet UI (DepositModal, WithdrawModal, TransactionTable)
├── composables/              # Reusable composition logic (useAuth, useCampaign, useWallet, useAdmin, useToast, useConfirm)
├── layouts/                  # MainLayout, AuthLayout, AdminLayout
├── pages/
│   ├── admin/                # AdminDashboardPage, AdminCampaignsPage, AdminUsersPage
│   ├── auth/                 # LoginPage, RegisterPage, ForgotPasswordPage, ResetPasswordPage, VerifyEmailPage
│   ├── backer/               # BackerDashboardPage, WalletPage, TransactionsPage, NotificationsPage
│   ├── creator/              # CreatorDashboardPage, CreateCampaignPage, EditCampaignPage
│   └── public/               # HomePage, CampaignListPage, CampaignDetailPage, NotFoundPage
├── router/                   # index.js (Route definitions, meta permissions, navigation guards)
├── services/                 # api.js, authService, campaignService, walletService, adminService, tierService, dll.
├── stores/                   # useAuthStore, useCampaignStore, useWalletStore, useAdminStore, useNotificationStore, useToastStore, useConfirmStore
└── utils/                    # formatCurrency, formatDate, imageHelper, badgeHelper
```

---

## 3. Vue Router & Navigation Guards

Router (`router/index.js`) mengimplementasikan *Navigation Guards* berbasis metadata rute (`meta`):

```javascript
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // 1. Initial Profile Fetching jika token ada
  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  const { requiresAuth, role, guestOnly, requiresVerified } = to.meta

  // 2. Cegah user login mengakses halaman guest (Login/Register)
  if (guestOnly && authStore.isAuthenticated) {
    return next(authStore.isAdmin ? '/admin/dashboard' : authStore.isCreator ? '/creator/dashboard' : '/dashboard')
  }

  // 3. Wajib Login
  if (requiresAuth && !authStore.isAuthenticated) {
    return next({ path: '/login', query: { redirect: to.fullPath } })
  }

  // 4. Cek Hak Akses Role (Admin / Creator)
  if (role && authStore.role !== role) {
    return next('/404')
  }

  // 5. Cek Verifikasi Email untuk aksi khusus
  if (requiresVerified && !authStore.isEmailVerified) {
    return next('/verify-email')
  }

  next()
})
```

---

## 4. Pengelolaan Global State (Pinia Stores)

Setiap entitas domain memiliki store mandiri dengan State, Getters, dan Actions:

- **`useAuthStore`**: Token sesi, data user saat ini, role (`isAdmin`, `isCreator`, `isBacker`), status verifikasi email, dan kalkulasi saldo (`balance`).
- **`useCampaignStore`**: List kampanye publik, filter/search state, paginasi, kampanye aktif creator (`scope=mine`), dan detail kampanye aktif.
- **`useWalletStore`**: Mutasi transaksi, status loading deposit/withdraw, dan trigger refresh saldo.
- **`useAdminStore`**: Data agregat statistik platform, list user admin, persetujuan dan penolakan kampanye.
- **`useNotificationStore`**: Notifikasi internal sistem dan counter pesan belum dibaca.
- **`useToastStore` & `useConfirmStore`**: Sistem dialog konfirmasi dan toast kustom berbasis Promise.

---

## 5. Form Validation (Vee-Validate + Yup)

Seluruh form input (Login, Register, Create Campaign, Deposit, Withdraw) divalidasi secara deklaratif menggunakan **Vee-Validate v4** dan schema **Yup**:

```javascript
import { useForm, useField } from 'vee-validate'
import * as yup from 'yup'

const schema = yup.object({
  email: yup.string().required('Email wajib diisi').email('Format email tidak valid'),
  password: yup.string().required('Password wajib diisi').min(8, 'Password minimal 8 karakter'),
})

const { handleSubmit, errors } = useForm({ validationSchema: schema })
const { value: email } = useField('email')
const { value: password } = useField('password')
```
