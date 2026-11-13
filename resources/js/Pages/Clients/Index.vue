<script setup lang="ts">
import { onBeforeUnmount, shallowRef } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import ResourceList from '../../Components/ResourceList.vue'
import RecordActionsMenu from '../../Components/RecordActionsMenu.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
type Client = { id: string | number; name: string; kind?: string; status?: string; deleted_at?: string | null; phone?: string; whatsapp?: string; email?: string; last_activity_at?: string }
type Paginator = { data?: Client[]; links?: Array<{ url: string | null; label: string; active: boolean }>; from?: number | null; to?: number | null; total?: number }
const props = withDefaults(defineProps<{ clients?: Client[] | Paginator; canManage?: boolean; loading?: boolean; error?: boolean; search?: string; focus?: number; status?: string; sort?: string; direction?: 'asc' | 'desc' }>(), { clients: () => [], canManage: false, loading: false, error: false, search: '', focus: 0, status: '', sort: 'created_at', direction: 'desc' })
const archiveForm = useForm({})
const selectedForArchive = shallowRef<Client | null>(null)
const listLoading = shallowRef(false)
const listError = shallowRef(false)
const search = shallowRef(props.search || '')
const status = shallowRef(props.status || '')
const sort = shallowRef(props.sort || 'created_at')
const direction = shallowRef<'asc' | 'desc'>(props.direction || 'desc')
let searchTimer: ReturnType<typeof setTimeout> | undefined
const clients = () => Array.isArray(props.clients) ? props.clients : Object.assign([...(props.clients?.data || [])], props.clients || {})
function requestList() { if (searchTimer) clearTimeout(searchTimer); listLoading.value = true; listError.value = false; router.get('/clients', { search: search.value || undefined, status: status.value || undefined, sort: sort.value, direction: direction.value }, { preserveState: true, preserveScroll: true, replace: true, onError: () => { listError.value = true }, onFinish: () => { listLoading.value = false } }) }
function debouncedSearch() { if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(requestList, 350) }
function statusChanged(value: string) { status.value = value; requestList() }
function sortChanged(value: { sort: string; direction: 'asc' | 'desc' }) { sort.value = value.sort; direction.value = value.direction; requestList() }
function clearFilters() { search.value = ''; status.value = ''; requestList() }
function archive(client: Client) { if (!archiveForm.processing) selectedForArchive.value = client }
function confirmArchive() {
  if (!selectedForArchive.value || archiveForm.processing) return
  const id = selectedForArchive.value.id
  selectedForArchive.value = null
  archiveForm.delete(`/clients/${id}`)
}
function actionsFor(client: Client) {
  const actions: Array<{ key: 'view' | 'edit' | 'delete'; label: string; href?: string; danger?: boolean; disabled?: boolean }> = [
    { key: 'view', label: 'Visualizar', href: `/clients/${client.id}` },
  ]
  if (props.canManage && !client.deleted_at) {
    actions.push({ key: 'edit', label: 'Editar', href: `/clients/${client.id}/edit` })
    actions.push({ key: 'delete', label: 'Excluir', danger: true, disabled: archiveForm.processing })
  }
  return actions
}
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>
<template>
  <ResourceList title="Clientes" active="clients" description="Encontre pessoas e empresas e acompanhe todo o histórico de cada atendimento." :items="clients()" :loading="props.loading || listLoading" :error="props.error || listError" searchable :search="search" :status="status" :status-options="[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Ativos' }, { value: 'archived', label: 'Arquivados' }]" :sort="sort" :sort-direction="direction" :sort-options="[{ value: 'created_at', label: 'Data de cadastro' }, { value: 'name', label: 'Nome' }, { value: 'updated_at', label: 'Última atualização' }]" :filter-active="Boolean(status || search || props.focus)" create-href="/clients/create" create-label="Novo cliente" empty-title="Cadastre seu primeiro cliente" @search="(value) => { search = value; debouncedSearch() }" @status-change="statusChanged" @sort-change="sortChanged" @clear="clearFilters" @retry="requestList" @filter="requestList">
    <template #summary><p v-if="archiveForm.errors.client" class="error-summary my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-red/25 bg-td-red/5 p-4 text-sm leading-6 text-td-red [&_svg]:size-5 [&_svg]:shrink-0" role="alert">{{ archiveForm.errors.client }}</p></template>
    <template #table>
      <thead><tr><th scope="col">Cliente</th><th scope="col">Contato</th><th scope="col">Situação</th><th scope="col">Última atividade</th><th scope="col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody><tr v-for="client in clients()" :key="client.id">
        <td><Link class="font-medium text-gray-800 hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-white/90" :href="`/clients/${client.id}`">{{ client.name }}</Link><span>{{ client.kind === 'company' ? 'Pessoa jurídica' : 'Pessoa física' }}</span></td>
        <td>{{ client.phone || client.whatsapp || '—' }}<span>{{ client.email || 'Sem e-mail' }}</span></td>
        <td><StatusBadge :value="client.deleted_at ? 'archived' : (client.status || 'active')" /></td>
        <td>{{ client.last_activity_at || 'Sem atividade registrada' }}</td>
        <td><RecordActionsMenu :label="`Ações para ${client.name}`" :actions="actionsFor(client)" @select="(key) => { if (key === 'delete') archive(client) }" /></td>
      </tr></tbody>
    </template>
    <template #cards>
      <article v-for="client in clients()" :key="client.id" class="record-card min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm [&_h3]:text-base [&_h3]:font-medium [&_h3]:text-td-ink [&_dl]:mt-4 [&_dl]:grid [&_dl]:grid-cols-2 [&_dl]:gap-4 [&_dt]:text-sm [&_dt]:text-td-body [&_dd]:mt-1 [&_dd]:break-words [&_svg]:inline-block [&_svg]:size-5 [&_svg]:shrink-0 [&_strong]:font-medium [&_strong]:text-td-ink">
        <div class="record-card__top flex flex-wrap items-start justify-between gap-3 [&>div]:min-w-0 [&>div>span]:mt-1 [&>div>span]:block [&>div>span]:text-sm"><div><h3><Link class="hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" :href="`/clients/${client.id}`">{{ client.name }}</Link></h3><span>{{ client.email || client.phone || 'Sem contato informado' }}</span></div><StatusBadge :value="client.deleted_at ? 'archived' : (client.status || 'active')" /></div>
        <dl><div><dt>Tipo</dt><dd>{{ client.kind === 'company' ? 'Pessoa jurídica' : 'Pessoa física' }}</dd></div><div><dt>Atividade</dt><dd>{{ client.last_activity_at || '—' }}</dd></div></dl>
        <div class="record-card__actions mt-4 flex justify-end border-t border-gray-100 pt-3 dark:border-gray-800"><RecordActionsMenu :label="`Ações para ${client.name}`" :actions="actionsFor(client)" @select="(key) => { if (key === 'delete') archive(client) }" /></div>
      </article>
    </template>
  </ResourceList>
  <ConfirmModal :open="Boolean(selectedForArchive)" title="Excluir cliente" :message="`Deseja excluir ${selectedForArchive?.name || 'este cliente'}?`" :busy="archiveForm.processing" @cancel="selectedForArchive = null" @confirm="confirmArchive" />
</template>
