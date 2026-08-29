<script setup>
import { computed } from 'vue'
import { formatCurrency } from '@/utils/formatCurrency'

const props = defineProps({
  tier: {
    type: Object,
    required: true,
  },
  isSelected: {
    type: Boolean,
    default: false,
  },
  isCampaignActive: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['select'])

const isSoldOut = computed(() => {
  if (props.tier.quota === 0) return false // 0 means unlimited
  return props.tier.remaining_quota <= 0
})
</script>

<template>
  <div
    :class="[
      'rounded-2xl p-6 border transition-all duration-200 flex flex-col justify-between relative overflow-hidden',
      isSelected
        ? 'border-blue-600 bg-blue-50/30 ring-2 ring-blue-500/20 shadow-md'
        : 'border-slate-200 bg-white hover:border-blue-300 hover:shadow-sm',
      isSoldOut ? 'opacity-60 bg-slate-50 border-slate-200' : '',
    ]"
  >
    <!-- Sold out banner badge -->
    <div
      v-if="isSoldOut"
      class="absolute top-3 right-3 px-2 py-0.5 rounded-md bg-rose-100 text-rose-700 text-[10px] font-bold uppercase tracking-wider"
    >
      Habis Terjual
    </div>

    <div>
      <div class="flex items-start justify-between gap-4 mb-2">
        <h4 class="font-bold text-slate-900 text-base leading-snug">
          {{ tier.name }}
        </h4>
      </div>

      <div class="mb-4">
        <span class="text-xl font-extrabold text-blue-600">
          {{ formatCurrency(tier.min_amount) }}
        </span>
        <span class="text-xs text-slate-400 font-medium ml-1">atau lebih</span>
      </div>

      <p class="text-xs text-slate-600 leading-relaxed mb-6">
        {{ tier.reward_description || 'Dukungan untuk proyek tanpa reward fisik tambahan.' }}
      </p>
    </div>

    <div class="space-y-4 pt-4 border-t border-slate-100">
      <!-- Quota info -->
      <div class="flex items-center justify-between text-xs text-slate-500">
        <span>Ketersediaan:</span>
        <span v-if="tier.quota === 0" class="font-semibold text-emerald-600">
          Tidak Terbatas
        </span>
        <span v-else-if="tier.remaining_quota > 0" class="font-semibold text-slate-700">
          Sisa {{ tier.remaining_quota }} dari {{ tier.quota }} slot
        </span>
        <span v-else class="font-semibold text-rose-600">
          Kuota Habis (0 slot)
        </span>
      </div>

      <!-- Action Button -->
      <button
        @click="emit('select', tier)"
        :disabled="isSoldOut || !isCampaignActive"
        :class="[
          'w-full py-2.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm',
          isSelected
            ? 'bg-blue-600 text-white hover:bg-blue-700'
            : isSoldOut || !isCampaignActive
            ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
            : 'bg-slate-900 text-white hover:bg-blue-600',
        ]"
      >
        <i v-if="isSelected" class="pi pi-check text-xs"></i>
        <span>{{ isSoldOut ? 'Slot Habis' : isSelected ? 'Tier Terpilih' : 'Pilih Reward Ini' }}</span>
      </button>
    </div>
  </div>
</template>
