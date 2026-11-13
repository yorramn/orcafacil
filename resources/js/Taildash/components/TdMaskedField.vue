<script setup lang="ts">
import { formatCurrencyFromDigits } from '../../utils/brazilianInputMasks'

const props = defineProps<{ id: string; label: string; modelValue?: string; mask?: string; required?: boolean; error?: string; errorKey?: string; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string]; raw: [value: string] }>()

function blurCurrency(event: FocusEvent) {
  if (props.mask !== 'currency') return
  const input = event.currentTarget as HTMLInputElement
  const value = input.value.replace(/\D/g, '')
  emit('raw', value)
  const formatted = formatCurrencyFromDigits(value)
  input.value = formatted
  emit('update:modelValue', formatted)
}
function update(event: Event) {
  const input = event.target as HTMLInputElement
  const raw = input.value.replace(/\D/g, '')
  const formatted = formatCurrencyFromDigits(raw)
  input.value = formatted
  input.setSelectionRange(formatted.length, formatted.length)
  emit('update:modelValue', formatted)
  emit('raw', raw)
}
</script>

<template>
  <div class="grid min-w-0 content-start gap-2.5">
    <label :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-400">
      {{ label }}<span v-if="required" class="ml-1 text-error-500" aria-hidden="true">*</span>
    </label>
    <input
      :id="id"
      :data-mask="mask"
      :data-error-field="errorKey"
      :value="modelValue"
      :placeholder="placeholder || '0,00'"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      inputmode="numeric"
      class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs outline-none transition placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 aria-[invalid=true]:border-error-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      @blur="blurCurrency"
      @input="update"
    />
    <p v-if="error" :id="`${id}-error`" class="text-sm text-error-500" role="alert">{{ error }}</p>
  </div>
</template>
