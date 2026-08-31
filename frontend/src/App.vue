<script setup>
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/useAuthStore'
import GlobalToastContainer from '@/components/common/GlobalToastContainer.vue'
import GlobalConfirmModal from '@/components/common/GlobalConfirmModal.vue'

const authStore = useAuthStore()

onMounted(async () => {
  if (authStore.token) {
    await authStore.fetchMe()
  }
})
</script>

<template>
  <RouterView />
  <!-- Custom Reactive Popups -->
  <GlobalToastContainer />
  <GlobalConfirmModal />
</template>

<style>
/* Global styling handled by main.css and tailwind */
</style>
