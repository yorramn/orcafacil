<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import AppShell from '../../Components/AppShell.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import PageHeader from '../../Components/PageHeader.vue'
import { saoPauloToday } from '../../utils/dateLimits'

type Installment = { description: string; due_date: string; amount_cents: number }
type Service = { id: number; title: string; status: string; contracted_total_cents: number; client?: { name?: string } | null; quote?: { display_number?: string; payment_terms?: string | null } | null }
const props = withDefaults(defineProps<{ service: Service; billingExists?: boolean; billingMode?: 'complete' | 'retroactive' }>(), { billingExists: false, billingMode: 'complete' })
const total = Math.max(0, props.service.contracted_total_cents || 0)
const zeroValue = total === 0
const form = useForm<{ installments: Installment[] }>({ installments: zeroValue ? [] : [{ description: 'Parcela 1', due_date: saoPauloToday(), amount_cents: total }] })
const amountTexts = ref<string[]>(zeroValue ? [] : [(total / 100).toFixed(2).replace('.', ',')])
const money = (cents: number) => `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

function parseCents(value: string): number | null {
  const cleaned = value.trim().replace(/\s/g, '').replace(/^R\$/, '')
  const normalized = cleaned.includes(',') ? cleaned.replace(/\./g, '').replace(',', '.') : cleaned
  if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null
  const cents = Math.round(Number(normalized) * 100)
  return Number.isSafeInteger(cents) ? cents : null
}
const parsedAmounts = computed(() => amountTexts.value.map(parseCents))
const enteredTotal = computed(() => parsedAmounts.value.reduce<number>((sum, cents) => sum + (cents || 0), 0))
const amountsValid = computed(() => parsedAmounts.value.every((cents) => cents !== null && cents > 0))
const missingFields = computed(() => form.installments.some((item) => !item.description.trim() || !item.due_date))
const totalMatches = computed(() => enteredTotal.value === total)
const invalid = computed(() => props.billingExists || (!zeroValue && (!form.installments.length || !amountsValid.value || missingFields.value || !totalMatches.value)))
const disabledReason = computed(() => !amountsValid.value || missingFields.value || !form.installments.length ? 'Preencha os campos obrigatórios' : !totalMatches.value ? 'A soma das parcelas deve ser igual ao valor contratado' : '')

function addInstallment() {
  if (form.installments.length >= 60) return
  form.installments.push({ description: `Parcela ${form.installments.length + 1}`, due_date: saoPauloToday(), amount_cents: 0 })
  amountTexts.value.push('')
}
function removeInstallment(index: number) {
  form.installments.splice(index, 1)
  amountTexts.value.splice(index, 1)
}
function submit() {
  if (invalid.value || form.processing) return
  form.installments.forEach((item, index) => { item.amount_cents = parsedAmounts.value[index] || 0 })
  form.post(`/services/${props.service.id}/billing`)
}
</script>

<template>
  <AppShell title="Faturar serviço" active="services">
    <Head :title="`Faturar ${props.service.title}`" />
    <div class="page-content mx-auto max-w-5xl space-y-6 p-4 sm:p-6 2xl:p-8">
      <PageHeader :title="props.billingMode === 'retroactive' ? 'Faturar serviço concluído' : 'Concluir e faturar serviço'" :description="props.service.title" :back-href="`/services/${props.service.id}`" back-label="Voltar ao serviço">
        <template #actions><Link :href="`/services/${props.service.id}`" class="inline-flex min-h-11 items-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm font-medium text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary">Cancelar</Link></template>
      </PageHeader>
      <div v-if="props.billingExists" role="alert" class="rounded-xl border border-td-border bg-white p-5 text-sm text-td-body">Este serviço já possui recebimentos vinculados. Confira os lançamentos antes de continuar.</div>
      <template v-else>
        <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="billing-summary-heading">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div><h2 id="billing-summary-heading" class="text-lg font-semibold text-td-ink">Resumo da contratação</h2><p class="mt-1 text-sm text-td-body">{{ props.service.client?.name || 'Cliente não informado' }}</p><p v-if="props.service.quote?.display_number" class="mt-1 text-sm text-td-body">Orçamento {{ props.service.quote.display_number }}</p></div>
            <div class="sm:text-right"><span class="text-sm text-td-body">Valor contratado</span><p class="text-2xl font-semibold text-td-ink">{{ money(total) }}</p></div>
          </div>
          <p v-if="props.service.quote?.payment_terms" class="mt-5 border-t border-td-border pt-4 text-sm text-td-body"><strong class="font-medium text-td-ink">Condições informadas no orçamento:</strong> {{ props.service.quote.payment_terms }}</p>
        </section>
        <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="installments-heading">
          <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="installments-heading" class="text-lg font-semibold text-td-ink">Parcelas a receber</h2><p class="mt-1 text-sm text-td-body">Confira valores e vencimentos antes de confirmar. A soma deve corresponder ao valor contratado.</p></div><button v-if="!zeroValue" type="button" :disabled="form.installments.length >= 60" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-td-border px-4 text-sm font-medium text-td-primary hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary disabled:opacity-50" @click="addInstallment"><PlusIcon class="size-4" aria-hidden="true" />Adicionar parcela</button></div>
          <p v-if="zeroValue" class="mt-5 rounded-lg border border-td-border bg-td-soft px-4 py-3 text-sm text-td-body">O valor contratado é zero. Ao confirmar, o serviço será concluído sem criar parcelas a receber.</p>
          <FormErrorSummary :errors="form.errors" class="mt-5" />
          <form id="service-billing-form" class="mt-5 space-y-4" @submit.prevent="submit">
            <fieldset v-for="(item, index) in form.installments" :key="index" class="rounded-lg border border-td-border p-4"><legend class="px-2 text-sm font-semibold text-td-ink">Parcela {{ index + 1 }}</legend>
              <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 sm:col-span-6"><label :for="`installment-description-${index}`" class="mb-2 block text-sm font-medium text-td-ink">Descrição <span aria-hidden="true" class="text-td-red">*</span></label><input :id="`installment-description-${index}`" v-model="item.description" type="text" maxlength="255" required class="h-11 w-full rounded-lg border border-td-border px-3 text-sm text-td-ink focus:border-td-primary focus:outline-none" /><p v-if="form.errors[`installments.${index}.description` as keyof typeof form.errors]" class="mt-1 text-sm text-td-red" role="alert">{{ form.errors[`installments.${index}.description` as keyof typeof form.errors] }}</p></div>
                <div class="col-span-12 sm:col-span-3"><label :for="`installment-due-${index}`" class="mb-2 block text-sm font-medium text-td-ink">Vencimento <span aria-hidden="true" class="text-td-red">*</span></label><input :id="`installment-due-${index}`" v-model="item.due_date" type="date" required class="h-11 w-full rounded-lg border border-td-border px-3 text-sm text-td-ink focus:border-td-primary focus:outline-none" /><p v-if="form.errors[`installments.${index}.due_date` as keyof typeof form.errors]" class="mt-1 text-sm text-td-red" role="alert">{{ form.errors[`installments.${index}.due_date` as keyof typeof form.errors] }}</p></div>
                <div class="col-span-12 sm:col-span-3"><label :for="`installment-amount-${index}`" class="mb-2 block text-sm font-medium text-td-ink">Valor (R$) <span aria-hidden="true" class="text-td-red">*</span></label><input :id="`installment-amount-${index}`" v-model="amountTexts[index]" type="text" inputmode="decimal" required placeholder="0,00" class="h-11 w-full rounded-lg border border-td-border px-3 text-sm text-td-ink focus:border-td-primary focus:outline-none" /><p v-if="form.errors[`installments.${index}.amount_cents` as keyof typeof form.errors]" class="mt-1 text-sm text-td-red" role="alert">{{ form.errors[`installments.${index}.amount_cents` as keyof typeof form.errors] }}</p></div>
              </div>
              <button v-if="form.installments.length > 1" type="button" class="mt-3 inline-flex min-h-10 items-center gap-1 rounded-lg px-2 text-sm text-td-red hover:bg-td-red/5 focus-visible:outline-2 focus-visible:outline-td-primary" :aria-label="`Remover parcela ${index + 1}`" @click="removeInstallment(index)"><TrashIcon class="size-4" aria-hidden="true" />Remover parcela</button>
            </fieldset>
            <div v-if="!zeroValue" class="flex flex-wrap justify-between gap-3 rounded-lg bg-td-soft px-4 py-3 text-sm"><span class="text-td-body">Total das parcelas: <strong class="text-td-ink">{{ money(enteredTotal) }}</strong></span><span :class="totalMatches ? 'text-td-green-ink' : 'text-td-red'">{{ totalMatches ? 'Valor conferido' : `Diferença: ${money(Math.abs(total - enteredTotal))}` }}</span></div>
            <p v-if="!zeroValue && form.installments.some((item) => item.due_date && item.due_date < saoPauloToday())" class="text-sm text-td-body">Há parcela com vencimento anterior a hoje; ela poderá aparecer como vencida.</p>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-td-border pt-5"><Link :href="`/services/${props.service.id}`" class="inline-flex min-h-11 items-center rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink hover:bg-td-soft">Cancelar</Link><span class="group relative inline-flex" :tabindex="invalid ? 0 : undefined" :aria-describedby="invalid ? 'billing-disabled-reason' : undefined"><button type="submit" :disabled="invalid || form.processing" class="min-h-11 rounded-lg bg-td-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50">{{ form.processing ? 'Confirmando…' : props.billingMode === 'retroactive' ? 'Confirmar faturamento' : 'Concluir e faturar' }}</button><span v-if="invalid" id="billing-disabled-reason" role="tooltip" class="pointer-events-none invisible absolute bottom-full right-0 z-20 mb-2 w-max max-w-[min(17rem,calc(100vw-2rem))] rounded-lg bg-td-ink px-3 py-2 text-center text-xs font-medium text-white shadow-theme-lg group-hover:visible group-focus:visible">{{ disabledReason }}</span></span></div>
          </form>
        </section>
      </template>
    </div>
  </AppShell>
</template>
