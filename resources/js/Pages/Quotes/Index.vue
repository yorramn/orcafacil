<script setup lang="ts">
import { formatDateBR } from '../../utils/dateDisplay'
import { computed, onBeforeUnmount, shallowRef } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ResourceList from '../../Components/ResourceList.vue'
import RecordActionsMenu from '../../Components/RecordActionsMenu.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
const rawProps = withDefaults(defineProps<{ quotes?: any[] | { data?: any[]; links?: any[]; from?: number; to?: number; total?: number }; loading?: boolean; error?: boolean; status?: string; search?: string; focus?: number; sort?: string; direction?: 'asc' | 'desc' }>(), { quotes: () => [], loading: false, error: false, status: '', search: '', focus: 0, sort: 'created_at', direction: 'desc' })
const props = computed(() => ({ ...rawProps, quotes: normalize(rawProps.quotes) }))
const appliedStatus = shallowRef(rawProps.status || '')
const search = shallowRef(rawProps.search || '')
const sort = shallowRef(rawProps.sort || 'created_at')
const direction = shallowRef<'asc' | 'desc'>(rawProps.direction || 'desc')
const copyStatus = shallowRef('')
let copyTimer: ReturnType<typeof setTimeout> | undefined
function showCopyStatus(message: string) { copyStatus.value = message; if (copyTimer) clearTimeout(copyTimer); copyTimer = setTimeout(() => { copyStatus.value = '' }, 5000) }
let searchTimer: ReturnType<typeof setTimeout> | undefined
function applyFilters() { if (searchTimer) clearTimeout(searchTimer); router.get('/quotes', { search: search.value || undefined, status: appliedStatus.value || undefined, sort: sort.value, direction: direction.value }, { preserveState: true, preserveScroll: true, replace: true }) }
function searchChanged(value: string) { search.value = value; if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350) }
function statusChanged(value: string) { appliedStatus.value = value; applyFilters() }
function sortChanged(value: { sort: string; direction: 'asc' | 'desc' }) { sort.value = value.sort; direction.value = value.direction; applyFilters() }
function clearFilters() { search.value = ''; appliedStatus.value = ''; applyFilters() }
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer); if (copyTimer) clearTimeout(copyTimer) })
function normalize(value: any) { return Array.isArray(value) ? value : Object.assign([...(value?.data || [])], value || {}) }
function money(cents?: number) { return cents == null ? '—' : `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
function actionsFor(quote: any) {
  const actions: Array<{ key: 'view' | 'edit' | 'versions' | 'copy' | 'download' | 'service'; label: string; href?: string; download?: boolean; disabled?: boolean }> = [
    { key: 'view', label: 'Visualizar', href: `/quotes/${quote.id}` },
  ]
  if (quote.status === 'draft') actions.push({ key: 'edit', label: 'Editar', href: `/quotes/${quote.id}/edit` })
  if (quote.status === 'approved') actions.push(quote.service?.id
    ? { key: 'service', label: 'Ver Ordem de Serviço', href: `/services/${quote.service.id}` }
    : { key: 'service', label: 'Criar Ordem de Serviço', href: `/services/create?quote=${quote.id}` })
  actions.push({ key: 'download', label: 'Baixar orçamento', href: `/quotes/${quote.id}/download`, download: true })
  actions.push({ key: 'copy', label: quote.public_link_legacy ? 'Copiar link (renove primeiro)' : 'Copiar link público', disabled: Boolean(quote.public_link_legacy) })
  actions.push({ key: 'versions', label: 'Versões e link', href: `/quotes/${quote.id}/versions` })
  return actions
}
async function copyUrl(url: string) {
  try { await navigator.clipboard.writeText(url); showCopyStatus('Link público copiado. Você pode copiá-lo novamente pelo menu.') }
  catch { showCopyStatus('Não foi possível copiar o link.') }
}
function selectAction(key: string, quote: any) {
  if (key !== 'copy') return
  if (quote.public_url) { void copyUrl(quote.public_url); return }
  router.post(`/quotes/${quote.id}/public-link`, {}, { preserveScroll: true, onSuccess: (page) => {
    const data = (page.props.quotes as any)?.data || []
    const updated = data.find((item: any) => item.id === quote.id)
    if (updated?.public_url) void copyUrl(updated.public_url)
    else showCopyStatus('Link criado. Abra o orçamento para copiá-lo.')
  }, onError: () => { showCopyStatus('Não foi possível criar o link público.') } })
}
</script>
<template>
  <ResourceList title="Orçamentos" active="quotes" description="Acompanhe cada proposta do rascunho à aprovação e mantenha os próximos passos claros." :items="props.quotes" :loading="props.loading" :error="props.error" searchable :search="search" :status="appliedStatus" :status-options="[{ value: '', label: 'Todos os orçamentos' }, { value: 'draft', label: 'Rascunho' }, { value: 'sent', label: 'Enviado' }, { value: 'viewed', label: 'Visualizado' }, { value: 'approved', label: 'Aprovado' }, { value: 'rejected', label: 'Recusado' }, { value: 'expired', label: 'Expirado' }, { value: 'canceled', label: 'Cancelado' }]" :sort="sort" :sort-direction="direction" :sort-options="[{ value: 'created_at', label: 'Data de criação' }, { value: 'number', label: 'Número' }, { value: 'total_cents', label: 'Valor total' }, { value: 'valid_until', label: 'Validade' }]" :filter-active="Boolean(appliedStatus || search || rawProps.focus)" create-href="/quotes/create" create-label="Novo orçamento" empty-title="Crie seu primeiro orçamento" @search="searchChanged" @status-change="statusChanged" @sort-change="sortChanged" @clear="clearFilters" @filter="applyFilters">
    <template #table>
      <thead><tr><th>Número</th><th>Cliente</th><th>Total</th><th>Status</th><th>Validade</th><th><span class="sr-only">Ações</span></th></tr></thead>
      <tbody><tr v-for="quote in props.quotes" :key="quote.id">
        <td><Link class="font-medium text-gray-800 hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-white/90" :href="`/quotes/${quote.id}`">{{ quote.display_number || 'Sem número' }}</Link><span>{{ quote.title || 'Sem título' }}</span></td>
        <td>{{ quote.client?.name || 'Cliente não informado' }}</td>
        <td>{{ money(quote.total_cents) }}</td><td><StatusBadge :value="quote.status" /></td><td>{{ formatDateBR(quote.valid_until) }}</td>
        <td><RecordActionsMenu :label="`Ações para orçamento ${quote.display_number || quote.id}`" :actions="actionsFor(quote)" @select="(key) => selectAction(key, quote)" /></td>
      </tr></tbody>
    </template>
    <template #cards>
      <article v-for="quote in props.quotes" :key="quote.id" class="record-card flex min-w-0 flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm [&_h3]:text-base [&_h3]:font-semibold [&_h3]:leading-6 [&_h3]:text-td-ink [&_dl]:grid [&_dl]:grid-cols-2 [&_dl]:gap-4 [&_dt]:text-sm [&_dt]:text-td-body [&_dd]:mt-1 [&_dd]:font-medium [&_dd]:text-td-ink">
        <div class="record-card__top flex min-w-0 flex-wrap items-start justify-between gap-3 [&>div]:min-w-0 [&_span]:text-sm [&_span]:leading-5"><div><h3><Link class="hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" :href="`/quotes/${quote.id}`">{{ quote.display_number || 'Sem número' }}</Link></h3><span>{{ quote.title || 'Sem título' }}</span></div><StatusBadge :value="quote.status" /></div>
        <p class="text-sm text-gray-600"><span class="font-medium text-gray-800">Cliente:</span> {{ quote.client?.name || 'Não informado' }}</p>
        <dl><div><dt>Total</dt><dd>{{ money(quote.total_cents) }}</dd></div><div><dt>Validade</dt><dd>{{ formatDateBR(quote.valid_until) }}</dd></div></dl>
        <div class="record-card__actions flex justify-end border-t border-gray-100 pt-3 dark:border-gray-800"><RecordActionsMenu :label="`Ações para orçamento ${quote.display_number || quote.id}`" :actions="actionsFor(quote)" @select="(key) => selectAction(key, quote)" /></div>
      </article>
    </template>
  </ResourceList>
  <p v-if="copyStatus" class="pointer-events-none fixed right-4 top-4 z-[90] max-w-sm rounded-lg border border-td-border bg-white px-4 py-3 text-sm text-td-ink shadow-theme-lg" role="status">{{ copyStatus }}</p>
</template>
