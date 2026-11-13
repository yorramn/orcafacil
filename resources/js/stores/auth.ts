import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({ twoFactorStep: 'challenge' as 'challenge' | 'setup' | 'confirmed', isPending: false }),
  actions: { setTwoFactorStep(step: 'challenge' | 'setup' | 'confirmed') { this.twoFactorStep = step } },
})
