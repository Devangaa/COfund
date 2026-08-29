import { ref } from 'vue'
import { backingService } from '@/services/backingService'
import { useAuthStore } from '@/stores/useAuthStore'
import { useToast } from '@/composables/useToast'

export function useBacking() {
  const toast = useToast()
  const authStore = useAuthStore()

  const isBackingDialogOpen = ref(false)
  const isSubmitting = ref(false)
  const selectedTier = ref(null)
  const backingAmount = ref(10000)

  function openBackingDialog(tier = null, defaultAmount = null) {
    selectedTier.value = tier
    if (tier && tier.min_amount) {
      backingAmount.value = Number(tier.min_amount)
    } else if (defaultAmount) {
      backingAmount.value = Number(defaultAmount)
    } else {
      backingAmount.value = 10000
    }
    isBackingDialogOpen.value = true
  }

  function closeBackingDialog() {
    isBackingDialogOpen.value = false
    selectedTier.value = null
  }

  async function submitBacking(campaignSlug, payloadData = {}, onSuccess) {
    if (!authStore.isAuthenticated) {
      toast.warning('Silakan login terlebih dahulu untuk melakukan backing.')
      return { success: false, needLogin: true }
    }
    if (!authStore.isEmailVerified) {
      toast.warning('Email Anda belum diverifikasi. Silakan verifikasi email terlebih dahulu.')
      return { success: false, needVerification: true }
    }

    // Support both (campaignSlug, callback) and (campaignSlug, payloadData, callback) signatures
    let actualPayload = payloadData
    let actualOnSuccess = onSuccess
    if (typeof payloadData === 'function') {
      actualOnSuccess = payloadData
      actualPayload = {}
    }

    isSubmitting.value = true
    try {
      const finalAmount = actualPayload?.amount !== undefined ? Number(actualPayload.amount) : Number(backingAmount.value)
      const finalTier = actualPayload?.tier !== undefined ? actualPayload.tier : selectedTier.value

      const payload = {
        amount: finalAmount,
      }
      if (finalTier?.id) {
        payload.tier_id = finalTier.id
      }

      const response = await backingService.store(campaignSlug, payload)
      toast.success(response.data?.message || 'Terima kasih! Dukungan Anda berhasil dicatat.')
      closeBackingDialog()

      // Refresh authenticated user to sync wallet balance immediately in navbar
      try {
        await authStore.fetchMe()
      } catch {
        // ignore fetchMe failure if any
      }

      if (typeof actualOnSuccess === 'function') {
        actualOnSuccess(response.data?.data)
      }
      return { success: true, data: response.data?.data }
    } catch (error) {
      return { success: false, error }
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    isBackingDialogOpen,
    isSubmitting,
    selectedTier,
    backingAmount,
    openBackingDialog,
    closeBackingDialog,
    submitBacking,
  }
}
