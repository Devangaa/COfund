<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { formatCurrency } from '@/utils/formatCurrency'
import { getDaysRemaining } from '@/utils/formatDate'
import { getImageUrl, onImageError } from '@/utils/imageHelper'
import ProgressBar from '@/components/common/ProgressBar.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'

const props = defineProps({
  campaign: {
    type: Object,
    required: true,
  },
  showStatus: {
    type: Boolean,
    default: false,
  },
})

const primaryImageUrl = computed(() => {
  if (props.campaign.images && props.campaign.images.length > 0) {
    const primary = props.campaign.images.find((img) => img.is_primary) || props.campaign.images[0]
    return getImageUrl(primary.url)
  }
  return getImageUrl(null)
})

const daysInfo = computed(() => {
  return getDaysRemaining(props.campaign.deadline)
})

const progress = computed(() => {
  return Number(props.campaign.progress_percentage) || 0
})
</script>

<template>
  <div
    class="group bg-white rounded-2xl border border-slate-100/90 shadow-sm hover:shadow-elevated transition-all duration-300 flex flex-col overflow-hidden hover:-translate-y-1"
  >
    <!-- Card Image Header (1:1 Persegi Kotak) -->
    <div class="relative aspect-square w-full overflow-hidden bg-slate-100">
      <img
        :src="primaryImageUrl"
        :alt="campaign.title"
        @error="onImageError"
        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
        loading="lazy"
      />
      <!-- Gradient overlay -->
      <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent"></div>

      <!-- Category Pill -->
      <div class="absolute top-3 left-3">
        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-900/80 backdrop-blur-md text-sky-300 border border-slate-700/50 shadow-sm">
          {{ campaign.category?.name || 'Inovasi' }}
        </span>
      </div>

      <!-- Status badge (for creator/admin view) -->
      <div v-if="showStatus" class="absolute top-3 right-3">
        <StatusBadge type="campaign" :value="campaign.status" size="sm" />
      </div>

      <!-- Days remaining bottom overlay -->
      <div class="absolute bottom-3 left-3 text-white text-xs font-semibold flex items-center gap-1.5">
        <i class="pi pi-calendar text-[11px] text-sky-400"></i>
        <span>{{ daysInfo.text }}</span>
      </div>
    </div>

    <!-- Card Body -->
    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
      <div>
        <!-- Creator Info -->
        <div class="flex items-center gap-2 mb-2">
          <div class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold text-[10px] flex items-center justify-center">
            {{ campaign.creator?.name?.charAt(0)?.toUpperCase() || 'C' }}
          </div>
          <span class="text-xs font-medium text-slate-500 truncate">
            {{ campaign.creator?.name || 'Inisiator' }}
          </span>
        </div>

        <!-- Title -->
        <RouterLink :to="`/campaigns/${campaign.slug}`" class="block group-hover:text-blue-600 transition">
          <h3 class="font-bold text-slate-900 text-base line-clamp-2 leading-snug">
            {{ campaign.title }}
          </h3>
        </RouterLink>

        <!-- Excerpt -->
        <p class="text-xs text-slate-500 line-clamp-2 mt-2 leading-relaxed">
          {{ campaign.description }}
        </p>
      </div>

      <!-- Funding Metrics -->
      <div class="space-y-3 pt-2 border-t border-slate-100">
        <!-- Progress Bar -->
        <ProgressBar :percentage="progress" height="h-2" />

        <div class="flex items-baseline justify-between">
          <div>
            <div class="text-sm font-bold text-slate-900">
              {{ formatCurrency(campaign.collected_amount) }}
            </div>
            <div class="text-[11px] text-slate-400">
              dari {{ formatCurrency(campaign.target_amount, true) }}
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-extrabold text-blue-600">
              {{ progress.toFixed(0) }}%
            </div>
            <div class="text-[11px] text-slate-400">
              tercapai
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
