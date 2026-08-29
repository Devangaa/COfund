<script setup>
defineProps({
  title: {
    type: String,
    default: 'Belum Ada Data',
  },
  description: {
    type: String,
    default: 'Tidak ada data yang ditemukan untuk kriteria pencarian ini.',
  },
  icon: {
    type: String,
    default: 'pi pi-folder-open',
  },
  actionText: {
    type: String,
    default: '',
  },
})

defineEmits(['action'])
</script>

<template>
  <div class="flex flex-col items-center justify-center p-12 text-center bg-white rounded-2xl border border-dashed border-slate-200">
    <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center mb-4 shadow-sm">
      <i :class="[icon, 'text-2xl']"></i>
    </div>
    <h3 class="text-lg font-bold text-slate-800 mb-1">{{ title }}</h3>
    <p class="text-sm text-slate-500 max-w-sm mb-6">{{ description }}</p>
    <button
      v-if="actionText"
      @click="$emit('action')"
      class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm"
    >
      <i class="pi pi-plus text-xs"></i>
      <span>{{ actionText }}</span>
    </button>
  </div>
</template>
