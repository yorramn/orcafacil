import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', {
  state: () => ({ filterSheetOpen: false, activeModal: null as string | null, reducedMotion: false }),
  actions: {
    openFilterSheet() { this.filterSheetOpen = true },
    closeFilterSheet() { this.filterSheetOpen = false },
    openModal(name: string) { this.activeModal = name },
    closeModal() { this.activeModal = null },
  },
})
