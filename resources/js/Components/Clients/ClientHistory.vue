<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { BanknotesIcon, ChevronRightIcon, DocumentTextIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import StatusBadge from '../StatusBadge.vue'
import { formatDateBR } from '../../utils/dateDisplay'

type RecordItem = {
  id: number
  title?: string
  description?: string
  display_number?: string
  status?: string
  created_at?: string
  sent_at?: string
  updated_at?: string
  scheduled_start?: string
  completed_at?: string
  paid_at?: string
  due_date?: string
  total_cents?: number
  contracted_total_cents?: number
  amount_cents?: number
}
type Page = {
  data: RecordItem[]
  total: number
  current_page: number
  last_page: number
  prev_page_url: string | null
  next_page_url: string | null
}
type Tab = 'quotes' | 'services' | 'receivables'
type Entry = { id: number; title: string; subtitle: string; status: string; date: string | null; amount: number | null; href: string | null }

const props = defineProps<{ clientId: number; tab: Tab; quotes: Page; services: Page; receivables: Page }>()
const tabs: { id: Tab; label: string; singular: string; icon: typeof DocumentTextIcon }[] = [
  { id: 'quotes', label: 'Orçamentos', singular: 'orçamento', icon: DocumentTextIcon },
  { id: 'services', label: 'Serviços', singular: 'serviço', icon: WrenchScrewdriverIcon },
  { id: 'receivables', label: 'Recebimentos', singular: 'recebimento', icon: BanknotesIcon },
]
const pages = computed(() => ({ quotes: props.quotes, services: props.services, receivables: props.receivables }))
const currentPage = computed(() => pages.value[props.tab])
const activeTab = computed(() => tabs.find(tab => tab.id === props.tab) || tabs[0])
const entries = computed<Entry[]>(() => currentPage.value.data.map(item => {
  if (props.tab === 'quotes') return {
    id: item.id, title: item.display_number ? `Orçamento ${item.display_number}` : 'Orçamento',
    subtitle: item.title || 'Orçamento do cliente', status: item.status || 'draft',
    date: item.created_at || item.sent_at || item.updated_at || null,
    amount: item.total_cents ?? null, href: `/quotes/${item.id}`,
  }
  if (props.tab === 'services') return {
    id: item.id, title: item.title || 'Serviço', subtitle: item.description || 'Serviço contratado',
    status: item.status || 'planned', date: item.scheduled_start || item.completed_at || item.created_at || null,
    amount: item.contracted_total_cents ?? null, href: `/services/${item.id}`,
  }
  return {
    id: item.id, title: item.description || 'Recebimento',
    subtitle: item.due_date ? `Vencimento: ${formatDateBR(item.due_date)}` : 'Recebimento do cliente',
    status: item.status || 'pending', date: item.paid_at || item.due_date || item.created_at || null,
    amount: item.amount_cents ?? null, href: null,
  }
}))
const money = (cents: number | null) => cents === null ? null : `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
</script>

<template>
  <section class="panel min-w-0 rounded-xl border border-td-border bg-white p-5 shadow-td-card sm:p-6" aria-labelledby="client-history-title">
    <div class="mb-5">
      <h2 id="client-history-title" class="text-lg font-semibold leading-7 text-td-ink">Histórico do cliente</h2>
      <p class="mt-1 text-sm leading-5 text-td-body">Consulte orçamentos, serviços e recebimentos deste cliente.</p>
    </div>

    <nav class="flex gap-1 overflow-x-auto border-b border-td-border" aria-label="Categorias do histórico">
      <Link v-for="item in tabs" :key="item.id" :href="`/clients/${clientId}?tab=${item.id}`" :aria-current="tab === item.id ? 'page' : undefined" class="inline-flex min-h-11 shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-td-primary" :class="tab === item.id ? 'border-td-primary text-td-primary' : 'border-transparent text-td-body hover:border-td-border hover:text-td-ink'">
        <component :is="item.icon" class="size-4" aria-hidden="true" />{{ item.label }}
        <span class="rounded-full px-2 py-0.5 text-xs tabular-nums" :class="tab === item.id ? 'bg-td-primary/10 text-td-primary' : 'bg-td-soft text-td-body'">{{ pages[item.id].total }}</span>
      </Link>
    </nav>

    <div v-if="!entries.length" class="mt-5 flex min-w-0 flex-col gap-4 rounded-xl bg-td-soft/60 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
      <div class="flex min-w-0 items-start gap-3">
        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-td-primary shadow-sm"><component :is="activeTab.icon" class="size-5" aria-hidden="true" /></span>
        <div class="min-w-0"><strong class="block font-medium text-td-ink">Nenhum {{ activeTab.singular }} encontrado</strong><p class="mt-1 text-sm leading-5 text-td-body">Não há registros nesta categoria para este cliente.</p></div>
      </div>
      <Link v-if="tab === 'quotes'" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/quotes/create">Criar orçamento</Link>
    </div>

    <ol v-else class="divide-y divide-td-border" :aria-label="`${activeTab.label} do cliente`">
      <li v-for="entry in entries" :key="entry.id" class="py-4 last:pb-0">
        <component :is="entry.href ? Link : 'div'" :href="entry.href || undefined" class="group -mx-2 flex min-w-0 flex-wrap items-start justify-between gap-3 rounded-lg px-2 py-2" :class="entry.href ? 'hover:bg-td-soft/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary' : ''">
          <span class="flex min-w-0 flex-1 items-start gap-3">
            <component :is="activeTab.icon" class="mt-0.5 size-5 shrink-0 text-td-primary" aria-hidden="true" />
            <span class="min-w-0"><span class="block break-words font-medium text-td-ink" :class="entry.href ? 'group-hover:text-td-primary' : ''">{{ entry.title }}</span><span class="mt-1 block break-words text-sm leading-5 text-td-body">{{ entry.subtitle }}</span><span class="mt-2 block text-xs leading-5 text-td-body">{{ formatDateBR(entry.date, 'Data não informada') }}</span></span>
          </span>
          <span class="flex shrink-0 items-center gap-2"><span v-if="money(entry.amount)" class="text-sm font-medium tabular-nums text-td-ink">{{ money(entry.amount) }}</span><StatusBadge :value="entry.status" /><ChevronRightIcon v-if="entry.href" class="size-4 shrink-0 text-td-body group-hover:text-td-primary" aria-hidden="true" /></span>
        </component>
      </li>
    </ol>

    <div v-if="currentPage.last_page > 1" class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-td-border pt-4 text-sm">
      <span class="text-td-body">Página {{ currentPage.current_page }} de {{ currentPage.last_page }}</span>
      <div class="flex gap-2">
        <Link v-if="currentPage.prev_page_url" :href="currentPage.prev_page_url" class="inline-flex min-h-10 items-center rounded-lg border border-td-border px-3 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary">Anterior</Link>
        <Link v-if="currentPage.next_page_url" :href="currentPage.next_page_url" class="inline-flex min-h-10 items-center rounded-lg border border-td-border px-3 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary">Próxima</Link>
      </div>
    </div>
  </section>
</template>
