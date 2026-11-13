<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, useAttrs, useTemplateRef, watch } from 'vue'
import { CalendarDaysIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'
import { saoPauloToday } from '../utils/dateLimits'

defineOptions({ inheritAttrs: false })

const props = withDefaults(defineProps<{
  id: string
  modelValue?: string
  label?: string
  min?: string
  max?: string
  name?: string
  required?: boolean
  disabled?: boolean
}>(), { modelValue: '', required: false, disabled: false })

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
const root = useTemplateRef<HTMLDivElement>('root')
const trigger = useTemplateRef<HTMLButtonElement>('trigger')
const validation = useTemplateRef<HTMLInputElement>('validation')
const open = shallowRef(false)
const invalid = shallowRef(false)
const errorMessage = shallowRef('')
const viewedMonth = shallowRef(startOfMonth(parseIsoDate(props.modelValue) ?? new Date()))
const focusedDate = shallowRef(props.modelValue || todayIso())

const monthLabel = computed(() => new Intl.DateTimeFormat('pt-BR', { month: 'long', year: 'numeric' }).format(viewedMonth.value))
const displayValue = computed(() => {
  const date = parseIsoDate(props.modelValue)
  return date ? new Intl.DateTimeFormat('pt-BR').format(date) : ''
})
const calendarDays = computed(() => {
  const firstOfMonth = startOfMonth(viewedMonth.value)
  const offset = (firstOfMonth.getDay() + 6) % 7
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(firstOfMonth.getFullYear(), firstOfMonth.getMonth(), index - offset + 1)
    const iso = toIsoDate(date)
    return {
      date,
      iso,
      number: date.getDate(),
      inMonth: date.getMonth() === viewedMonth.value.getMonth(),
      selected: iso === props.modelValue,
      today: iso === todayIso(),
      disabled: isDateDisabled(iso),
    }
  })
})
const calendarWeeks = computed(() => Array.from({ length: 6 }, (_, week) => calendarDays.value.slice(week * 7, week * 7 + 7)))

const weekdays = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom']

function parseIsoDate(value?: string): Date | null {
  if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return null
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null
}

function toIsoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function todayIso(): string { return saoPauloToday() }
function startOfMonth(date: Date): Date { return new Date(date.getFullYear(), date.getMonth(), 1) }
function isDateDisabled(iso: string): boolean { return Boolean((props.min && iso < props.min) || (props.max && iso > props.max)) }

function initialFocusDate(): string {
  const selected = props.modelValue && !isDateDisabled(props.modelValue) ? props.modelValue : ''
  if (selected) return selected
  const today = todayIso()
  if (!isDateDisabled(today)) return today
  if (props.min && today < props.min) return props.min
  if (props.max) return props.max
  return today
}

function moveMonth(amount: number) {
  viewedMonth.value = new Date(viewedMonth.value.getFullYear(), viewedMonth.value.getMonth() + amount, 1)
}

function focusCalendarDay(iso: string) {
  if (isDateDisabled(iso)) return
  focusedDate.value = iso
  const date = parseIsoDate(iso)
  if (date) viewedMonth.value = startOfMonth(date)
  nextTick(() => root.value?.querySelector<HTMLButtonElement>(`[data-date="${iso}"]`)?.focus())
}

function toggleCalendar() {
  if (props.disabled) return
  open.value = !open.value
  if (open.value) {
    const initialDate = initialFocusDate()
    const initial = parseIsoDate(initialDate) ?? new Date()
    viewedMonth.value = startOfMonth(initial)
    focusedDate.value = initialDate
  }
}

function selectDate(iso: string) {
  emit('update:modelValue', iso)
  invalid.value = false
  errorMessage.value = ''
  if (validation.value) {
    validation.value.value = iso
    validation.value.dispatchEvent(new Event('change', { bubbles: true }))
  }
  open.value = false
  nextTick(() => trigger.value?.focus())
}

function handleInvalid() {
  invalid.value = true
  errorMessage.value = `O campo ${props.label || 'data'} é obrigatório.`
  nextTick(() => trigger.value?.focus())
}

function handlePointerDown(event: PointerEvent) {
  if (open.value && event.target instanceof Node && !root.value?.contains(event.target)) open.value = false
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    open.value = false
    trigger.value?.focus()
    return
  }
  if (!open.value) return

  const offsetByKey: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }
  if (event.key in offsetByKey) {
    event.preventDefault()
    const current = parseIsoDate(focusedDate.value) ?? new Date()
    current.setDate(current.getDate() + offsetByKey[event.key])
    focusCalendarDay(toIsoDate(current))
  } else if (event.key === 'Home' || event.key === 'End') {
    event.preventDefault()
    const current = parseIsoDate(focusedDate.value) ?? new Date()
    const mondayOffset = (current.getDay() + 6) % 7
    current.setDate(current.getDate() + (event.key === 'Home' ? -mondayOffset : 6 - mondayOffset))
    focusCalendarDay(toIsoDate(current))
  }
}

watch(() => props.modelValue, value => {
  if (value) {
    const date = parseIsoDate(value)
    if (date) viewedMonth.value = startOfMonth(date)
    invalid.value = false
    errorMessage.value = ''
  }
})

