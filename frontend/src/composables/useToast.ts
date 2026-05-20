import { reactive } from 'vue'

export type ToastVariant = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: string
  message: string
  variant: ToastVariant
  duration: number
}

/* Module-level reactive list — shared across all useToast() calls */
const toasts = reactive<Toast[]>([])

export function useToast() {
  function addToast(
    message: string,
    variant: ToastVariant = 'info',
    duration = 4000,
  ): string {
    const id = `t-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    toasts.push({ id, message, variant, duration })
    if (duration > 0) {
      setTimeout(() => removeToast(id), duration)
    }
    return id
  }

  function removeToast(id: string): void {
    const i = toasts.findIndex(t => t.id === id)
    if (i !== -1) toasts.splice(i, 1)
  }

  const success = (msg: string, duration?: number) => addToast(msg, 'success', duration)
  const error = (msg: string, duration?: number) => addToast(msg, 'error', duration)
  const warning = (msg: string, duration?: number) => addToast(msg, 'warning', duration)
  const info = (msg: string, duration?: number) => addToast(msg, 'info', duration)

  return { toasts, addToast, removeToast, success, error, warning, info }
}
