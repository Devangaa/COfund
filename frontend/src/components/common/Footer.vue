<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="bg-slate-900 border-t border-slate-800 text-slate-400 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-8 lg:gap-12">
        <!-- Brand Info -->
        <div class="lg:col-span-2 space-y-4">
          <RouterLink to="/" class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 via-blue-500 to-sky-400 flex items-center justify-center text-white font-bold text-xl shadow-lg shadow-blue-500/20"
            >
              <i class="pi pi-bolt text-lg"></i>
            </div>
            <div class="flex flex-col">
              <span class="text-xl font-black tracking-tight text-white flex items-center gap-1">
                Co<span class="text-sky-400">Fund</span>
              </span>
              <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold -mt-1">
                Crowdfunding FinTech
              </span>
            </div>
          </RouterLink>
          <p class="text-sm text-slate-400 max-w-sm leading-relaxed">
            Platform pendanaan gotong-royong modern dengan sistem virtual escrow otomatis dan transparansi penuh untuk mewujudkan inovasi creator Indonesia.
          </p>
          <div class="flex items-center gap-3 pt-2">
            <span class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700 cursor-pointer transition">
              <i class="pi pi-twitter text-sm"></i>
            </span>
            <span class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700 cursor-pointer transition">
              <i class="pi pi-instagram text-sm"></i>
            </span>
            <span class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700 cursor-pointer transition">
              <i class="pi pi-github text-sm"></i>
            </span>
            <span class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700 cursor-pointer transition">
              <i class="pi pi-linkedin text-sm"></i>
            </span>
          </div>
        </div>

        <!-- Col 1: Jelajah -->
        <div class="space-y-3">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider">Jelajah</h4>
          <ul class="space-y-2 text-sm">
            <li>
              <RouterLink to="/campaigns" class="hover:text-white transition">Semua Kampanye</RouterLink>
            </li>
            <li>
              <RouterLink to="/campaigns?category=teknologi" class="hover:text-white transition">Teknologi & Inovasi</RouterLink>
            </li>
            <li>
              <RouterLink to="/campaigns?category=sosial-kemanusiaan" class="hover:text-white transition">Sosial & Kemanusiaan</RouterLink>
            </li>
            <li>
              <RouterLink to="/campaigns?category=lingkungan" class="hover:text-white transition">Lingkungan</RouterLink>
            </li>
          </ul>
        </div>

        <!-- Col 2: Creator -->
        <div class="space-y-3">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider">Creator Hub</h4>
          <ul class="space-y-2 text-sm">
            <li>
              <RouterLink to="/creator/campaigns/create" class="hover:text-white transition">Mulai Kampanye</RouterLink>
            </li>
            <li>
              <RouterLink to="/creator/dashboard" class="hover:text-white transition">Dashboard Creator</RouterLink>
            </li>
            <li>
              <span class="hover:text-white cursor-pointer transition">Panduan Penggalangan</span>
            </li>
            <li>
              <span class="hover:text-white cursor-pointer transition">Struktur Biaya (5%)</span>
            </li>
          </ul>
        </div>

        <!-- Col 3: Keamanan & Legal -->
        <div class="space-y-3">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider">Keamanan</h4>
          <ul class="space-y-2 text-sm">
            <li>
              <span class="hover:text-white cursor-pointer transition">Virtual Escrow</span>
            </li>
            <li>
              <span class="hover:text-white cursor-pointer transition">Jaminan Refund Otomatis</span>
            </li>
            <li>
              <span class="hover:text-white cursor-pointer transition">Ketentuan Layanan</span>
            </li>
            <li>
              <span class="hover:text-white cursor-pointer transition">Kebijakan Privasi</span>
            </li>
          </ul>
        </div>
      </div>

      <div class="border-t border-slate-800 mt-12 pt-8 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 gap-4">
        <p>&copy; 2026 CoFund Platform. Hak Cipta Dilindungi.</p>
        <p class="flex items-center gap-1">
          <span>Dibuat dengan</span>
          <i class="pi pi-heart-fill text-rose-500 text-[10px]"></i>
          <span>untuk ekosistem kreatif Indonesia</span>
        </p>
      </div>
    </div>
  </footer>
</template>
