import { defineStore } from 'pinia'

export const useOnboardingStore = defineStore('onboarding', {
  state: () => ({ step: 1, completed: false }),
  actions: { setStep(step: number) { this.step = step }, complete() { this.completed = true } },
})
