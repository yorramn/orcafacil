<script setup lang="ts">
import { onBeforeUnmount, shallowRef, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CheckCircleIcon, ExclamationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline'

type SharedPage = { flash?: { success?: string | null; error?: string | null }; errors?: Record<string, string> }
const emit = defineEmits<{ visibilityChange: [visible: boolean] }>()
const page = usePage<SharedPage>()
const toast = shallowRef<{ kind: 'success' | 'error'; message: string } | null>(null)
let dismissTimer: ReturnType<typeof setTimeout> | undefined

function stopDismissTimer() {
  if (dismissTimer) clearTimeout(dismissTimer)
  dismissTimer = undefined
}

function startDismissTimer() {
  stopDismissTimer()
  if (toast.value) dismissTimer = setTimeout(() => { toast.value = null }, 5000)
}

function resumeDismissTimer(event: FocusEvent) {
  if (!(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) startDismissTimer()
}

watch(() => page.props, (props) => {
  const success = props.flash?.success
  const error = props.flash?.error || Object.values(props.errors || {})[0]
  toast.value = success ? { kind: 'success', message: success } : error ? { kind: 'error', message: error } : null
  startDismissTimer()
}, { immediate: true })

watch(toast, (value) => { emit('visibilityChange', Boolean(value)) }, { immediate: true })
onBeforeUnmount(stopDismissTimer)
</script>

<template>
  <div v-if="toast" class="relative z-20 ml-auto mr-4 mt-4 flex w-[min(24rem,calc(100vw-2rem))] items-center gap-3 rounded-xl border bg-white py-2 pl-4 pr-2 text-sm leading-5 shadow-theme-lg sm:mr-6 lg:fixed lg:right-4 lg:top-2 lg:z-50 lg:mr-0 lg:mt-0 dark:bg-gray-900" :class="toast.kind === 'success' ? 'border-success-200 text-success-700 dark:border-success-800 dark:text-success-400' : 'border-error-200 text-error-700 dark:border-error-800 dark:text-error-400'" :role="toast.kind === 'error' ? 'alert' : 'status'" :aria-live="toast.kind === 'error' ? 'assertive' : 'polite'" aria-atomic="true" @mouseenter="stopDismissTimer" @mouseleave="startDismissTimer" @focusin="stopDismissTimer" @focusout="resumeDismissTimer">
    <CheckCircleIcon v-if="toast.kind === 'success'" class="size-5 shrink-0" aria-hidden="true" />
    <ExclamationCircleIcon v-else class="size-5 shrink-0" aria-hidden="true" />
    <p class="min-w-0 flex-1">{{ toast.message }}</p>
    <button class="inline-flex size-11 shrink-0 items-center justify-center rounded-lg hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:hover:bg-gray-800" type="button" aria-label="Fechar mensagem" @click="toast = null"><XMarkIcon class="size-4" aria-hidden="true" /></button>
  </div>
</template>
