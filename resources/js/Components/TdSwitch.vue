<script setup lang="ts">
const props = defineProps<{ id: string; label: string; error?: string; disabled?: boolean }>()
const model = defineModel<boolean>({ default: false })
</script>

<template>
  <div class="grid min-w-0 content-start gap-1.5">
    <label :for="id" class="inline-flex min-h-11 w-fit cursor-pointer items-center gap-3">
      <input
        :id="id"
        v-model="model"
        type="checkbox"
        role="switch"
        :disabled="disabled"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="error ? `${id}-error` : undefined"
        class="peer sr-only"
      />
      <span
        aria-hidden="true"
        class="relative inline-flex h-6 w-11 shrink-0 rounded-full bg-gray-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:size-5 after:rounded-full after:bg-white after:shadow-sm after:transition-transform peer-checked:bg-td-primary peer-checked:after:translate-x-5 peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-td-primary peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-60 dark:bg-gray-700"
      />
      <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ label }}</span>
    </label>
    <p v-if="error" :id="`${id}-error`" class="text-sm text-td-red" role="alert">{{ error }}</p>
  </div>
</template>
