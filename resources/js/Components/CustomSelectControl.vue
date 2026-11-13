<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

type Option = { value: string; label: string; disabled?: boolean }

const props = withDefaults(defineProps<{
  id: string
  label: string
  options: Option[]
  modelValue?: string | string[]
  disabled?: boolean
  required?: boolean
  error?: string
  externalLabel?: boolean
  controlAttrs?: Record<string, unknown>
  searchable?: boolean
  multiple?: boolean
  placeholder?: string
  searchPlaceholder?: string
  emptyOptionSelected?: boolean
}>(), {
  modelValue: '',
  controlAttrs: () => ({}),
  placeholder: 'Selecione uma opção',
  searchPlaceholder: 'Buscar opções…',
  emptyOptionSelected: false,
})

const emit = defineEmits<{ 'update:modelValue': [value: string | string[]] }>()
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const searchInput = ref<HTMLInputElement | null>(null)
const open = ref(false)
const query = ref('')
const activeIndex = ref(-1)
const typeahead = ref('')
let typeaheadTimer: ReturnType<typeof setTimeout> | undefined

const selectedValues = computed(() => {
  const values = Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
  return values.filter((value): value is string => typeof value === 'string'
    && (props.multiple ? value !== '' : value !== '' || props.emptyOptionSelected)
    && (props.multiple ? true : props.options.some(option => option.value === value)))
})
const selectedOptions = computed(() => props.options.filter(option => selectedValues.value.includes(option.value)))
const filteredOptions = computed(() => {
  const needle = query.value.trim().toLocaleLowerCase()
  return props.options.filter(option => !needle || option.label.toLocaleLowerCase().includes(needle))
})
const activeOptionId = computed(() => activeIndex.value >= 0 ? `${props.id}-option-${activeIndex.value}` : undefined)
defineExpose({ focus: () => trigger.value?.focus() })

function firstEnabledIndex() {
  return filteredOptions.value.findIndex(option => !option.disabled)
}

function selectedIndex() {
  const index = filteredOptions.value.findIndex(option => selectedValues.value.includes(option.value) && !option.disabled)
  return index >= 0 ? index : firstEnabledIndex()
}

function openMenu() {
  if (props.disabled) return
  open.value = true
  activeIndex.value = selectedIndex()
  nextTick(() => {
    if (props.searchable) searchInput.value?.focus()
    else trigger.value?.focus()
  })
}

function closeMenu(restoreFocus = false) {
  open.value = false
  query.value = ''
  if (restoreFocus) nextTick(() => trigger.value?.focus())
}

function moveActive(direction: 1 | -1) {
  const enabled = filteredOptions.value
    .map((option, index) => option.disabled ? -1 : index)
    .filter(index => index >= 0)
  if (!enabled.length) { activeIndex.value = -1; return }
  const current = enabled.indexOf(activeIndex.value)
  activeIndex.value = current < 0
    ? (direction > 0 ? enabled[0] : enabled[enabled.length - 1])
    : enabled[(current + direction + enabled.length) % enabled.length]
}

function choose(option: Option) {
  if (option.disabled) return
  if (props.multiple) {
    const values = new Set(selectedValues.value)
    values.has(option.value) ? values.delete(option.value) : values.add(option.value)
    emit('update:modelValue', [...values])
    return
  }
  emit('update:modelValue', option.value)
  closeMenu(true)
}

function chooseActive() {
  const option = filteredOptions.value[activeIndex.value]
  if (option) choose(option)
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!open.value) openMenu()
    else moveActive(1)
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!open.value) openMenu()
    else moveActive(-1)
  } else if (event.key === 'Home' && open.value) {
    event.preventDefault()
    activeIndex.value = firstEnabledIndex()
  } else if (event.key === 'End' && open.value) {
    event.preventDefault()
    const reversedIndex = [...filteredOptions.value].reverse().findIndex(option => !option.disabled)
    activeIndex.value = reversedIndex < 0 ? -1 : filteredOptions.value.length - reversedIndex - 1
  } else if ((event.key === 'Enter' || event.key === ' ') && open.value && !props.searchable) {
    event.preventDefault()
    chooseActive()
  } else if (event.key === 'Escape' && open.value) {
    event.preventDefault()
    closeMenu(true)
  } else if (event.key === 'Tab' && open.value) {
    closeMenu()
  } else if (!props.searchable && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
    typeahead.value += event.key.toLocaleLowerCase()
    if (typeaheadTimer) clearTimeout(typeaheadTimer)
    typeaheadTimer = setTimeout(() => { typeahead.value = '' }, 500)
    const matchIndex = filteredOptions.value.findIndex(option => !option.disabled && option.label.toLocaleLowerCase().startsWith(typeahead.value))
    if (matchIndex >= 0) {
      activeIndex.value = matchIndex
      if (!open.value) { open.value = true; nextTick(() => trigger.value?.focus()) }
      else if (!props.multiple) choose(filteredOptions.value[matchIndex])
    }
  }
}

function handleOutsidePointer(event: PointerEvent) {
  if (root.value && !root.value.contains(event.target as Node)) closeMenu()
}

