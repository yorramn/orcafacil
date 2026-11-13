<script setup lang="ts">
import { formatCalendarDateBR } from '../../utils/dateDisplay'
import { computed, onBeforeUnmount, shallowRef } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ResourceList from '../../Components/ResourceList.vue'
import RecordActionsMenu from '../../Components/RecordActionsMenu.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import { serviceStatusOptions } from '../../utils/displayLabels'
const rawProps = withDefaults(defineProps<{ services?: any[] | { data?: any[]; links?: any[]; from?: number; to?: number; total?: number }; loading?: boolean; error?: boolean; status?: string; search?: string; focus?: number; sort?: string; direction?: 'asc' | 'desc'; canManage?: boolean }>(), { services: () => [], loading: false, error: false, status: '', search: '', focus: 0, sort: 'created_at', direction: 'desc', canManage: false })
const props = computed(() => ({ ...rawProps, services: normalize(rawProps.services) }))
const appliedStatus = shallowRef(rawProps.status || '')
const search = shallowRef(rawProps.search || '')
const sort = shallowRef(rawProps.sort || 'created_at')
const direction = shallowRef<'asc' | 'desc'>(rawProps.direction || 'desc')
let searchTimer: ReturnType<typeof setTimeout> | undefined
function applyFilters() { if (searchTimer) clearTimeout(searchTimer); router.get('/services', { search: search.value || undefined, status: appliedStatus.value || undefined, sort: sort.value, direction: direction.value }, { preserveState: true, preserveScroll: true, replace: true }) }
function searchChanged(value: string) { search.value = value; if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350) }
function statusChanged(value: string) { appliedStatus.value = value; applyFilters() }
function sortChanged(value: { sort: string; direction: 'asc' | 'desc' }) { sort.value = value.sort; direction.value = value.direction; applyFilters() }
function clearFilters() { search.value = ''; appliedStatus.value = ''; applyFilters() }
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })
function normalize(value: any) { return Array.isArray(value) ? value : Object.assign([...(value?.data || [])], value || {}) }
function money(cents?: number) { return cents == null ? '—' : `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` }
function actionsFor(service: any) { return [{ key: 'view' as const, label: 'Visualizar', href: `/services/${service.id}` }, ...(rawProps.canManage ? [{ key: 'edit' as const, label: 'Editar', href: `/services/${service.id}/edit` }] : []), ...(service.canBill ? [{ key: 'service' as const, label: service.status === 'completed' ? 'Faturar serviço' : 'Concluir e faturar', href: `/services/${service.id}/billing` }] : [])] }
</script>
<template>
  <ResourceList title="Serviços" active="services" description="Transforme aprovações em execução e acompanhe status, prazo, responsável e valor contratado." :items="props.services" :loading="props.loading" :error="props.error" searchable :search="search" :status="appliedStatus" :status-options="[{ value: '', label: 'Todos os serviços' }, ...serviceStatusOptions]" :sort="sort" :sort-direction="direction" :sort-options="[{ value: 'created_at', label: 'Data de criação' }, { value: 'title', label: 'Título' }, { value: 'scheduled_end', label: 'Prazo' }, { value: 'contracted_total_cents', label: 'Valor contratado' }]" :filter-active="Boolean(appliedStatus || search || rawProps.focus)" create-href="/services/create" create-label="Novo serviço" empty-title="Nenhum serviço em andamento" @search="searchChanged" @status-change="statusChanged" @sort-change="sortChanged" @clear="clearFilters" @filter="applyFilters">
    <template #table>
      <thead><tr><th>Serviço</th><th>Cliente</th><th>Responsável</th><th>Status</th><th>Prazo</th><th><span class="sr-only">Ações</span></th></tr></thead>
      <tbody><tr v-for="service in props.services" :key="service.id">
        <td><Link class="font-medium text-gray-800 hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-white/90" :href="`/services/${service.id}`">{{ service.title }}</Link><span>{{ money(service.contracted_total_cents) }}</span></td>
        <td>{{ service.client?.name || service.client_name || '—' }}</td><td>{{ service.assigned_to?.name || service.assignee || 'Não atribuído' }}</td><td><StatusBadge :value="service.status" /></td><td>{{ formatCalendarDateBR(service.scheduled_end) }}</td>
        <td><RecordActionsMenu :label="`Ações para ${service.title}`" :actions="actionsFor(service)" /></td>
      </tr></tbody>
    </template>
    <template #cards>
      <article v-for="service in props.services" :key="service.id" class="record-card flex min-w-0 flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm [&_h3]:text-base [&_h3]:font-semibold [&_h3]:leading-6 [&_h3]:text-td-ink [&_dl]:grid [&_dl]:grid-cols-2 [&_dl]:gap-4 [&_dt]:text-sm [&_dt]:text-td-body [&_dd]:mt-1 [&_dd]:font-medium [&_dd]:text-td-ink">
        <div class="record-card__top flex min-w-0 flex-wrap items-start justify-between gap-3 [&>div]:min-w-0 [&_span]:text-sm [&_span]:leading-5"><div><h3><Link class="hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" :href="`/services/${service.id}`">{{ service.title }}</Link></h3><span>{{ service.client?.name || service.client_name || 'Cliente não informado' }}</span></div><StatusBadge :value="service.status" /></div>
        <dl><div><dt>Valor</dt><dd>{{ money(service.contracted_total_cents) }}</dd></div><div><dt>Responsável</dt><dd>{{ service.assigned_to?.name || service.assignee || 'Não atribuído' }}</dd></div><div><dt>Prazo</dt><dd>{{ formatCalendarDateBR(service.scheduled_end) }}</dd></div></dl>
        <div class="record-card__actions flex justify-end border-t border-gray-100 pt-3 dark:border-gray-800"><RecordActionsMenu :label="`Ações para ${service.title}`" :actions="actionsFor(service)" /></div>
      </article>
    </template>
  </ResourceList>
</template>

