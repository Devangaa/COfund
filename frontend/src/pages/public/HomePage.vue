<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useCampaignStore } from '@/stores/useCampaignStore'
import { useAuthStore } from '@/stores/useAuthStore'
import CampaignCard from '@/components/campaign/CampaignCard.vue'
import SkeletonLoader from '@/components/common/SkeletonLoader.vue'

const campaignStore = useCampaignStore()
const authStore = useAuthStore()

const categories = [
  { name: 'Teknologi & Inovasi', slug: 'teknologi', icon: 'pi pi-bolt', count: '14+ Proyek' },
  { name: 'Sosial & Kemanusiaan', slug: 'sosial-kemanusiaan', icon: 'pi pi-heart', count: '28+ Proyek' },
  { name: 'Lingkungan & Alam', slug: 'lingkungan', icon: 'pi pi-globe', count: '10+ Proyek' },
  { name: 'Seni & Kerajinan', slug: 'seni-kerajinan', icon: 'pi pi-palette', count: '19+ Proyek' },
  { name: 'Pendidikan', slug: 'pendidikan', icon: 'pi pi-book', count: '12+ Proyek' },
  { name: 'Kesehatan & Medis', slug: 'kesehatan', icon: 'pi pi-shield', count: '8+ Proyek' },
]

onMounted(async () => {
  await campaignStore.fetchCampaigns({ per_page: 6, sort: 'popular' })
})
</script>