onMounted(() => {
  document.addEventListener('pointerdown', handlePointerDown)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handlePointerDown)
  document.removeEventListener('keydown', handleKeydown)
})

defineExpose({ focus: () => trigger.value?.focus() })
</script>

<template>
  <div ref="root" class="relative min-w-0">
    <button
      :id="id"
      ref="trigger"
      v-bind="attrs"
      type="button"
      :disabled="disabled"
      :aria-label="`${label || 'Data'}${displayValue ? `: ${displayValue}` : ''}`"
      aria-haspopup="dialog"
      :aria-expanded="open"
      :aria-required="required ? 'true' : undefined"
      :aria-invalid="invalid ? 'true' : attrs['aria-invalid']"
      :aria-describedby="errorMessage ? `${id}-required-error` : attrs['aria-describedby']"
      class="flex h-11 w-full min-w-0 items-center justify-between gap-3 rounded-lg border border-td-border bg-white px-3.5 text-left text-sm text-td-ink shadow-theme-xs outline-none transition-colors hover:border-td-primary/60 focus-visible:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 aria-[invalid=true]:border-td-red disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      @click="toggleCalendar"
    >
      <span :class="displayValue ? 'text-td-ink dark:text-white/90' : 'text-td-body/70'">{{ displayValue || 'dd/mm/aaaa' }}</span>
      <CalendarDaysIcon class="size-5 shrink-0 text-td-body" aria-hidden="true" />
    </button>

    <input
      ref="validation"
      class="sr-only"
      type="text"
      :value="modelValue"
      :required="required"
      :disabled="disabled"
      tabindex="-1"
      aria-hidden="true"
      @invalid.prevent="handleInvalid"
    />
    <input v-if="name" type="hidden" :name="name" :value="modelValue" :disabled="disabled" />

    <p v-if="errorMessage" :id="`${id}-required-error`" class="mt-1.5 text-sm text-td-red" role="alert">{{ errorMessage }}</p>

    <div
      v-if="open"
      class="absolute left-0 top-full z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-gray-200 bg-white p-4 text-td-ink shadow-xl dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
      role="dialog"
      :aria-label="`Escolher ${label || 'data'}`"
      @click.stop
    >
      <div class="mb-3 flex items-center justify-between gap-3">
        <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-lg text-td-body transition-colors hover:bg-td-soft hover:text-td-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary"
          aria-label="Mês anterior"
          @click="moveMonth(-1)"
        ><ChevronLeftIcon class="size-5" aria-hidden="true" /></button>
        <p class="text-sm font-semibold capitalize text-td-ink dark:text-white/90" aria-live="polite">{{ monthLabel }}</p>
        <button
          type="button"
          class="inline-flex size-9 items-center justify-center rounded-lg text-td-body transition-colors hover:bg-td-soft hover:text-td-ink focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary"
          aria-label="Próximo mês"
          @click="moveMonth(1)"
        ><ChevronRightIcon class="size-5" aria-hidden="true" /></button>
      </div>

      <div class="space-y-1 text-center" role="grid" :aria-label="monthLabel">
        <div class="grid grid-cols-7 gap-1" role="row">
          <span v-for="weekday in weekdays" :key="`label-${weekday}`" class="py-1.5 text-xs font-medium capitalize text-td-body" role="columnheader">{{ weekday }}</span>
        </div>
        <div v-for="(week, weekIndex) in calendarWeeks" :key="`week-${weekIndex}`" class="grid grid-cols-7 gap-1" role="row">
          <button
            v-for="day in week"
            :key="day.iso"
            :data-date="day.iso"
            type="button"
            role="gridcell"
            :tabindex="focusedDate === day.iso ? 0 : -1"
            :disabled="day.disabled"
            :aria-label="new Intl.DateTimeFormat('pt-BR', { dateStyle: 'full' }).format(day.date)"
            :aria-selected="day.selected"
            :class="[
              'inline-flex size-9 items-center justify-center rounded-lg text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary',
              day.selected ? 'bg-td-primary font-semibold text-white hover:bg-td-primary/90' : 'text-td-ink hover:bg-td-soft dark:text-gray-200 dark:hover:bg-white/10',
              !day.inMonth && !day.selected ? 'text-gray-400 dark:text-gray-600' : '',
              day.today && !day.selected ? 'font-semibold ring-1 ring-inset ring-td-primary/40' : '',
              day.disabled ? 'cursor-not-allowed opacity-35 hover:bg-transparent' : '',
            ]"
            @click="selectDate(day.iso)"
          >{{ day.number }}</button>
        </div>
      </div>

      <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
        <button type="button" :disabled="Boolean((min && todayIso() < min) || (max && todayIso() > max))" class="rounded-md px-2 py-1 text-sm font-medium text-td-primary hover:bg-td-primary/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary disabled:cursor-not-allowed disabled:opacity-40" @click="selectDate(todayIso())">Hoje</button>
        <button v-if="modelValue" type="button" class="rounded-md px-2 py-1 text-sm text-td-body hover:bg-td-soft focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary" @click="selectDate('')">Limpar data</button>
      </div>
    </div>
  </div>
</template>
