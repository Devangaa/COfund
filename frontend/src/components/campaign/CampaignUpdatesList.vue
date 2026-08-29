<script setup>
import { formatDateTime } from '@/utils/formatDate'

defineProps({
  updates: {
    type: Array,
    default: () => [],
  },
  isOwner: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['edit', 'delete'])
</script>

<template>
  <div class="space-y-6">
    <div v-if="updates.length === 0" class="text-center py-10 text-slate-400 text-sm">
      <i class="pi pi-bell-slash text-3xl mb-2 block"></i>
      Belum ada kabar terbaru dari creator untuk kampanye ini.
    </div>

    <div
      v-for="(update, idx) in updates"
      :key="update.id || idx"
      class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-sm space-y-3 relative group transition hover:border-slate-300"
    >
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg">
            Update #{{ updates.length - idx }}
          </span>
          <span class="text-xs text-slate-400">
            {{ formatDateTime(update.created_at) }}
          </span>
        </div>

        <!-- Creator Actions (Edit & Delete) -->
        <div v-if="isOwner" class="flex items-center gap-1.5">
          <button
            type="button"
            @click="emit('edit', update)"
            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 font-semibold text-xs transition flex items-center gap-1"
            title="Edit Kabar Proyek"
          >
            <i class="pi pi-pencil text-[10px]"></i>
            <span>Edit</span>
          </button>
          <button
            type="button"
            @click="emit('delete', update)"
            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 font-semibold text-xs transition flex items-center gap-1"
            title="Hapus Kabar Proyek"
          >
            <i class="pi pi-trash text-[10px]"></i>
            <span>Hapus</span>
          </button>
        </div>
      </div>

      <h4 class="text-base sm:text-lg font-bold text-slate-900">
        {{ update.title }}
      </h4>

      <div
        v-if="update.content_html"
        class="prose prose-sm max-w-none text-slate-600 text-xs sm:text-sm leading-relaxed"
        v-html="update.content_html"
      ></div>
      <p v-else class="text-xs sm:text-sm text-slate-600 leading-relaxed whitespace-pre-line">
        {{ update.content }}
      </p>
    </div>
  </div>
</template>
