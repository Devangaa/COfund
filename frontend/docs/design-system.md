# CoFund Design System & Panduan UI/UX (Anti-AI Slop)

Dokumen ini mendefinisikan standar visual, token desain, tipografi, komponen UI, dan filosofi estetika **Modern Navy FinTech** yang diterapkan pada CoFund untuk menghapus kesan *generic AI slop* dan menghasilkan pengalaman pengguna yang berkelas, tepercaya, dan profesional.

---

## 1. Filosofi Desain: Anti-AI Slop & Modern FinTech

CoFund menghindari pola-pola usang (*AI Slop Patterns*):
- ❌ **Hindari Background Putih Polos Membosankan (`#FFFFFF` tanpa jeda)**: Menyebabkan mata cepat lelah dan tampilan terasa datar tanpa kedalaman.
- ❌ **Hindari Drop Shadow Hitam Pekat/Kotor**: Menimbulkan kesan kotor dan kaku khas generate AI instan.
- ❌ **Hindari Ukuran Font yang Seragam**: Menyulitkan pengguna membedakan prioritas informasi.
- ❌ **Hindari Layout Padat Tanpa Ruang Nafas**: Elemen berdempetan terlihat amatir.

Prinsip Baru yang Diterapkan:
- ✅ **Off-White Soft Canvas (`bg-slate-50`)**: Memberikan kontras lembut terhadap kartu putih dan panel navy.
- ✅ **Deep Navy Trust (`#0B132B` / `slate-900`)**: Membangun persepsi keamanan dan kredibilitas perbankan/fintech.
- ✅ **High-Contrast Warm CTA (`#F59E0B` - `#D97706` Amber)**: Menjadikan tombol donasi dan konfirmasi sebagai fokus utama pandangan mata.
- ✅ **Subtle Micro-Interactions**: Animasi transisi 300ms, hover elevation halus, dan klik taktil (`active:scale-[0.98]`).
- ✅ **Dynamic Feedback**: Shimmer skeleton loading dan progress bar glowing dengan pulse animation.

---

## 2. Token Desain (Design Tokens)

### A. Palet Warna (Color Palette)

| Kategori | Token Tailwind | Kode Hex | Penggunaan Utama |
|---|---|---|---|
| **Canvas Background** | `bg-slate-50` | `#F8FAFC` | Latar belakang seluruh halaman konten |
| **Deep Navy (Primary)** | `navy-900` / `slate-900` | `#0B132B` / `#0F172A` | Navbar, Hero banner, Footer, Text H1/H2 |
| **Navy Card (Surface)** | `navy-800` | `#111C33` | Kartu Saldo FinTech, Hero Widget |
| **Electric Blue (Accent)**| `brand-600` / `blue-600` | `#2563EB` | Hyperlinks, Icon badge, Progress Bar fill |
| **Warm Amber (CTA)** | `cta-500` / `amber-500` | `#F59E0B` | Tombol *"Dukung Kampanye Sekarang"*, Badge penting |
| **Emerald (Success)** | `emerald-600` | `#059669` | Transaksi berhasil, Status aktif, Verifikasi sukses |
| **Rose (Danger)** | `rose-600` | `#E11D48` | Status ditolak/gagal, Akun suspended, Error alert |

---

### B. Tipografi & Skala Teks (Typography Hierarchy)

Font utama: **Plus Jakarta Sans** (fallback: *Inter, system-ui*).

| Tingkat | Kelas Tailwind | Ukuran / Tracking | Contoh Penggunaan |
|---|---|---|---|
| **Display / H1** | `text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight` | 36px - 60px | Headline Hero Section |
| **Page Title / H2** | `text-2xl sm:text-3xl font-black tracking-tight` | 24px - 30px | Judul Kampanye, Heading Halaman |
| **Section Title / H3**| `text-lg sm:text-xl font-bold` | 18px - 20px | Sub-judul bagian, Nama paket tier |
| **Body Standard** | `text-sm sm:text-base text-slate-600 leading-relaxed` | 14px - 16px | Deskripsi proyek, Paragraf artikel |
| **Small Caption** | `text-xs text-slate-500 font-medium` | 12px | Label metrik, info pembuat kampanye |
| **Micro Eyebrow** | `text-[11px] font-bold uppercase tracking-wider text-blue-600` | 11px | Kategori Pill, Status tag |

---

### C. Spacing & Negative Space
- **Jarak Antar Section**: `space-y-16` hingga `space-y-20` (64px - 80px).
- **Padding Kontainer Kartu**: `p-6` hingga `p-8` (24px - 32px).
- **Grid Gap**: `gap-6` hingga `gap-8` (24px - 32px).
- **Radius Sudut Kontainer**:
  - `rounded-3xl` (24px): Kartu kampanye detail, Banner Hero, Dialog Modal.
  - `rounded-2xl` (16px): Kartu katalog, Input form, Dropdown panel.
  - `rounded-xl` (12px): Tombol aksi, Badge kategori, Thumbnail foto.

---

### D. Elevasi & Bayangan Halus (Soft Shadows)
- `shadow-soft`: `0 2px 10px -2px rgba(15, 23, 42, 0.05), 0 1px 3px 0 rgba(15, 23, 42, 0.03)`
- `shadow-elevated`: `0 12px 24px -6px rgba(15, 23, 42, 0.07), 0 4px 8px -4px rgba(15, 23, 42, 0.03)`
- `shadow-glow-blue`: `0 0 25px -5px rgba(37, 99, 235, 0.25)`
- `shadow-glow-amber`: `0 0 25px -5px rgba(245, 158, 11, 0.35)`

---

## 3. Komponen Utama & PrimeVue Aura Integration

Aplikasi mengintegrasikan **PrimeVue v4** dengan preset **Aura** dan **PrimeIcons** (`pi pi-*`):

### A. Dynamic Progress Bar (`ProgressBar.vue`)
- Menggunakan gradasi `from-blue-600 via-blue-500 to-sky-400`.
- Efek animasi pulse shine di atas fill progress saat nilai persentase di atas 0%.

### B. Shimmer Skeleton Loading (`SkeletonLoader.vue`)
- Menggantikan spinner putar jadul dengan wireframe layout yang beranimasi gelombang cahaya (*gradient shimmer*).
- Tersedia preset: `type="card"`, `type="detail"`, `type="table"`.

### C. Status Badge Indicator (`StatusBadge.vue`)
- Menampilkan status kampanye (`active`, `draft`, `review`, `success`, `failed`), role (`admin`, `creator`, `backer`), dan status transaksi dengan kombinasi dot indicator dan warna pastel berkontras tinggi.
