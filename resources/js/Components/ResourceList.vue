<script setup lang="ts">
import { paginationLabel } from '../utils/pagination'
import { computed, nextTick, onBeforeUnmount, onMounted, shallowRef, useTemplateRef, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppShell from './AppShell.vue'
import PageHeader from './PageHeader.vue'
import DataState from './DataState.vue'
import FormSelect from './FormSelect.vue'
import TailAdminTable from '../TailAdminSource/components/ui/table/Table.vue'
import { ArrowPathIcon, FunnelIcon, MagnifyingGlassIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
type RecordItem = Record<string, unknown> & { id?: string | number; name?: string; title?: string; status?: string }
type PageLink = { url: string | null; label: string; active: boolean }
type ResourceItems = RecordItem[] & { data?: RecordItem[]; links?: PageLink[]; from?: number | null; to?: number | null; total?: number }
type FilterOption = { value: string; label: string }
const props = withDefaults(defineProps<{ title: string; description?: string; active: string; items?: ResourceItems; loading?: boolean; error?: boolean; searchable?: boolean; search?: string; status?: string; statusLabel?: string; statusOptions?: FilterOption[]; sort?: string; sortDirection?: 'asc' | 'desc'; sortOptions?: FilterOption[]; filterActive?: boolean; createHref?: string; createLabel?: string; emptyTitle?: string }>(), { items: () => [] as ResourceItems, loading: false, error: false, searchable: true, search: '', status: '', statusLabel: 'Situação', statusOptions: () => [{ value: '', label: 'Todas' }], sort: '', sortDirection: 'desc', sortOptions: () => [], filterActive: false })
const emit = defineEmits<{ search: [value: string]; statusChange: [value: string]; sortChange: [value: { sort: string; direction: 'asc' | 'desc' }]; retry: []; filter: []; clear: []; page: [url: string] }>()
const localSearch = shallowRef(props.search); const filterOpen = shallowRef(false); const filterTrigger = useTemplateRef<HTMLButtonElement>('filterTrigger'); const filterPanel = useTemplateRef<HTMLDivElement>('filterPanel'); const externalContent = useTemplateRef<HTMLDivElement>('externalContent')
let filterTimer: ReturnType<typeof setTimeout> | undefined
const rows = computed(() => Array.isArray(props.items) ? props.items : props.items.data || []); const links = computed(() => props.items.links || []); const hasItems = computed(() => rows.value.length > 0)
const hasActiveFilters = computed(() => Boolean(localSearch.value.trim() || props.status || props.filterActive))
function focusableControls() { return Array.from(filterPanel.value?.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])') || []) }
function toggleFilters() { filterOpen.value = !filterOpen.value; if (filterOpen.value) nextTick(() => focusableControls()[0]?.focus()) }
function closeFilters() { filterOpen.value = false; nextTick(() => filterTrigger.value?.focus()) }
function scheduleFilter() { if (filterTimer) clearTimeout(filterTimer); filterTimer = setTimeout(() => emit('filter'), 300) }
function filterChanged() { if (filterTimer) clearTimeout(filterTimer); emit('filter') }
function onKeydown(event: KeyboardEvent) { if (!filterOpen.value) return; if (event.key === 'Escape') { event.preventDefault(); closeFilters(); return } if (event.key !== 'Tab') return; const controls = focusableControls(); if (!controls.length) return; const first = controls[0]; const last = controls[controls.length - 1]; if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() } else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() } }
function onResize() { if (window.innerWidth >= 768 && filterOpen.value) closeFilters() }
onMounted(() => { window.addEventListener('keydown', onKeydown); window.addEventListener('resize', onResize) })
watch(filterOpen, (open) => { document.body.classList.toggle('overflow-hidden', open); if (externalContent.value) { externalContent.value.inert = open; externalContent.value.setAttribute('aria-hidden', open ? 'true' : 'false') }; if (open) nextTick(() => focusableControls()[0]?.focus()) })
watch(() => props.search, (value) => { localSearch.value = value })
onBeforeUnmount(() => { if (filterTimer) clearTimeout(filterTimer); window.removeEventListener('keydown', onKeydown); window.removeEventListener('resize', onResize); document.body.classList.remove('overflow-hidden'); if (externalContent.value) { externalContent.value.inert = false; externalContent.value.removeAttribute('aria-hidden') } })
</script>
<template>
  <AppShell :title="title" :active="active">
    <div class="page-content mx-auto min-w-0 p-4 sm:p-6 2xl:p-8">
      <div ref="externalContent">
        <PageHeader :title="title" :description="description"><template #actions><Link v-if="createHref" class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium leading-5 text-white transition-colors hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0" :href="createHref"><PlusIcon aria-hidden="true" /> {{ createLabel || 'Novo registro' }}</Link><button v-else-if="createLabel" class="btn-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-brand-500 bg-brand-500 px-4 py-2.5 text-sm font-medium leading-5 text-white" type="button" disabled><PlusIcon aria-hidden="true" /> {{ createLabel }}</button></template></PageHeader>
        <slot name="summary" />
        <section v-if="searchable || $slots.filters" class="panel mb-5 min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-5 dark:border-gray-800 dark:bg-gray-900" aria-label="Busca e filtros">
          <div class="filter-bar grid min-w-0 grid-cols-12 items-end gap-3">
            <div v-if="searchable" class="field-group col-span-12 min-w-0" :class="statusOptions.length ? 'lg:col-span-9' : 'lg:col-span-12'">
              <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="resource-search">Buscar</label>
              <div class="field-with-icon relative min-w-0">
                <MagnifyingGlassIcon class="field-icon pointer-events-none absolute left-3.5 top-3 size-4 text-gray-400" aria-hidden="true" />
                <input id="resource-search" v-model="localSearch" class="form-input form-input--with-leading h-11 w-full min-w-0 rounded-[10px] border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm text-gray-800 shadow-theme-xs outline-none transition focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" type="search" :placeholder="`Buscar ${title.toLocaleLowerCase()}...`" @input="emit('search', localSearch)" />
              </div>
            </div>
            <div v-if="statusOptions.length" class="field-group col-span-12 min-w-0" :class="searchable ? 'lg:col-span-3' : 'lg:col-span-12'">
              <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="resource-status">{{ statusLabel }}</label>
              <FormSelect id="resource-status" context="filter" :model-value="status" @update:model-value="emit('statusChange', String($event))">
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </FormSelect>
            </div>
            <div v-if="searchable && statusOptions.length && hasActiveFilters" class="field-group col-span-12 min-w-0 lg:justify-self-end">
              <button class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 lg:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" type="button" @click="emit('clear')">
                <XMarkIcon class="size-4 shrink-0" aria-hidden="true" />
                <span>Limpar filtros</span>
              </button>
            </div>
            <button v-if="$slots.filters" ref="filterTrigger" class="btn-secondary resource-filter-trigger inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-brand-500 md:hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" type="button" aria-controls="resource-filters" :aria-expanded="filterOpen" @click="toggleFilters"><FunnelIcon class="size-4" aria-hidden="true" /> Filtros</button>
            <Teleport v-if="$slots.filters" to="body" :disabled="!filterOpen">
              <div id="resource-filters" ref="filterPanel" class="resource-filter-fields fixed inset-x-3 bottom-3 z-50 max-h-[calc(100dvh-24px)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-5 shadow-theme-lg outline-none md:static md:col-span-12 md:grid md:grid-cols-12 md:items-end md:gap-3 md:border-0 md:bg-transparent md:p-0 md:shadow-none [&_.field-group]:mb-4 md:[&_.field-group]:mb-0" :class="filterOpen ? 'resource-filter-fields--open grid grid-cols-1 gap-3' : 'hidden md:contents'" :role="filterOpen ? 'dialog' : undefined" :aria-modal="filterOpen ? 'true' : undefined" aria-labelledby="resource-filters-title" tabindex="-1" @input="scheduleFilter" @change="filterChanged">
                <div class="resource-filter-fields__heading mb-5 flex items-center justify-between gap-3 text-base font-medium text-gray-800 md:hidden dark:text-white/90"><strong id="resource-filters-title">Filtros</strong><button class="btn-text inline-flex min-h-10 items-center rounded-lg px-3 text-sm text-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-500 md:hidden" type="button" @click="closeFilters">Fechar</button></div>
                <slot name="filters" />
              </div>
            </Teleport>
            <div v-if="sortOptions.length" class="col-span-12 grid min-w-0 grid-cols-1 gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2 dark:border-gray-800">
              <div class="field-group min-w-0">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="resource-sort">Ordenar por</label>
                <FormSelect id="resource-sort" :model-value="sort" @update:model-value="emit('sortChange', { sort: String($event), direction: sortDirection })">
                  <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </FormSelect>
              </div>
              <div class="field-group min-w-0">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="resource-sort-direction">Direção</label>
                <FormSelect id="resource-sort-direction" :model-value="sortDirection" @update:model-value="emit('sortChange', { sort, direction: String($event) as 'asc' | 'desc' })">
                  <option value="asc">Crescente</option>
                  <option value="desc">Decrescente</option>
                </FormSelect>
              </div>
            </div>
          </div>
        </section>
        <DataState v-if="loading" kind="loading" /><DataState v-else-if="error" kind="error" :retry="true" @retry="emit('retry')"><button class="btn-text inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm text-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-brand-500" type="button" @click="emit('retry')"><ArrowPathIcon class="size-4" aria-hidden="true" /> Tentar novamente</button></DataState><DataState v-else-if="!hasItems && (localSearch || filterActive)" kind="filtered" /><DataState v-else-if="!hasItems" kind="empty" :title="emptyTitle" /><section v-else class="table-card min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900" :aria-label="title"><header class="flex min-h-16 items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6 dark:border-gray-800"><h2 class="text-sm font-medium text-gray-800 dark:text-white/90">{{ title }}</h2><span class="text-xs text-gray-500 dark:text-gray-400">{{ props.items.total ?? rows.length }} registros</span></header><div class="p-4 sm:p-5"><div class="table-wrap hidden min-w-0 overflow-x-auto rounded-xl border border-gray-200 xl:block dark:border-gray-800"><TailAdminTable class="data-table w-full border-collapse text-left text-sm text-gray-500 dark:text-gray-400 [&_thead]:border-b [&_thead]:border-gray-100 [&_thead]:bg-white [&_thead]:dark:border-gray-800 [&_th]:px-5 [&_th]:py-3 [&_th]:text-xs [&_th]:font-medium [&_th]:text-gray-500 [&_td]:border-t [&_td]:border-gray-100 [&_td]:px-5 [&_td]:py-4 [&_td]:align-middle [&_td_strong]:block [&_td_strong]:font-medium [&_td_strong]:text-gray-800 [&_td>span:not(.status-badge)]:mt-1 [&_td>span:not(.status-badge)]:block [&_td>span:not(.status-badge)]:text-xs [&_tbody_tr]:transition-colors [&_tbody_tr:hover]:bg-gray-50/50 dark:[&_td]:border-gray-800 dark:[&_td_strong]:text-white/90 dark:[&_tbody_tr:hover]:bg-white/[0.02]"><slot name="table" /></TailAdminTable></div><div class="record-cards grid gap-3 xl:hidden"><slot name="cards" /></div><nav v-if="links.length" class="pagination mt-5 flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Paginação"><span>{{ props.items.from || 0 }}–{{ props.items.to || 0 }} de {{ props.items.total || 0 }}</span><div class="pagination__actions flex max-w-full flex-wrap gap-2"><template v-for="link in links" :key="link.label"><Link v-if="link.url" class="btn-secondary inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" :class="{ 'pagination__current !border-brand-500 !bg-brand-50 !text-brand-500 dark:!bg-brand-500/10': link.active }" :href="link.url" preserve-scroll preserve-state>{{ paginationLabel(link.label) }}</Link><span v-else class="btn-secondary pagination__disabled inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3.5 py-2 text-sm text-gray-400 opacity-60 dark:border-gray-800 dark:bg-gray-900" aria-hidden="true">{{ paginationLabel(link.label) }}</span></template></div></nav></div></section>
        <slot name="after" />
      </div>
      <div v-if="filterOpen" class="resource-filter-backdrop fixed inset-0 z-40 bg-td-ink/40 md:hidden" aria-hidden="true" @click="closeFilters" />
    </div>
  </AppShell>
</template>
