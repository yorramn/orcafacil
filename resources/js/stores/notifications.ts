import { defineStore } from 'pinia'

export type Notice = { id: number; kind: 'success' | 'error' | 'info'; message: string }

export const useNotificationStore = defineStore('notifications', {
  state: () => ({ items: [] as Notice[], nextId: 1 }),
  actions: {
    push(kind: Notice['kind'], message: string) { const id = this.nextId++; this.items.push({ id, kind, message }); window.setTimeout(() => this.dismiss(id), 5000) },
    dismiss(id: number) { this.items = this.items.filter(item => item.id !== id) },
  },
})
