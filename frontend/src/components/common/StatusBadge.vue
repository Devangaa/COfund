<script setup>
import { computed } from 'vue'
import { CAMPAIGN_STATUS, BACKING_STATUS, TRANSACTION_TYPE, ROLE_BADGE } from '@/utils/badgeHelper'

const props = defineProps({
  type: {
    type: String,
    default: 'campaign', // 'campaign' | 'backing' | 'transaction' | 'role'
  },
  value: {
    type: String,
    required: true,
  },
  showIcon: {
    type: Boolean,
    default: true,
  },
  size: {
    type: String,
    default: 'md', // 'sm' | 'md' | 'lg'
  },
})

const badgeConfig = computed(() => {
  const val = (props.value || '').toLowerCase()
  if (props.type === 'campaign') {
    return CAMPAIGN_STATUS[val] || { label: props.value, bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' }
  }
  if (props.type === 'backing') {
    return BACKING_STATUS[val] || { label: props.value, bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' }
  }
  if (props.type === 'transaction') {
    return TRANSACTION_TYPE[val] || { label: props.value, bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' }
  }
  if (props.type === 'role') {
    return ROLE_BADGE[val] || { label: props.value, bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' }
  }
  return { label: props.value, bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' }
})

const sizeClasses = computed(() => {
  if (props.size === 'sm') return 'text-xs px-2 py-0.5'
  if (props.size === 'lg') return 'text-sm px-3 py-1.5 font-medium'
  return 'text-xs px-2.5 py-1 font-medium'
})
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-full border',
      badgeConfig.bg,
      badgeConfig.text,
      badgeConfig.border,
      sizeClasses,
    ]"
  >
    <i v-if="showIcon && badgeConfig.icon" :class="[badgeConfig.icon, 'text-[11px]']"></i>
    <span>{{ badgeConfig.label }}</span>
  </span>
</template>
