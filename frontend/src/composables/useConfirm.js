import { useConfirmStore } from '@/stores/useConfirmStore'

export function useConfirm() {
  const store = useConfirmStore()
  return {
    confirm: (options) => {
      if (typeof options === 'string') {
        return store.showConfirm({ message: options })
      }
      return store.showConfirm(options)
    },
  }
}
