<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-[70vh] flex flex-col items-center justify-center text-center px-4 py-16">
    <div class="w-20 h-20 rounded-3xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 shadow-sm">
      <i class="pi pi-compass text-3xl"></i>
    </div>
    <span class="text-xs font-bold uppercase tracking-wider text-blue-600">Error 404</span>
    <h1 class="text-3xl sm:text-4xl font-black text-slate-900 mt-2 mb-3">
      Halaman Tidak Ditemukan
    </h1>
    <p class="text-sm text-slate-500 max-w-md mb-8 leading-relaxed">
      Halaman atau kampanye yang Anda tuju tidak tersedia, telah dipindahkan, atau tautan yang Anda masukkan kurang tepat.
    </p>
    <RouterLink
      to="/"
      class="px-6 py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-lg shadow-blue-600/25 transition inline-flex items-center gap-2"
    >
      <i class="pi pi-home text-xs"></i>
      <span>Kembali ke Beranda</span>
    </RouterLink>
  </div>
</template>
