<script setup>
import { computed } from 'vue'

const props = defineProps({
  percentage: {
    type: Number,
    required: true,
    default: 0,
  },
  showLabel: {
    type: Boolean,
    default: false,
  },
  height: {
    type: String,
    default: 'h-2.5',
  },
  animated: {
    type: Boolean,
    default: true,
  },
})

const clampedPercentage = computed(() => {
  const p = Number(props.percentage) || 0
  return Math.min(Math.max(p, 0), 100)
})
</script>

<template>
  <div class="w-full">
    <div v-if="showLabel" class="flex justify-between text-xs font-semibold mb-1 text-slate-700">
      <span>Tercapai</span>
      <span class="text-blue-600 font-bold">{{ percentage.toFixed(1) }}%</span>
    </div>
    <div :class="['w-full bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200/60', height]">
      <div
        :class="[
          'h-full rounded-full bg-gradient-to-r from-blue-600 via-blue-500 to-sky-400 transition-all duration-700 ease-out shadow-sm',
          animated ? 'relative' : '',
        ]"
        :style="{ width: `${clampedPercentage}%` }"
      >
        <!-- subtle light shine animation -->
        <div
          v-if="animated && clampedPercentage > 0"
          class="absolute inset-0 bg-white/25 rounded-full opacity-60 animate-pulse"
        ></div>
      </div>
    </div>
  </div>
</template>
