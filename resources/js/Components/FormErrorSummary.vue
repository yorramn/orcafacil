<script setup lang="ts">
import { computed, nextTick, useTemplateRef, watch } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline'

type FieldErrors = Record<string, string | string[] | undefined>

const props = defineProps<{ errors: FieldErrors }>()
const summaryRef = useTemplateRef<HTMLDivElement>('summary')

const items = computed(() =>
  Object.entries(props.errors)
    .flatMap(([field, message]) => {
      const messages = Array.isArray(message) ? message : [message]
      return messages.filter((value): value is string => Boolean(value)).map((text, index) => ({
        field,
        text,
        id: field + '-error-' + index,
      }))
    }),
)

async function focusField(field: string) {
  await nextTick()
  const control = resolveControl(field)
  control?.scrollIntoView({ block: 'center' })
  control?.focus()
}

const fieldAliases: Record<string, string[]> = {
  default_unit_price_cents: ['item-price'],
  default_cost_cents: ['item-cost'],
  scheduled_start: ['service-start'],
  scheduled_end: ['service-end'],
  quote_id: ['service-quote'],
  current_password: ['delete-password'],
}
const controlSelector = 'input:not([type="hidden"]), select, textarea, button, [tabindex]:not([tabindex="-1"])'

function resolveControl(field: string): HTMLElement | null {
  const candidates: HTMLElement[] = []
  const direct = document.getElementById(field)
  if (direct) candidates.push(direct)

  const dynamicItem = field.match(/^items\.(\d+)\.(.+)$/)
  if (dynamicItem) {
    const [, index, key] = dynamicItem
    const itemField = ({ unit_price_cents: 'price', discount_cents: 'discount', catalog_item_id: 'catalog' } as Record<string, string>)[key] || key.replaceAll('_', '-')
    candidates.push(...Array.from(document.querySelectorAll<HTMLElement>('[id]')).filter((element) =>
      element.id === `item-${itemField}-${index}` || element.id === `edit-item-${itemField}-${index}`,
    ))
  }

  for (const id of fieldAliases[field] || []) {
    const target = document.getElementById(id)
    if (target) candidates.push(target)
  }

  const dataTarget = Array.from(document.querySelectorAll<HTMLElement>('[data-error-field]'))
    .find((element) => element.dataset.errorField === field)
  if (dataTarget) candidates.push(dataTarget)

  const normalized = field.replaceAll('_', '-')
  candidates.push(...Array.from(document.querySelectorAll<HTMLElement>('[id]')).filter((element) =>
    element.id.endsWith(`-${normalized}`),
  ))

  for (const candidate of candidates) {
    if (candidate.matches(controlSelector)) return candidate
    const childControl = candidate.querySelector<HTMLElement>(controlSelector)
    if (childControl) return childControl
  }

  return null
}

function fieldHref(field: string) {
  const control = resolveControl(field)
  return `#${control?.id || field}`
}

function focusSummary() {
  summaryRef.value?.focus()
}

watch(items, async (value) => {
  if (!value.length) return
  await nextTick()
  focusSummary()
})

defineExpose({ focusSummary })
</script>

<template>
  <div v-if="items.length" ref="summary" class="error-summary mb-6 flex gap-3 rounded-md border border-td-red/30 bg-td-red/5 p-4 text-sm leading-6 text-td-red focus-visible:outline-2 focus-visible:outline-td-red [&>svg]:mt-0.5 [&>svg]:size-5 [&>svg]:shrink-0 [&_ul]:mt-2 [&_ul]:list-inside [&_ul]:list-disc [&_a]:underline [&_a:focus-visible]:outline-2 [&_a:focus-visible]:outline-td-red" data-error-summary tabindex="-1" role="alert" aria-labelledby="error-summary-title">
    <ExclamationTriangleIcon aria-hidden="true" />
    <div>
      <strong id="error-summary-title">Confira os dados informados.</strong>
      <ul>
        <li v-for="item in items" :key="item.id">
          <a :href="fieldHref(item.field)" @click.prevent="focusField(item.field)">{{ item.text }}</a>
        </li>
      </ul>
    </div>
  </div>
</template>
