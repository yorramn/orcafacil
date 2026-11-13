<script setup lang="ts">
defineProps<{ id: string; label: string; modelValue?: string; type?: string; required?: boolean; error?: string; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
</script>
<template>
  <div class="grid min-w-0 content-start gap-2.5">
    <label :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-400">
      {{ label }}<span v-if="required" class="ml-1 text-error-500" aria-hidden="true">*</span>
    </label>
    <textarea
      v-if="type === 'textarea'"
      :id="id"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="min-h-28 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs outline-none transition placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 aria-[invalid=true]:border-error-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <input
      v-else
      :id="id"
      :value="modelValue"
      :type="type || 'text'"
      :placeholder="placeholder"
      :required="required"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
      class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs outline-none transition placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 aria-[invalid=true]:border-error-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="error" :id="`${id}-error`" class="text-sm text-error-500" role="alert">{{ error }}</p>
  </div>
</template>
