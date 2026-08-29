<script setup>
defineProps({
  type: {
    type: String,
    default: 'card', // 'card' | 'table' | 'text' | 'detail'
  },
  count: {
    type: Number,
    default: 1,
  },
})
</script>

<template>
  <div v-if="type === 'card'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div
      v-for="i in count"
      :key="i"
      class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col space-y-4"
    >
      <div class="h-48 w-full rounded-xl skeleton-shimmer"></div>
      <div class="h-4 w-1/4 rounded skeleton-shimmer"></div>
      <div class="h-6 w-3/4 rounded skeleton-shimmer"></div>
      <div class="h-4 w-full rounded skeleton-shimmer"></div>
      <div class="h-2.5 w-full rounded-full skeleton-shimmer mt-2"></div>
      <div class="flex justify-between items-center pt-2">
        <div class="h-5 w-24 rounded skeleton-shimmer"></div>
        <div class="h-5 w-16 rounded skeleton-shimmer"></div>
      </div>
    </div>
  </div>

  <div v-else-if="type === 'table'" class="space-y-3">
    <div v-for="i in count" :key="i" class="h-12 w-full rounded-xl skeleton-shimmer"></div>
  </div>

  <div v-else-if="type === 'detail'" class="space-y-6">
    <div class="h-10 w-2/3 rounded-xl skeleton-shimmer"></div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-2 h-96 rounded-2xl skeleton-shimmer"></div>
      <div class="h-96 rounded-2xl skeleton-shimmer"></div>
    </div>
  </div>

  <div v-else class="space-y-2">
    <div v-for="i in count" :key="i" class="h-4 rounded skeleton-shimmer w-full"></div>
  </div>
</template>
