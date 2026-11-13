<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, shallowRef, useTemplateRef } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ArrowDownTrayIcon, BanknotesIcon, NoSymbolIcon } from '@heroicons/vue/24/outline'
import ResourceList from '../../Components/ResourceList.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import { formatDateBR } from '../../utils/dateDisplay'
import { saoPauloToday } from '../../utils/dateLimits'

type Receivable = { id: number; description: string; installment_number?: number | null; status: string; due_date?: string | null; amount_cents: number; client?: { name?: string } | null; client_name?: string | null; service?: { id: number; title: string } | null }
type Paged = Receivable[] | { data?: Receivable[]; links?: unknown[]; from?: number; to?: number; total?: number }
const rawProps = withDefaults(defineProps<{ receivables?: Paged; loading?: boolean; error?: boolean; summary?: { pending?: string; overdue?: string; paid?: string }; status?: string; search?: string; sort?: string; direction?: 'asc' | 'desc'; canManage?: boolean }>(), { receivables: () => [], loading: false, error: false, summary: () => ({}), status: '', search: '', sort: 'due_date', direction: 'desc', canManage: false })
function normalize(value: Paged) { return Array.isArray(value) ? value : Object.assign([...(value.data || [])], value) }
const props = computed(() => ({ ...rawProps, receivables: normalize(rawProps.receivables) }))
const appliedStatus = shallowRef(rawProps.status || '')
const search = shallowRef(rawProps.search || '')
const sort = shallowRef(rawProps.sort || 'due_date')
const direction = shallowRef<'asc' | 'desc'>(rawProps.direction || 'desc')
let searchTimer: ReturnType<typeof setTimeout> | undefined
function applyFilters() {
  if (searchTimer) clearTimeout(searchTimer)
  router.get('/receivables', { search: search.value || undefined, status: appliedStatus.value || undefined, sort: sort.value, direction: direction.value }, { preserveState: true, preserveScroll: true, replace: true })
}
function searchChanged(value: string) { search.value = value; if (searchTimer) clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350) }
function statusChanged(value: string) { appliedStatus.value = value; applyFilters() }
function sortChanged(value: { sort: string; direction: 'asc' | 'desc' }) { sort.value = value.sort; direction.value = value.direction; applyFilters() }
function clearFilters() { search.value = ''; appliedStatus.value = ''; applyFilters() }
const exportHref = computed(() => {
  const query = new URLSearchParams()
  if (search.value) query.set('search', search.value)
  if (appliedStatus.value) query.set('status', appliedStatus.value)
  query.set('sort', sort.value); query.set('direction', direction.value)
  return `/receivables/export?${query.toString()}`
})
const money = (cents?: number | null) => `R$ ${((cents || 0) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const payable = (item: Receivable) => ['pending', 'overdue'].includes(item.status)

const selected = shallowRef<Receivable | null>(null)
const action = shallowRef<'settle' | 'cancel' | null>(null)
const dialog = useTemplateRef<HTMLDialogElement>('actionDialog')
const settleForm = useForm({ paid_at: saoPauloToday(), payment_method: '', notes: '' })
const cancelForm = useForm({ reason: '' })
let previousFocus: HTMLElement | null = null
async function openDialog(item: Receivable, mode: 'settle' | 'cancel') {
  if (!rawProps.canManage || !payable(item)) return
  previousFocus = document.activeElement as HTMLElement | null
  selected.value = item; action.value = mode
  settleForm.reset(); settleForm.paid_at = saoPauloToday(); settleForm.clearErrors()
  cancelForm.reset(); cancelForm.clearErrors()
  await nextTick(); dialog.value?.showModal()
}
function closeDialog(force = false) {
  if (!force && (settleForm.processing || cancelForm.processing)) return
  dialog.value?.close(); selected.value = null; action.value = null
  previousFocus?.focus(); previousFocus = null
}
function submitAction() {
  if (!selected.value) return
  if (action.value === 'settle') settleForm.post(`/receivables/${selected.value.id}/payments`, { onSuccess: () => closeDialog(true) })
  if (action.value === 'cancel') cancelForm.post(`/receivables/${selected.value.id}/cancel`, { onSuccess: () => closeDialog(true) })
}
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer); if (dialog.value?.open) dialog.value.close() })
</script>

<template>
  <ResourceList title="Recebimentos" active="receivables" description="Acompanhe parcelas, vencimentos e pagamentos de serviços faturados." :items="props.receivables" :loading="props.loading" :error="props.error" searchable :search="search" :status="appliedStatus" :status-options="[{ value: '', label: 'Todos os recebimentos' }, { value: 'pending', label: 'Pendentes' }, { value: 'overdue', label: 'Vencidos' }, { value: 'paid', label: 'Pagos' }, { value: 'canceled', label: 'Cancelados' }]" :sort="sort" :sort-direction="direction" :sort-options="[{ value: 'due_date', label: 'Vencimento' }, { value: 'amount_cents', label: 'Valor' }, { value: 'created_at', label: 'Data de criação' }]" :filter-active="Boolean(appliedStatus || search)" empty-title="Nenhum recebimento encontrado" @search="searchChanged" @status-change="statusChanged" @sort-change="sortChanged" @clear="clearFilters" @filter="applyFilters">
    <template #summary>
      <div class="mb-4 flex justify-end"><a :href="exportHref" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm font-medium text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary"><ArrowDownTrayIcon class="size-5" aria-hidden="true" />Exportar CSV</a></div>
      <div class="stats-grid mb-6 grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-3">
        <article class="stat-card flex min-w-0 flex-col gap-2 rounded-lg border border-td-border bg-white p-5"><span class="text-sm text-td-body">A receber</span><strong class="break-words text-2xl font-semibold text-td-ink">R$ {{ props.summary.pending || '0,00' }}</strong></article>
        <article class="stat-card flex min-w-0 flex-col gap-2 rounded-lg border border-td-border bg-white p-5"><span class="text-sm text-td-body">Vencido</span><strong class="break-words text-2xl font-semibold text-td-ink">R$ {{ props.summary.overdue || '0,00' }}</strong></article>
        <article class="stat-card flex min-w-0 flex-col gap-2 rounded-lg border border-td-border bg-white p-5"><span class="text-sm text-td-body">Recebido</span><strong class="break-words text-2xl font-semibold text-td-ink">R$ {{ props.summary.paid || '0,00' }}</strong></article>
      </div>
    </template>
    <template #table>
      <thead><tr><th>Parcela</th><th>Cliente</th><th>Serviço</th><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Ações</th></tr></thead>
      <tbody><tr v-for="item in props.receivables" :key="item.id">
        <td><strong>{{ item.description }}</strong><span v-if="item.installment_number">Parcela {{ item.installment_number }}</span></td>
        <td>{{ item.client?.name || item.client_name || '—' }}</td>
        <td><Link v-if="item.service" :href="`/services/${item.service.id}`" class="font-medium text-td-primary hover:underline focus-visible:outline-2 focus-visible:outline-td-primary">{{ item.service.title }}</Link><span v-else>—</span></td>
        <td>{{ formatDateBR(item.due_date) }}</td><td>{{ money(item.amount_cents) }}</td><td><StatusBadge :value="item.status" /></td>
        <td><div v-if="rawProps.canManage && payable(item)" class="flex flex-wrap gap-2"><button type="button" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-td-primary hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary" @click="openDialog(item, 'settle')"><BanknotesIcon class="size-4" aria-hidden="true" />Dar baixa</button><button type="button" class="inline-flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm font-medium text-td-red hover:bg-td-red/5 focus-visible:outline-2 focus-visible:outline-td-primary" @click="openDialog(item, 'cancel')"><NoSymbolIcon class="size-4" aria-hidden="true" />Cancelar</button></div></td>
      </tr></tbody>
    </template>
    <template #cards>
      <article v-for="item in props.receivables" :key="item.id" class="record-card flex min-w-0 flex-col gap-4 rounded-xl border border-td-border bg-white p-4 shadow-td-card">
        <div class="flex min-w-0 flex-wrap items-start justify-between gap-3"><div class="min-w-0"><h3 class="break-words font-semibold text-td-ink">{{ item.description }}</h3><p class="mt-1 text-sm text-td-body">{{ item.client?.name || item.client_name || 'Cliente não informado' }}</p></div><StatusBadge :value="item.status" /></div>
        <dl class="grid grid-cols-2 gap-4 text-sm"><div><dt class="text-td-body">Vencimento</dt><dd class="mt-1 font-medium text-td-ink">{{ formatDateBR(item.due_date) }}</dd></div><div><dt class="text-td-body">Valor</dt><dd class="mt-1 font-medium text-td-ink">{{ money(item.amount_cents) }}</dd></div><div v-if="item.service" class="col-span-2"><dt class="text-td-body">Serviço</dt><dd class="mt-1"><Link :href="`/services/${item.service.id}`" class="font-medium text-td-primary hover:underline">{{ item.service.title }}</Link></dd></div></dl>
        <div v-if="rawProps.canManage && payable(item)" class="flex flex-wrap gap-2 border-t border-td-border pt-3"><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 text-sm font-medium text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-td-primary" @click="openDialog(item, 'settle')"><BanknotesIcon class="size-5" aria-hidden="true" />Dar baixa</button><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-td-border px-4 text-sm font-medium text-td-red hover:bg-td-red/5 focus-visible:outline-2 focus-visible:outline-td-primary" @click="openDialog(item, 'cancel')"><NoSymbolIcon class="size-5" aria-hidden="true" />Cancelar</button></div>
      </article>
    </template>
  </ResourceList>
  <dialog ref="actionDialog" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[min(92vw,30rem)] max-w-none overflow-y-auto rounded-xl border border-td-border bg-white p-0 text-td-ink shadow-theme-lg backdrop:bg-td-ink/50" aria-labelledby="receivable-dialog-title" @cancel.prevent="closeDialog()">
    <form v-if="selected && action" class="p-5 sm:p-6" @submit.prevent="submitAction">
      <div class="border-b border-td-border pb-4"><h2 id="receivable-dialog-title" class="text-lg font-semibold">{{ action === 'settle' ? 'Dar baixa na parcela' : 'Cancelar parcela' }}</h2><p class="mt-1 text-sm text-td-body">{{ selected.description }} · {{ money(selected.amount_cents) }}</p></div>
      <template v-if="action === 'settle'"><p class="my-4 text-sm text-td-body">A baixa registra o pagamento integral desta parcela.</p><div class="space-y-4"><div><label for="payment-date" class="mb-2 block text-sm font-medium">Data do pagamento <span aria-hidden="true" class="text-td-red">*</span></label><input id="payment-date" v-model="settleForm.paid_at" type="date" :max="saoPauloToday()" required class="h-11 w-full rounded-lg border border-td-border px-3 text-sm focus:border-td-primary focus:outline-none" /><p v-if="settleForm.errors.paid_at" role="alert" class="mt-1 text-sm text-td-red">{{ settleForm.errors.paid_at }}</p></div><div><label for="payment-method" class="mb-2 block text-sm font-medium">Forma de pagamento <span class="font-normal text-td-body">(opcional)</span></label><input id="payment-method" v-model="settleForm.payment_method" type="text" maxlength="80" class="h-11 w-full rounded-lg border border-td-border px-3 text-sm focus:border-td-primary focus:outline-none" /><p v-if="settleForm.errors.payment_method" role="alert" class="mt-1 text-sm text-td-red">{{ settleForm.errors.payment_method }}</p></div><div><label for="payment-notes" class="mb-2 block text-sm font-medium">Observações <span class="font-normal text-td-body">(opcional)</span></label><textarea id="payment-notes" v-model="settleForm.notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-td-border px-3 py-2 text-sm focus:border-td-primary focus:outline-none" /><p v-if="settleForm.errors.notes" role="alert" class="mt-1 text-sm text-td-red">{{ settleForm.errors.notes }}</p></div></div></template>
      <template v-else><p class="my-4 text-sm text-td-body">O serviço permanecerá concluído. Informe por que esta parcela será cancelada.</p><label for="cancel-reason" class="mb-2 block text-sm font-medium">Justificativa <span aria-hidden="true" class="text-td-red">*</span></label><textarea id="cancel-reason" v-model="cancelForm.reason" rows="4" maxlength="1000" required class="w-full rounded-lg border border-td-border px-3 py-2 text-sm focus:border-td-primary focus:outline-none" /><p v-if="cancelForm.errors.reason" role="alert" class="mt-1 text-sm text-td-red">{{ cancelForm.errors.reason }}</p></template>
      <p v-if="action === 'settle' && Object.keys(settleForm.errors).length && !settleForm.errors.paid_at && !settleForm.errors.payment_method && !settleForm.errors.notes" role="alert" class="mt-4 text-sm text-td-red">{{ Object.values(settleForm.errors)[0] }}</p>
      <p v-if="action === 'cancel' && Object.keys(cancelForm.errors).length && !cancelForm.errors.reason" role="alert" class="mt-4 text-sm text-td-red">{{ Object.values(cancelForm.errors)[0] }}</p>
      <div class="mt-6 flex flex-wrap justify-end gap-3 border-t border-td-border pt-4"><button type="button" class="min-h-11 rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink hover:bg-td-soft" :disabled="settleForm.processing || cancelForm.processing" @click="closeDialog">Não</button><button type="submit" class="min-h-11 rounded-lg bg-td-primary px-4 text-sm font-medium text-white hover:bg-td-primary/90 disabled:opacity-50" :disabled="settleForm.processing || cancelForm.processing || (action === 'settle' ? !settleForm.paid_at : !cancelForm.reason.trim())">{{ action === 'settle' ? 'Confirmar baixa' : 'Confirmar cancelamento' }}</button></div>
    </form>
  </dialog>
</template>
