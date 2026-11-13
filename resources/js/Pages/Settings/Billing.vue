<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'

type UsageValue = { current?: number; limit?: number }
type UsagePayload = Record<string, UsageValue | number | undefined> & { users?: UsageValue; clients?: UsageValue; quotes_month?: UsageValue; services_open?: UsageValue }
type Subscription = { plan?: string; status?: string; current_period_end?: string }
type Plan = { key?: string; code?: string; name?: string; price?: string }
const props = withDefaults(defineProps<{ subscription?: Subscription; usage?: UsagePayload; plans?: Plan[] }>(), { subscription: () => ({ plan: 'basic', status: 'trialing' }), usage: () => ({}), plans: () => [] })
const page = usePage<{ flash?: { success?: string } }>()
const planForm = useForm({ plan: props.subscription.plan || 'basic' })
const cancelForm = useForm({})
const confirmation = shallowRef<'plan' | 'cancel' | null>(null)
const selectedPlan = shallowRef('')
function usageValue(key: string, aliases: string[] = []): UsageValue {
  for (const candidate of [key, ...aliases]) {
    const value = props.usage[candidate]
    if (value && typeof value === 'object') return value
  }
  return { current: 0, limit: 0 }
}
const availablePlans = computed(() => props.plans.map((plan) => ({ ...plan, key: plan.key || plan.code || '' })))
const formError = computed(() => planForm.errors.plan || cancelForm.errors.cancel || Object.values(planForm.errors)[0] || Object.values(cancelForm.errors)[0])
const metrics = computed(() => [
  ['clients', 'Clientes'],
  ['quotes_month', 'Orçamentos no mês'],
  ['services_open', 'Serviços em aberto'],
  ['users', 'Usuários'],
].map(([key, label]) => ({ key, label, ...usageValue(key, key === 'quotes_month' ? ['quotes'] : key === 'services_open' ? ['services'] : []) })))
function changePlan(plan: string) {
  if (plan === props.subscription.plan || planForm.processing) return
  selectedPlan.value = plan
  confirmation.value = 'plan'
}
function cancelSubscription() {
  if (!cancelForm.processing) confirmation.value = 'cancel'
}
function confirmAction() {
  const action = confirmation.value
  confirmation.value = null
  if (action === 'plan') { planForm.plan = selectedPlan.value; planForm.post('/billing/plan') }
  if (action === 'cancel') cancelForm.post('/billing/cancel')
}
</script>

