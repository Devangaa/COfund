<script setup>
import Navbar from '@/components/common/Navbar.vue'
import Footer from '@/components/common/Footer.vue'
</script>

<template>
  <div class="min-h-screen flex flex-col bg-slate-50">
    <Navbar />
    <main class="flex-1 w-full">
      <RouterView />
    </main>
    <Footer />
  </div>
</template>
