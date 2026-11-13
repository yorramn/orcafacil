<script setup lang="ts">
import { computed, shallowRef, useId } from 'vue'

withDefaults(defineProps<{
  form?: string
  label?: string
  requiredIncomplete?: boolean
  processing?: boolean
}>(), { label: 'Salvar', requiredIncomplete: false, processing: false })

const tooltipId = useId()
const hovered = shallowRef(false)
const focused = shallowRef(false)
const tooltipOpen = computed(() => hovered.value || focused.value)
</script>

<template>
  <span class="relative inline-flex min-w-0" @pointerenter="hovered = true" @pointerleave="hovered = false">
    <button
      class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
      type="submit"
      :form="form"
      :disabled="requiredIncomplete || processing"
      :aria-busy="processing"
    >{{ processing ? 'Salvando…' : label }}</button>
    <button
      v-if="requiredIncomplete && !processing"
      class="absolute inset-0 z-10 cursor-not-allowed rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
      type="button"
      aria-label="Salvar indisponível"
      :aria-describedby="tooltipId"
      @click="focused = true"
      @focus="focused = true"
      @blur="focused = false"
      @keydown.esc="focused = false"
    />
    <span
      v-if="requiredIncomplete && !processing"
      v-show="tooltipOpen"
      :id="tooltipId"
      class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 w-max max-w-[min(15rem,calc(100vw-2rem))] rounded-lg bg-gray-900 px-3 py-2 text-center text-xs font-medium leading-5 text-white shadow-theme-lg dark:bg-gray-700"
      role="tooltip"
    >Preencha os campos obrigatórios<span class="absolute -bottom-1 right-5 size-2 rotate-45 bg-gray-900 dark:bg-gray-700" aria-hidden="true" /></span>
  </span>
</template>