function handleSearchKeydown(event: KeyboardEvent) {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault()
    moveActive(event.key === 'ArrowDown' ? 1 : -1)
  } else if (event.key === 'Enter') {
    event.preventDefault()
    chooseActive()
  } else if (event.key === 'Escape') {
    event.preventDefault()
    closeMenu(true)
  } else if (event.key === 'Tab') {
    closeMenu()
  }
}

watch(query, () => { activeIndex.value = firstEnabledIndex() })
watch(activeIndex, () => {
  if (!open.value || activeIndex.value < 0) return
  nextTick(() => document.getElementById(activeOptionId.value || '')?.scrollIntoView({ block: 'nearest' }))
})
onMounted(() => document.addEventListener('pointerdown', handleOutsidePointer))
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handleOutsidePointer)
  if (typeaheadTimer) clearTimeout(typeaheadTimer)
})
</script>

<template>
  <div ref="root" class="relative min-w-0">
    <label v-if="!externalLabel" :for="id" class="mb-2 block text-sm font-medium text-gray-700">{{ label }}</label>
    <button
      :id="id"
      ref="trigger"
      v-bind="controlAttrs"
      type="button"
      :role="searchable ? undefined : 'combobox'"
      :disabled="disabled"
      :aria-label="String(controlAttrs['aria-label'] || label)"
      aria-haspopup="listbox"
      :aria-required="required || undefined"
      :aria-expanded="open"
      :aria-controls="`${id}-listbox`"
      :aria-activedescendant="open && !searchable ? activeOptionId : undefined"
      :aria-invalid="controlAttrs['aria-invalid'] || (error ? 'true' : undefined)"
      :aria-describedby="controlAttrs['aria-describedby']"
      class="group flex h-11 w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-3.5 text-left text-sm text-gray-900 shadow-sm outline-none transition-colors hover:border-gray-400 focus-visible:border-blue-600 focus-visible:ring-4 focus-visible:ring-blue-600/15 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 aria-[invalid=true]:border-red-500 aria-[invalid=true]:focus-visible:ring-red-500/15 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:hover:border-gray-600 dark:focus-visible:ring-blue-400/20 dark:disabled:bg-gray-800 dark:disabled:text-gray-400"
      @click="open ? closeMenu() : openMenu()"
      @keydown="handleKeydown"
    >
      <span v-if="!selectedOptions.length" class="min-w-0 flex-1 truncate text-gray-500 dark:text-gray-400">{{ placeholder }}</span>
      <span v-else-if="multiple" class="flex min-w-0 flex-1 flex-wrap gap-1.5 py-1">
        <span v-for="option in selectedOptions" :key="option.value" class="max-w-full truncate rounded-md bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-300 dark:ring-blue-300/20">{{ option.label }}</span>
      </span>
      <span v-else class="min-w-0 flex-1 truncate text-gray-900 dark:text-gray-100">{{ selectedOptions[0]?.label }}</span>
      <svg class="size-4 shrink-0 text-gray-500 transition-transform group-aria-expanded:rotate-180 group-focus-visible:text-blue-700 dark:text-gray-400 dark:group-focus-visible:text-blue-300" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="m5.5 7.5 4.5 4.5 4.5-4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </button>

    <div v-if="open" class="absolute inset-x-0 top-full z-50 mt-1.5 max-h-72 min-w-0 overflow-hidden rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-900 dark:ring-white/10">
      <input
        v-if="searchable"
        ref="searchInput"
        v-model="query"
        type="search"
        role="combobox"
        :aria-label="searchPlaceholder"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded="true"
        :aria-controls="`${id}-listbox`"
        :aria-activedescendant="activeOptionId"
        class="mb-1.5 h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-900 outline-none placeholder:text-gray-500 focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-400/15"
        :placeholder="searchPlaceholder"
        @keydown="handleSearchKeydown"
      />
      <div :id="`${id}-listbox`" role="listbox" :aria-multiselectable="multiple || undefined" :aria-label="label" class="max-h-60 overflow-y-auto overscroll-contain">
        <div
          v-for="(option, index) in filteredOptions"
          :id="`${id}-option-${index}`"
          :key="option.value"
          role="option"
          :aria-selected="selectedValues.includes(option.value)"
          :aria-disabled="option.disabled || undefined"
          class="flex min-h-10 cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-sm text-gray-800 outline-none transition-colors hover:bg-blue-50 hover:text-blue-900 aria-[selected=true]:font-medium aria-[selected=true]:text-blue-700 aria-[disabled=true]:pointer-events-none aria-[disabled=true]:opacity-45 dark:text-gray-200 dark:hover:bg-blue-400/10 dark:hover:text-blue-100 dark:aria-[selected=true]:text-blue-300"
          :class="{ 'bg-blue-50 text-blue-900 dark:bg-blue-400/10 dark:text-blue-100': activeIndex === index }"
          @mouseenter="!option.disabled && (activeIndex = index)"
          @click="choose(option)"
        >
          <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
          <svg v-if="selectedValues.includes(option.value)" class="size-4 shrink-0 text-blue-700 dark:text-blue-300" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="m5 10 3.2 3.2L15 6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <p v-if="!filteredOptions.length" class="px-3 py-3 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma opção encontrada.</p>
      </div>
    </div>
    <p v-if="error" class="mt-1.5 text-sm text-red-600 dark:text-red-400" role="alert">{{ error }}</p>
  </div>
</template>
