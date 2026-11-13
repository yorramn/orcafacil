<script setup lang="ts">
import FormSelect from '../../Components/FormSelect.vue'
import TdDatePicker from '../../Components/TdDatePicker.vue'
import { computed, shallowRef } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
const props = withDefaults(defineProps<{ quote: any; clients?: any[]; catalog?: any[] }>(), { clients: () => [], catalog: () => [] })
const page = usePage<{ flash?: { success?: string } }>()
const form = useForm({ client_id: props.quote.client_id, title: props.quote.title || '', description: props.quote.description || '', discount_cents: moneyInput(props.quote.discount_cents), extra_cents: moneyInput(props.quote.extra_cents), valid_until: props.quote.valid_until || '', payment_terms: props.quote.payment_terms || '', estimated_days: props.quote.estimated_days ?? '', public_notes: props.quote.public_notes || '', internal_notes: props.quote.internal_notes || '', items: (props.quote.items || []).map((item: any) => ({ catalog_item_id: item.catalog_item_id || null, description: item.description_snapshot || item.description || '', unit: item.unit || 'un', quantity: String(item.quantity || '1'), unit_price_cents: moneyInput(item.unit_price_cents), discount_cents: moneyInput(item.discount_cents) })) })
const confirmOpen = shallowRef(false)
function cents(value: string | number) { const raw = String(value).trim().replace(/\s/g, '').replace(/[^\d,.-]/g, ''); const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw; if (!normalized) return null; const amount = Number(normalized); return Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100)) : null }
function moneyInput(value: string | number | null | undefined) { const amount = Number(value ?? 0); return Number.isFinite(amount) ? (amount / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '' }
const itemTotal = computed(() => form.items.reduce((total, item) => total + Math.max(0, Math.round(Number(item.quantity || 0) * (cents(item.unit_price_cents) ?? 0)) - (cents(item.discount_cents) ?? 0)), 0))
const grandTotal = computed(() => Math.max(0, itemTotal.value - (cents(form.discount_cents) ?? 0) + (cents(form.extra_cents) ?? 0)))
const canSave = computed(() => Number(form.client_id) > 0 && form.title.trim().length > 0 && form.items.length > 0 && form.items.every((item) => item.description.trim() && item.unit.trim() && Number(String(item.quantity).replace(',', '.')) > 0 && cents(item.unit_price_cents) !== null))
const money = (cents: number) => `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
function submit() { if (canSave.value && !form.processing && props.quote.status !== 'approved') confirmOpen.value = true }
function save() { confirmOpen.value = false; form.transform((data) => ({ ...data, discount_cents: cents(data.discount_cents) ?? 0, extra_cents: cents(data.extra_cents) ?? 0, items: data.items.map((item) => ({ ...item, unit_price_cents: cents(item.unit_price_cents), discount_cents: cents(item.discount_cents) })) })).patch(`/quotes/${props.quote.id}`) }
</script>
<template>
  <AppShell title="Editar orçamento" active="quotes">
    <Head title="Editar orçamento" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader :title="props.quote.display_number || 'Editar orçamento'" description="Edite proposta, itens e anexos conforme o contrato.">
        <template #actions>
          <StatusBadge :value="props.quote.status" />
          <Link class="btn-secondary inline-flex min-h-11 max-w-full items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-center text-sm font-medium leading-5 text-td-ink transition-colors hover:border-td-primary hover:text-td-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary focus-visible:ring-offset-2" :href="`/quotes/${props.quote.id}`">Cancelar</Link>
          <SaveActionButton v-if="props.quote.status !== 'approved'" form="quote-edit-form" label="Salvar alterações" :required-incomplete="!canSave" :processing="form.processing" />
        </template>
      </PageHeader>

      <p v-if="page.props.flash?.success" class="status-message rounded-lg border border-td-green/25 bg-td-green/5 px-4 py-3 text-sm leading-5 text-td-green-ink" role="status" aria-live="polite">{{ page.props.flash.success }}</p>
      <FormErrorSummary :errors="form.errors" />
      <p v-if="props.quote.status === 'approved'" class="rounded-lg border border-td-border bg-td-soft px-4 py-3 text-sm text-td-body" role="status">Orçamentos aprovados não podem ser editados.</p>

      <div class="two-col grid min-w-0 grid-cols-12 items-start gap-6">
        <form id="quote-edit-form" class="contents" @submit.prevent="submit">
        <main class="col-span-12 stack flex min-w-0 flex-col gap-6 xl:col-span-8">
          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 class="text-lg font-semibold leading-7 text-td-ink">Cliente e proposta</h2>
            <div class="form-grid grid min-w-0 grid-cols-12 gap-6">
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-client">Cliente</label>
                <FormSelect id="edit-quote-client" v-model="form.client_id" :disabled="['approved','canceled'].includes(props.quote.status)"><option v-for="client in props.clients" :key="client.id" :value="client.id">{{ client.name }}</option></FormSelect>
                <p v-if="form.errors.client_id" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.client_id }}</p>
              </div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-title">Título</label>
                <input id="edit-quote-title" v-model="form.title" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body" :disabled="props.quote.status === 'approved'" placeholder="Ex.: Instalação elétrica" />
                <p v-if="form.errors.title" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.title }}</p>
              </div>
              <div class="field-group min-w-0 col-span-12">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-description">Descrição</label>
                <textarea id="edit-quote-description" v-model="form.description" class="form-input min-h-28 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body" :disabled="props.quote.status === 'approved'" placeholder="Descreva o serviço e o que está incluído" />
              </div>
              <div class="field-group min-w-0 col-span-12">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-public">Observações públicas</label>
                <textarea id="edit-quote-public" v-model="form.public_notes" class="form-input min-h-28 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body" :disabled="props.quote.status === 'approved'" placeholder="Informações que o cliente verá no orçamento" />
              </div>
              <div class="field-group min-w-0 col-span-12">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-internal">Observações internas</label>
                <textarea id="edit-quote-internal" v-model="form.internal_notes" class="form-input min-h-28 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" placeholder="Notas visíveis apenas para sua equipe" />
              </div>
            </div>
          </section>

          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <div>
              <h2 class="text-lg font-semibold leading-7 text-td-ink">Itens</h2>
              <p class="mt-1 text-sm leading-5 text-td-body">Quantidade, preço e desconto são enviados nos nomes do contrato.</p>
            </div>
            <p v-if="!form.items.length" class="text-sm leading-5 text-td-body">Nenhum item retornado para edição.</p>
            <div v-else class="stack flex min-w-0 flex-col gap-6">
              <article v-for="(item, index) in form.items" :key="index" class="record-card flex min-w-0 flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm">
                <div class="form-grid grid min-w-0 grid-cols-12 gap-6">
                  <div class="field-group min-w-0 col-span-12">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`edit-item-description-${index}`">Descrição do item {{ index + 1 }}</label>
                    <input :id="`edit-item-description-${index}`" v-model="item.description" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" required placeholder="Ex.: Instalação de luminária" :aria-invalid="Boolean(form.errors[`items.${index}.description` as keyof typeof form.errors])" />
                    <p v-if="form.errors[`items.${index}.description` as keyof typeof form.errors]" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors[`items.${index}.description` as keyof typeof form.errors] }}</p>
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-4">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`edit-item-quantity-${index}`">Quantidade</label>
                    <input :id="`edit-item-quantity-${index}`" v-model="item.quantity" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" inputmode="decimal" required placeholder="Ex.: 1" />
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-4">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`edit-item-price-${index}`">Preço unitário (R$)</label>
                    <input :id="`edit-item-price-${index}`" v-model="item.unit_price_cents" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" inputmode="decimal" required placeholder="Ex.: 150,00" :aria-invalid="Boolean(form.errors[`items.${index}.unit_price_cents` as keyof typeof form.errors])" />
                    <p v-if="form.errors[`items.${index}.unit_price_cents` as keyof typeof form.errors]" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors[`items.${index}.unit_price_cents` as keyof typeof form.errors] }}</p>
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-4">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`edit-item-discount-${index}`">Desconto do item (R$)</label>
                    <input :id="`edit-item-discount-${index}`" v-model="item.discount_cents" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" inputmode="decimal" placeholder="Ex.: 0,00" :aria-invalid="Boolean(form.errors[`items.${index}.discount_cents` as keyof typeof form.errors])" />
                    <p v-if="form.errors[`items.${index}.discount_cents` as keyof typeof form.errors]" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors[`items.${index}.discount_cents` as keyof typeof form.errors] }}</p>
                  </div>
                </div>
                <p class="text-sm leading-5 text-td-body">Subtotal: <strong>{{ money(Math.max(0, Math.round(Number(item.quantity || 0) * (cents(item.unit_price_cents) ?? 0)) - (cents(item.discount_cents) ?? 0))) }}</strong></p>
              </article>
            </div>
          </section>

          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 class="text-lg font-semibold leading-7 text-td-ink">Prazos e condições</h2>
            <div class="form-grid grid min-w-0 grid-cols-12 gap-6">
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-valid-until">Validade</label>
                <TdDatePicker id="edit-valid-until" v-model="form.valid_until" label="Validade" />
                <p v-if="form.errors.valid_until" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.valid_until }}</p>
              </div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-estimated-days">Prazo de execução (dias)</label>
                <input id="edit-estimated-days" v-model="form.estimated_days" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" type="number" min="0" placeholder="Ex.: 5" :aria-invalid="Boolean(form.errors.estimated_days)" />
                <p v-if="form.errors.estimated_days" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.estimated_days }}</p>
              </div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-discount">Desconto total (R$)</label>
                <input id="edit-quote-discount" v-model="form.discount_cents" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" inputmode="decimal" placeholder="Ex.: 0,00" :aria-invalid="Boolean(form.errors.discount_cents)" />
                <p v-if="form.errors.discount_cents" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.discount_cents }}</p>
              </div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-quote-extra">Acréscimo total (R$)</label>
                <input id="edit-quote-extra" v-model="form.extra_cents" class="form-input w-full min-w-0 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" inputmode="decimal" placeholder="Ex.: 0,00" :aria-invalid="Boolean(form.errors.extra_cents)" />
                <p v-if="form.errors.extra_cents" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.extra_cents }}</p>
              </div>
              <div class="field-group min-w-0 col-span-12">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="edit-payment-terms">Condições de pagamento</label>
                <textarea id="edit-payment-terms" v-model="form.payment_terms" class="form-input min-h-28 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15" placeholder="Ex.: 50% na aprovação e 50% na entrega" :aria-invalid="Boolean(form.errors.payment_terms)" />
                <p v-if="form.errors.payment_terms" class="field-error mt-2 text-sm leading-5 text-td-red" role="alert">{{ form.errors.payment_terms }}</p>
              </div>
            </div>
          </section>
        </main>
        </form>

        <aside class="col-span-12 stack flex min-w-0 flex-col gap-6 xl:col-span-4">
          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 class="text-lg font-semibold leading-7 text-td-ink">Resumo</h2>
            <dl class="metrics-list divide-y divide-td-border [&>div]:flex [&>div]:flex-wrap [&>div]:items-center [&>div]:justify-between [&>div]:gap-3 [&>div]:py-3 [&_dt]:text-td-body [&_dd]:font-semibold [&_dd]:text-td-ink">
              <div><dt>Itens</dt><dd>{{ money(itemTotal) }}</dd></div>
              <div><dt>Desconto</dt><dd>{{ money(cents(form.discount_cents) ?? 0) }}</dd></div>
              <div><dt>Acréscimo</dt><dd>{{ money(cents(form.extra_cents) ?? 0) }}</dd></div>
              <div><dt>Total estimado</dt><dd>{{ money(grandTotal) }}</dd></div>
            </dl>
          </section>
          <Link class="block rounded-xl border border-gray-200 bg-white p-4 text-sm font-medium text-td-primary shadow-theme-sm hover:border-td-primary sm:p-6" :href="`/quotes/${props.quote.id}#quote-attachments`">Anexar imagens, vídeos ou arquivos na visualização do orçamento</Link>
        </aside>
      </div>
    </div>
  </AppShell>
  <ConfirmModal :open="confirmOpen" title="Confirmar edição" :message="`Deseja salvar as alterações no orçamento ${props.quote.display_number || ''}?`" :busy="form.processing" @cancel="confirmOpen = false" @confirm="save" />
</template>
