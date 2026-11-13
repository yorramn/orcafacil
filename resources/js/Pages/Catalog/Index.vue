<script setup lang="ts">
import { computed, onBeforeUnmount, shallowRef } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import ResourceList from '../../Components/ResourceList.vue'
import RecordActionsMenu from '../../Components/RecordActionsMenu.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import { catalogTypeLabel } from '../../utils/displayLabels'

type CatalogItem = {
  id: string | number
  name: string
  description?: string | null
  type: string
  unit: string
  default_unit_price_cents?: number | null
  active?: boolean
}
type Paginator = CatalogItem[] & {
  data?: CatalogItem[]
  links?: Array<{ url: string | null; label: string; active: boolean }>
  from?: number | null
  to?: number | null
  total?: number
}

const rawProps = withDefaults(defineProps<{
  items?: CatalogItem[] | Paginator
  canManage?: boolean
  loading?: boolean
  error?: boolean
  active?: string
  search?: string
  focus?: number
  status?: string
  sort?: string
  direction?: 'asc' | 'desc'
}>(), { items: () => [], canManage: false, loading: false, error: false, active: '', search: '', focus: 0, status: '', sort: 'created_at', direction: 'desc' })

const props = computed(() => ({ ...rawProps, items: normalize(rawProps.items) }))
const status = shallowRef(rawProps.status || rawProps.active || '')
const search = shallowRef(rawProps.search || '')
const sort = shallowRef(rawProps.sort || 'created_at')
const direction = shallowRef<'asc' | 'desc'>(rawProps.direction || 'desc')
const selectedForDelete = shallowRef<CatalogItem | null>(null)
const deleteForm = useForm({})
let searchTimer: ReturnType<typeof setTimeout> | undefined

function normalize(value: CatalogItem[] | Paginator) {
  return Array.isArray(value) ? value : Object.assign([...(value?.data || [])], value || {})
}

function applyFilters() {
  if (searchTimer) clearTimeout(searchTimer)
  router.get('/catalog', {
    search: search.value || undefined,
    status: status.value || undefined,
    sort: sort.value,
    direction: direction.value,
  }, { preserveState: true, preserveScroll: true, replace: true })
}

function searchChanged(value: string) {
  search.value = value
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 350)
}

function statusChanged(value: string) { status.value = value; applyFilters() }
function sortChanged(value: { sort: string; direction: 'asc' | 'desc' }) { sort.value = value.sort; direction.value = value.direction; applyFilters() }
function clearFilters() { search.value = ''; status.value = ''; applyFilters() }
function deleteItem() {
  if (!selectedForDelete.value || deleteForm.processing) return
  const id = selectedForDelete.value.id
  selectedForDelete.value = null
  deleteForm.delete(`/catalog-items/${id}`)
}

onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })

function money(value?: number | null) {
  return value == null ? '—' : `R$ ${(value / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
}

function actionsFor(item: CatalogItem) {
  const actions: Array<{ key: 'view' | 'edit' | 'delete'; label: string; href?: string; danger?: boolean; disabled?: boolean }> = [
    { key: 'view', label: 'Visualizar', href: `/catalog/${item.id}` },
  ]
  if (rawProps.canManage) {
    actions.push({ key: 'edit', label: 'Editar', href: `/catalog/${item.id}/edit` })
    if (item.active !== false) actions.push({ key: 'delete', label: 'Excluir', danger: true, disabled: deleteForm.processing })
  }
  return actions
}
</script>

<template>
  <ResourceList
    title="Catálogo"
    active="catalog"
    description="Mantenha serviços, materiais e taxas com preços padrão para acelerar seus orçamentos."
    :items="props.items"
    :loading="props.loading"
    :error="props.error"
    searchable
    :search="search"
    :status="status"
    :status-options="[{ value: '', label: 'Todas as situações' }, { value: 'active', label: 'Ativos' }, { value: 'inactive', label: 'Inativos' }]"
    :sort="sort"
    :sort-direction="direction"
    :sort-options="[{ value: 'created_at', label: 'Data de cadastro' }, { value: 'name', label: 'Nome' }, { value: 'type', label: 'Tipo' }, { value: 'default_unit_price_cents', label: 'Preço padrão' }]"
    :filter-active="Boolean(status || search || rawProps.focus)"
    create-href="/catalog-items/create"
    create-label="Novo item"
    empty-title="Seu catálogo está vazio"
    @search="searchChanged"
    @status-change="statusChanged"
    @sort-change="sortChanged"
    @clear="clearFilters"
    @filter="applyFilters"
  >
    <template #table>
      <thead>
        <tr><th>Item</th><th>Tipo</th><th>Unidade</th><th>Preço padrão</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr>
      </thead>
      <tbody>
        <tr v-for="item in props.items" :key="item.id">
          <td><Link class="font-medium text-gray-800 hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500 dark:text-white/90" :href="`/catalog/${item.id}`">{{ item.name }}</Link><span>{{ item.description || 'Sem descrição' }}</span></td>
          <td>{{ catalogTypeLabel(item.type) }}</td>
          <td>{{ item.unit }}</td>
          <td>{{ money(item.default_unit_price_cents) }}</td>
          <td><StatusBadge :value="item.active === false ? 'inactive' : 'active'" /></td>
          <td>
            <RecordActionsMenu :label="`Ações para ${item.name}`" :actions="actionsFor(item)" @select="(key) => { if (key === 'delete') selectedForDelete = item }" />
          </td>
        </tr>
      </tbody>
    </template>

    <template #cards>
      <article v-for="item in props.items" :key="item.id" class="record-card min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm [&_h3]:text-base [&_h3]:font-medium [&_h3]:text-td-ink [&_dl]:mt-4 [&_dl]:grid [&_dl]:grid-cols-2 [&_dl]:gap-4 [&_dt]:text-sm [&_dt]:text-td-body [&_dd]:mt-1 [&_dd]:break-words [&_svg]:inline-block [&_svg]:size-5 [&_svg]:shrink-0 [&_strong]:font-medium [&_strong]:text-td-ink">
        <div class="record-card__top flex flex-wrap items-start justify-between gap-3 [&>div]:min-w-0 [&>div>span]:mt-1 [&>div>span]:block [&>div>span]:text-sm">
          <h3><Link class="hover:text-brand-500 hover:underline focus-visible:outline-2 focus-visible:outline-brand-500" :href="`/catalog/${item.id}`">{{ item.name }}</Link></h3>
          <StatusBadge :value="item.active === false ? 'inactive' : 'active'" />
        </div>
        <dl>
          <div><dt>Tipo</dt><dd>{{ catalogTypeLabel(item.type) }}</dd></div>
          <div><dt>Preço</dt><dd>{{ money(item.default_unit_price_cents) }}</dd></div>
        </dl>
        <div class="record-card__actions mt-4 flex justify-end border-t border-gray-100 pt-3 dark:border-gray-800"><RecordActionsMenu :label="`Ações para ${item.name}`" :actions="actionsFor(item)" @select="(key) => { if (key === 'delete') selectedForDelete = item }" /></div>
      </article>
    </template>
  </ResourceList>
  <ConfirmModal :open="Boolean(selectedForDelete)" title="Excluir item do catálogo" :message="`Deseja excluir ${selectedForDelete?.name || 'este item'}?`" :busy="deleteForm.processing" @cancel="selectedForDelete = null" @confirm="deleteItem" />
</template>
