import { useToastStore } from '@/stores/useToastStore'

export function useToast() {
  const store = useToastStore()
  return {
    success: (msg, opts) => store.success(msg, typeof opts === 'object' ? opts : {}),
    error: (msg, opts) => store.error(msg, typeof opts === 'object' ? opts : {}),
    warning: (msg, opts) => store.warning(msg, typeof opts === 'object' ? opts : {}),
    info: (msg, opts) => store.info(msg, typeof opts === 'object' ? opts : {}),
    clear: () => store.clearAll(),
  }
}
