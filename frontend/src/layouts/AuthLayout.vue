<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen flex flex-col justify-center bg-slate-900 py-12 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Ambient glow & grid pattern -->
    <div class="absolute inset-0 bg-[radial-gradient(#1e293b_1px,transparent_1px)] [background-size:24px_24px] opacity-40"></div>
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-[400px] h-[400px] bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 text-center mb-8">
      <RouterLink to="/" class="inline-flex items-center gap-3 group">
        <div
          class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 via-blue-500 to-sky-400 flex items-center justify-center text-white font-bold text-2xl shadow-xl shadow-blue-500/20 group-hover:scale-105 transition transform"
        >
          <i class="pi pi-bolt text-xl"></i>
        </div>
        <div class="flex flex-col text-left">
          <span class="text-2xl font-black tracking-tight text-white flex items-center gap-1">
            Co<span class="text-sky-400">Fund</span>
          </span>
          <span class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold -mt-1">
            Crowdfunding FinTech
          </span>
        </div>
      </RouterLink>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
      <div class="bg-slate-900/90 border border-slate-800/90 shadow-2xl rounded-3xl p-8 sm:p-10 backdrop-blur-xl">
        <RouterView />
      </div>

      <div class="mt-8 text-center text-xs text-slate-500">
        <RouterLink to="/" class="hover:text-slate-400 transition inline-flex items-center gap-1.5">
          <i class="pi pi-arrow-left text-[10px]"></i>
          <span>Kembali ke Beranda</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
