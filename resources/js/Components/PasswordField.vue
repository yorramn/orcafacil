<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import EyeIcon from '../TailAdminSource/icons/layout/EyeIcon.vue'
import EyeCloseIcon from '../TailAdminSource/icons/layout/EyeCloseIcon.vue'
import LockIcon from '../TailAdminSource/icons/layout/LockIcon.vue'

const props = withDefaults(defineProps<{
  id: string
  label: string
  modelValue: string
  error?: string
  autocomplete?: string
  hint?: string
  hintTone?: 'default' | 'success' | 'warning'
  placeholder?: string
  required?: boolean
}>(), { autocomplete: 'current-password', hintTone: 'default', required: false })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const visible = shallowRef(false)
const inputType = computed(() => visible.value ? 'text' : 'password')
</script>

<template>
  <div class="field-group min-w-0">
    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="id">{{ label }}<span v-if="required" aria-hidden="true" class="ml-1 text-error-500">*</span></label>
    <div class="field-with-icon relative min-w-0">
      <LockIcon class="field-icon pointer-events-none absolute left-3.5 top-3 size-4 text-gray-400" aria-hidden="true" />
      <input
        :id="id"
        class="form-input form-input--with-leading h-11 w-full min-w-0 scroll-mt-28 rounded-[10px] border border-gray-300 bg-white px-4 py-2.5 pl-10 pr-12 text-sm text-gray-800 shadow-theme-xs outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        :class="error ? 'form-input--error !border-error-500 !ring-error-500/10' : ''"
        :type="inputType"
        :value="modelValue"
        :autocomplete="autocomplete"
        :placeholder="placeholder"
        :required="required"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        type="button"
        class="icon-button field-toggle inline-flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-brand-500 focus-visible:outline-2 focus-visible:outline-brand-500 [&_svg]:size-4 absolute right-0.5 top-0.5 dark:hover:bg-white/5"
        :aria-label="visible ? `Ocultar ${label.toLowerCase()}` : `Mostrar ${label.toLowerCase()}`"
        :title="visible ? 'Ocultar senha' : 'Mostrar senha'"
        @click="visible = !visible"
      >
        <EyeCloseIcon v-if="visible" aria-hidden="true" />
        <EyeIcon v-else aria-hidden="true" />
      </button>
    </div>
    <p v-if="hint && !error" :id="`${id}-hint`" class="field-hint mt-2 text-sm leading-5 text-td-body" :class="{ 'text-green-700 dark:text-green-400': hintTone === 'success', 'text-amber-700 dark:text-amber-400': hintTone === 'warning' }" :aria-live="hintTone === 'default' ? undefined : 'polite'">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" class="field-error mt-2 text-sm leading-5 text-error-500" role="alert">{{ error }}</p>
  </div>
</template>
