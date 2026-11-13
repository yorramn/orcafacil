<script setup lang="ts">
import { nextTick, useId, useTemplateRef, watch } from 'vue'

const props = withDefaults(defineProps<{ open: boolean; title: string; message: string; busy?: boolean; confirmLabel?: string }>(), { busy: false, confirmLabel: 'Sim' })
const emit = defineEmits<{ confirm: []; cancel: [] }>()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const titleId = useId()
const messageId = useId()
let previousFocus: HTMLElement | null = null

function cancel() {
  if (!props.busy) emit('cancel')
}

watch(() => props.open, async (open) => {
  await nextTick()
  if (open && !dialog.value?.open) {
    previousFocus = document.activeElement as HTMLElement | null
    dialog.value?.showModal()
  }
  if (!open && dialog.value?.open) {
    dialog.value.close()
    previousFocus?.focus()
    previousFocus = null
  }
}, { immediate: true })
</script>

<template>
  <Teleport to="body">
    <dialog ref="dialog" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[min(92vw,28rem)] max-w-none overflow-y-auto rounded-xl border border-gray-200 bg-white p-0 text-gray-800 shadow-theme-lg backdrop:bg-gray-900/50 dark:border-gray-700 dark:bg-gray-900 dark:text-white" :aria-labelledby="titleId" :aria-describedby="messageId" @cancel.prevent="cancel">
      <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700"><h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2></div>
      <p :id="messageId" class="px-5 py-5 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ message }}</p>
      <div class="flex justify-end gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-700">
        <button class="min-h-11 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:hover:bg-gray-800" type="button" :disabled="busy" autofocus @click="cancel">Não</button>
        <button class="min-h-11 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" type="button" :disabled="busy" @click="emit('confirm')">{{ confirmLabel }}</button>
      </div>
    </dialog>
  </Teleport>
</template>
