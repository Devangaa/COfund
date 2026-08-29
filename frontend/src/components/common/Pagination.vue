<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true,
  },
  lastPage: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    default: 0,
  },
  perPage: {
    type: Number,
    default: 10,
  },
})

const emit = defineEmits(['page-change'])

const pages = computed(() => {
  const current = props.currentPage
  const last = props.lastPage
  const delta = 2
  const range = []

  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i)
  }

  if (current - delta > 2) {
    range.unshift('...')
  }
  if (current + delta < last - 1) {
    range.push('...')
  }

  range.unshift(1)
  if (last > 1) {
    range.push(last)
  }

  return range
})

function changePage(page) {
  if (page === '...' || page === props.currentPage || page < 1 || page > props.lastPage) return
  emit('page-change', page)
}
</script>

<template>
  <div v-if="lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 py-4">
    <div class="text-xs text-slate-500">
      Menampilkan halaman <span class="font-semibold text-slate-700">{{ currentPage }}</span> dari
      <span class="font-semibold text-slate-700">{{ lastPage }}</span> (Total {{ total }} data)
    </div>

    <div class="flex items-center gap-1">
      <button
        @click="changePage(currentPage - 1)"
        :disabled="currentPage === 1"
        class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        <i class="pi pi-chevron-left text-xs"></i>
      </button>

      <template v-for="(p, index) in pages" :key="index">
        <span
          v-if="p === '...'"
          class="w-9 h-9 flex items-center justify-center text-slate-400 text-xs select-none"
        >
          ...
        </span>
        <button
          v-else
          @click="changePage(p)"
          :class="[
            'w-9 h-9 flex items-center justify-center rounded-xl text-xs font-semibold transition',
            p === currentPage
              ? 'bg-blue-600 text-white shadow-sm'
              : 'border border-slate-200 text-slate-700 hover:bg-slate-100',
          ]"
        >
          {{ p }}
        </button>
      </template>

      <button
        @click="changePage(currentPage + 1)"
        :disabled="currentPage === lastPage"
        class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed transition"
      >
        <i class="pi pi-chevron-right text-xs"></i>
      </button>
    </div>
  </div>
</template>