<template>
  <div class="space-y-20 pb-20">
    <!-- Hero Section -->
    <section class="relative bg-slate-900 text-white pt-16 pb-28 px-4 sm:px-6 lg:px-8 overflow-hidden">
      <!-- Background glows -->
      <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute top-1/2 right-0 w-[500px] h-[500px] bg-sky-500/15 rounded-full blur-3xl pointer-events-none"></div>

      <div class="max-w-7xl mx-auto relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
          <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/80 text-xs font-semibold text-sky-300 backdrop-blur-md">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              Platform Crowdfunding FinTech #1 dengan Virtual Escrow
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight sm:leading-tight">
              Wujudkan Ide Besar dengan
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-sky-300 to-cyan-200">
                Pendanaan Kolektif
              </span>
              yang Aman & Transparan.
            </h1>

            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto lg:mx-0 leading-relaxed font-normal">
              Dukung proyek teknologi, karya kreatif, dan gerakan sosial terpercaya. Seluruh dana terlindungi dalam sistem escrow otomatis dengan jaminan pengembalian 100% jika target kampanye tidak tercapai.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4 pt-4">
              <RouterLink
                to="/campaigns"
                class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm text-center transition-all duration-200 shadow-xl shadow-blue-600/25 flex items-center justify-center gap-2 group"
              >
                <span>Jelajahi Kampanye</span>
                <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
              </RouterLink>

              <RouterLink
                :to="authStore.isCreator ? '/creator/campaigns/create' : '/register'"
                class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-slate-800/90 hover:bg-slate-700/90 text-slate-200 font-bold text-sm text-center border border-slate-700 transition flex items-center justify-center gap-2"
              >
                <i class="pi pi-plus text-xs text-sky-400"></i>
                <span>Mulai Galang Dana</span>
              </RouterLink>
            </div>
          </div>

          <!-- Hero Graphic Card / Visual Feature -->
          <div class="lg:col-span-5">
            <div class="relative mx-auto max-w-md bg-gradient-to-b from-slate-800/80 to-slate-900/90 p-6 rounded-3xl border border-slate-700/80 shadow-2xl backdrop-blur-xl space-y-6">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-sky-400 flex items-center gap-1.5">
                  <i class="pi pi-shield"></i>
                  Sistem Escrow Terverifikasi
                </span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-950 text-emerald-400 border border-emerald-800 font-semibold">
                  Live Status
                </span>
              </div>

              <!-- Mini metric card demo -->
              <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 space-y-3">
                <div class="flex justify-between items-baseline">
                  <span class="text-xs text-slate-400 font-medium">Dana Terkumpul Platform</span>
                  <span class="text-xs font-bold text-emerald-400">+18% bln ini</span>
                </div>
                <div class="text-2xl sm:text-3xl font-extrabold text-white">
                  Rp 12.850.000.000
                </div>
                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                  <div class="bg-gradient-to-r from-blue-500 to-sky-400 h-full w-4/5 rounded-full"></div>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3 text-left">
                <div class="p-3.5 rounded-xl bg-slate-800/50 border border-slate-700/50">
                  <div class="text-xs text-slate-400">Tingkat Berhasil</div>
                  <div class="text-lg font-bold text-white mt-1">94.2%</div>
                </div>
                <div class="p-3.5 rounded-xl bg-slate-800/50 border border-slate-700/50">
                  <div class="text-xs text-slate-400">Total Backer</div>
                  <div class="text-lg font-bold text-white mt-1">45.000+</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Platform Stats Ticker Bar (Floating) -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 relative z-20">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 sm:p-8 bg-white rounded-3xl border border-slate-100 shadow-xl">
        <div class="text-center md:border-r border-slate-100 last:border-none p-2">
          <div class="text-2xl sm:text-3xl font-black text-slate-900">500+</div>
          <div class="text-xs font-medium text-slate-500 mt-1">Kampanye Sukses</div>
        </div>
        <div class="text-center md:border-r border-slate-100 last:border-none p-2">
          <div class="text-2xl sm:text-3xl font-black text-blue-600">Rp 25M+</div>
          <div class="text-xs font-medium text-slate-500 mt-1">Total Dana Tersalurkan</div>
        </div>
        <div class="text-center md:border-r border-slate-100 last:border-none p-2">
          <div class="text-2xl sm:text-3xl font-black text-slate-900">100%</div>
          <div class="text-xs font-medium text-slate-500 mt-1">Jaminan Virtual Escrow</div>
        </div>
        <div class="text-center p-2">
          <div class="text-2xl sm:text-3xl font-black text-emerald-600">5%</div>
          <div class="text-xs font-medium text-slate-500 mt-1">Transparansi Fee Rendah</div>
        </div>
      </div>
    </div>

    <!-- Featured Campaigns Section -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
        <div>
          <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Pilihan Unggulan</span>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
            Kampanye Paling Populer Saat Ini
          </h2>
        </div>
        <RouterLink
          to="/campaigns"
          class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 transition group"
        >
          <span>Lihat Semua Kampanye</span>
          <i class="pi pi-arrow-right text-[10px] group-hover:translate-x-0.5 transition-transform"></i>
        </RouterLink>
      </div>

      <!-- Loading State -->
      <SkeletonLoader v-if="campaignStore.isLoading" type="card" :count="3" />

      <!-- Campaign Grid -->
      <div v-else-if="campaignStore.campaigns.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <CampaignCard
          v-for="campaign in campaignStore.campaigns"
          :key="campaign.id"
          :campaign="campaign"
        />
      </div>

      <div v-else class="text-center py-12 text-slate-400">
        Belum ada kampanye aktif yang tersedia saat ini.
      </div>
    </section>

    <!-- Categories Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center max-w-2xl mx-auto mb-10">
        <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Kategori Kampanye</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
          Dukung Berbagai Sektor Inovasi
        </h2>
        <p class="text-xs sm:text-sm text-slate-500 mt-2">
          Temukan proyek yang sesuai dengan nilai dan minat Anda di berbagai kategori pilihan.
        </p>
      </div>

      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <RouterLink
          v-for="cat in categories"
          :key="cat.slug"
          :to="`/campaigns?category=${cat.slug}`"
          class="p-5 rounded-2xl bg-white border border-slate-200/80 hover:border-blue-500 hover:shadow-lg transition-all duration-200 text-center flex flex-col items-center justify-center group"
        >
          <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition">
            <i :class="[cat.icon, 'text-xl']"></i>
          </div>
          <h4 class="font-bold text-xs text-slate-900 group-hover:text-blue-600 transition">
            {{ cat.name }}
          </h4>
          <span class="text-[11px] text-slate-400 mt-1 font-medium">
            {{ cat.count }}
          </span>
        </RouterLink>
      </div>
    </section>

    <!-- How Virtual Escrow Works Section -->
    <section class="bg-slate-900 text-white py-16 px-4 sm:px-6 lg:px-8 rounded-3xl max-w-7xl mx-auto relative overflow-hidden">
      <div class="text-center max-w-2xl mx-auto mb-12">
        <span class="text-xs font-bold uppercase tracking-wider text-sky-400">Keamanan Dana Terjamin</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-1">
          Bagaimana CoFund Melindungi Uang Anda?
        </h2>
        <p class="text-xs sm:text-sm text-slate-300 mt-2">
          Sistem Virtual Escrow terotomasi menjamin dana tidak berpindah ke kreator sebelum target tercapai.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 relative z-10">
        <div class="bg-slate-800/80 border border-slate-700/80 p-6 rounded-2xl space-y-3">
          <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-sky-400 flex items-center justify-center font-bold text-sm">
            1
          </div>
          <h4 class="font-bold text-sm text-white">Pilih & Backing</h4>
          <p class="text-xs text-slate-400 leading-relaxed">
            Pilih reward tier atau donasi bebas. Dana disetorkan ke virtual escrow tertahan.
          </p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 p-6 rounded-2xl space-y-3">
          <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-sky-400 flex items-center justify-center font-bold text-sm">
            2
          </div>
          <h4 class="font-bold text-sm text-white">Escrow Holding</h4>
          <p class="text-xs text-slate-400 leading-relaxed">
            Dana ditahan aman dalam sistem platform selama masa kampanye berlangsung.
          </p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 p-6 rounded-2xl space-y-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-sm">
            3a
          </div>
          <h4 class="font-bold text-sm text-emerald-300">Target Tercapai (Disburse)</h4>
          <p class="text-xs text-slate-400 leading-relaxed">
            Jika target tercapai sebelum deadline, 95% dana dicairkan ke saldo kreator otomatis.
          </p>
        </div>

        <div class="bg-slate-800/80 border border-slate-700/80 p-6 rounded-2xl space-y-3">
          <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-sm">
            3b
          </div>
          <h4 class="font-bold text-sm text-purple-300">Gagal (100% Refund)</h4>
          <p class="text-xs text-slate-400 leading-relaxed">
            Jika target tidak tercapai saat deadline, seluruh dana dikembalikan otomatis ke saldo backer.
          </p>
        </div>
      </div>
    </section>

    <!-- Creator Banner Callout -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-800 rounded-3xl p-8 sm:p-12 text-white flex flex-col md:flex-row items-center justify-between gap-8 shadow-xl shadow-blue-600/15">
        <div class="space-y-3 text-center md:text-left">
          <span class="text-xs font-bold uppercase tracking-wider text-blue-200">Untuk Kreator & Inovator</span>
          <h2 class="text-2xl sm:text-3xl font-extrabold">
            Punya Ide Proyek yang Siap Diluncurkan?
          </h2>
          <p class="text-xs sm:text-sm text-blue-100 max-w-lg">
            Daftar sebagai creator, rancang kampanye Anda beserta pilihan reward tier, dan dapatkan dukungan dana dari ribuan backer di seluruh Indonesia.
          </p>
        </div>
        <RouterLink
          to="/creator/campaigns/create"
          class="px-8 py-4 rounded-2xl bg-white text-blue-700 hover:bg-blue-50 font-bold text-sm transition shadow-lg flex-shrink-0"
        >
          Buat Kampanye Sekarang
        </RouterLink>
      </div>
    </section>
  </div>
</template>
