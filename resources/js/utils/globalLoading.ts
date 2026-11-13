import { shallowRef } from 'vue'

export const isGlobalLoading = shallowRef(false)

let activeRequests = 0

/** Tracks a request outside the Inertia router and returns an idempotent cleanup. */
export function beginGlobalRequest(): () => void {
  let finished = false
  activeRequests += 1
  isGlobalLoading.value = true

  return () => {
    if (finished) return
    finished = true
    activeRequests = Math.max(0, activeRequests - 1)
    isGlobalLoading.value = activeRequests > 0
  }
}