<template><AppShell title="Plano e cobrança" active="settings"><Head title="Plano e cobrança" /><div class="page-content min-w-0 p-4 sm:p-6 2xl:p-11 [&>.panel]:mt-6"><PageHeader title="Plano, uso e cobrança" description="Confira o plano, os limites reais e o estado da assinatura." /><p v-if="page.props.flash?.success" class="status-message my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink [&_svg]:size-5 [&_svg]:shrink-0" role="status">{{ page.props.flash.success }}</p><p v-if="formError" class="error-summary my-5 flex min-w-0 items-start gap-3 rounded-lg border border-td-red/25 bg-td-red/5 p-4 text-sm leading-6 text-td-red [&_svg]:size-5 [&_svg]:shrink-0" role="alert">{{ formError }}</p><div class="two-col grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,1fr)]"><main class="stack grid min-w-0 gap-6"><section class="panel min-w-0 rounded-lg border border-td-border bg-white p-6 shadow-td-card sm:p-8 [&_h2]:text-lg [&_h2]:font-medium [&_h2]:leading-7 [&_h2]:text-td-ink [&_h3]:font-medium [&_h3]:text-td-ink [&_dd]:break-words"><div class="panel-heading mb-6 flex flex-wrap items-start justify-between gap-4 [&_p]:mt-2 [&_p]:text-sm [&_p]:leading-6 [&_p]:text-td-body"><div><h2>Plano atual</h2><p>{{ props.subscription.plan || 'Não informado' }}</p></div><StatusBadge :value="props.subscription.status || 'unknown'" /></div><div class="stats-grid grid min-w-0 grid-cols-1 gap-6 sm:grid-cols-2"><article v-for="metric in metrics" :key="metric.key" class="stat-card min-w-0 rounded-lg border border-td-border bg-white p-5"><span class="stat-card__label text-sm font-medium leading-5 text-td-body">{{ metric.label }}</span><strong class="stat-card__value mt-4 block text-2xl font-bold leading-[30px] text-td-ink">{{ metric.current }}</strong><span class="stat-card__detail mt-2 block text-sm text-td-body">de {{ metric.limit }}</span></article></div></section><section class="panel min-w-0 rounded-lg border border-td-border bg-white p-6 shadow-td-card sm:p-8 [&_h2]:text-lg [&_h2]:font-medium [&_h2]:leading-7 [&_h2]:text-td-ink [&_h3]:font-medium [&_h3]:text-td-ink [&_dd]:break-words"><h2>Planos disponíveis</h2><div v-if="availablePlans.length" class="billing-plan-grid mt-6 grid min-w-0 gap-4 sm:grid-cols-2"><article v-for="plan in availablePlans" :key="plan.key" class="record-card min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm [&_h3]:text-base [&_h3]:font-medium [&_h3]:text-td-ink [&_dl]:mt-4 [&_dl]:grid [&_dl]:grid-cols-2 [&_dl]:gap-4 [&_dt]:text-sm [&_dt]:text-td-body [&_dd]:mt-1 [&_dd]:break-words [&_svg]:inline-block [&_svg]:size-5 [&_svg]:shrink-0 [&_strong]:font-medium [&_strong]:text-td-ink"><h3>{{ plan.name || plan.key }}</h3><p class="form-help mt-2 text-sm leading-6 text-td-body">{{ plan.price || 'Preço não informado' }}</p><button class="btn-secondary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="button" :disabled="!plan.key || plan.key === props.subscription.plan || planForm.processing || cancelForm.processing" @click="changePlan(plan.key || '')">{{ planForm.processing && planForm.plan === plan.key ? 'Atualizando…' : plan.key === props.subscription.plan ? 'Plano atual' : 'Trocar para este plano' }}</button></article></div><p v-else class="form-help mt-2 text-sm leading-6 text-td-body">Nenhum plano foi retornado pelo servidor.</p></section></main><aside class="panel min-w-0 rounded-lg border border-td-border bg-white p-6 shadow-td-card sm:p-8 [&_h2]:text-lg [&_h2]:font-medium [&_h2]:leading-7 [&_h2]:text-td-ink [&_h3]:font-medium [&_h3]:text-td-ink [&_dd]:break-words"><h2>Assinatura</h2><p class="form-help mt-2 text-sm leading-6 text-td-body">Período atual: {{ props.subscription.current_period_end || 'Não informado' }}</p><button class="btn-secondary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0" type="button" :disabled="cancelForm.processing || props.subscription.status === 'canceled'" @click="cancelSubscription">{{ cancelForm.processing ? 'Cancelando…' : props.subscription.status === 'canceled' ? 'Assinatura cancelada' : 'Cancelar assinatura' }}</button><p class="form-help mt-2 text-sm leading-6 text-td-body">Cancelar preserva os dados e exige confirmação.</p></aside></div></div></AppShell><ConfirmModal :open="confirmation === 'plan'" title="Trocar plano" message="Deseja trocar para o plano selecionado agora?" :busy="planForm.processing" @cancel="confirmation = null" @confirm="confirmAction" /><ConfirmModal :open="confirmation === 'cancel'" title="Cancelar assinatura" message="Deseja cancelar a assinatura? Seus dados serão preservados." :busy="cancelForm.processing" @cancel="confirmation = null" @confirm="confirmAction" /></template>
