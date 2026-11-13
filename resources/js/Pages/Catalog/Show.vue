<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import { catalogTypeLabel } from '../../utils/displayLabels'
import { measurementUnits } from '../../utils/measurementUnits'

type CatalogItem = {
  name: string
  type: string
  description?: string | null
  unit: string
  default_unit_price_cents?: number | null
  default_cost_cents?: number | null
  active: boolean
}

const props = defineProps<{ item: CatalogItem }>()
const unitLabel = computed(() => measurementUnits.find((unit) => unit.value === props.item.unit)?.label || props.item.unit)
const money = (value?: number | null) => value == null ? '—' : `R$ ${(value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
</script>

<template>
  <AppShell title="Visualizar item" active="catalog">
    <Head :title="props.item.name" />
    <div class="page-content min-w-0 p-4 sm:p-6 2xl:p-11">
      <PageHeader :title="props.item.name" description="Dados registrados no catálogo." back-href="/catalog" back-label="Voltar ao catálogo" />
      <section class="min-w-0 rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm sm:p-6 dark:border-gray-800 dark:bg-gray-900" aria-label="Dados do item">
        <dl class="grid min-w-0 grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
          <div class="min-w-0"><dt class="text-sm text-gray-500 dark:text-gray-400">Tipo</dt><dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ catalogTypeLabel(props.item.type) }}</dd></div>
          <div class="min-w-0"><dt class="text-sm text-gray-500 dark:text-gray-400">Situação</dt><dd class="mt-1"><StatusBadge :value="props.item.active ? 'active' : 'inactive'" /></dd></div>
          <div class="min-w-0"><dt class="text-sm text-gray-500 dark:text-gray-400">Unidade de medida</dt><dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ unitLabel }}</dd></div>
          <div class="min-w-0"><dt class="text-sm text-gray-500 dark:text-gray-400">Preço padrão</dt><dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ money(props.item.default_unit_price_cents) }}</dd></div>
          <div class="min-w-0"><dt class="text-sm text-gray-500 dark:text-gray-400">Custo padrão</dt><dd class="mt-1 font-medium text-gray-800 dark:text-white/90">{{ money(props.item.default_cost_cents) }}</dd></div>
          <div class="min-w-0 sm:col-span-2"><dt class="text-sm text-gray-500 dark:text-gray-400">Descrição</dt><dd class="mt-1 whitespace-pre-wrap break-words text-gray-800 dark:text-white/90">{{ props.item.description || 'Sem descrição' }}</dd></div>
        </dl>
      </section>
    </div>
  </AppShell>
</template>
