<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ChevronDownIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import AppShell from '../../Components/AppShell.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import TdDatePicker from '../../Components/TdDatePicker.vue'

type Client = { id: number; name: string }
type CatalogItem = { id: number; name: string; description?: string; unit?: string; default_unit_price_cents?: number }
type QuoteItemForm = { catalog_item_id: number | ''; description: string; unit: string; quantity: string; unit_price_cents: string; discount_cents: string }

const props = withDefaults(defineProps<{ clients?: Client[]; catalog?: CatalogItem[]; catalogItems?: CatalogItem[]; catalog_items?: CatalogItem[] }>(), {
  clients: () => [],
  catalog: () => [],
  catalogItems: () => [],
  catalog_items: () => [],
})
const catalogItems = props.catalogItems.length ? props.catalogItems : (props.catalog?.length ? props.catalog : props.catalog_items)
const newItem = (): QuoteItemForm => ({ catalog_item_id: '', description: '', unit: 'un', quantity: '1', unit_price_cents: '', discount_cents: '' })
const form = useForm({ client_id: '' as number | '', title: '', description: '', items: [newItem()], discount_cents: '', extra_cents: '', valid_until: '', payment_terms: '', estimated_days: '', internal_notes: '', public_notes: '', media: [] as File[] })
const mediaError = ref('')
const maxMediaBytes = 100 * 1024 * 1024
function selectMedia(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files || [])
  mediaError.value = files.length > 10 ? 'Selecione até 10 arquivos.' : files.some((file) => file.size > maxMediaBytes) ? 'Cada arquivo deve ter até 100 MB.' : ''
  form.media = mediaError.value ? [] : files
}
const expandedItems = ref([true])

function addItem() { form.items.push(newItem()); expandedItems.value.push(true) }
function removeItem(index: number) {
  if (form.items.length <= 1) return
  form.items.splice(index, 1)
  expandedItems.value.splice(index, 1)
  if (index === 0) expandedItems.value[0] = true
}
function cents(value: string | number) {
  const raw = String(value).trim().replace(/\s/g, '').replace(/[^\d,.-]/g, '')
  const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw
  if (!normalized) return null
  const amount = Number(normalized)
  return Number.isFinite(amount) ? Math.max(0, Math.round(amount * 100)) : null
}
function moneyInput(centsValue: number) { return (centsValue / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function money(centsValue: number) { return `R$ ${moneyInput(centsValue)}` }
function itemTotalCents(item: QuoteItemForm) {
  const quantity = item.quantity.trim().replace(',', '.')
  if (!/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/.test(quantity)) return 0
  const [whole, fraction = ''] = quantity.split('.')
  const quantityMilli = Number(whole) * 1000 + Number(fraction.padEnd(3, '0'))
  const unitPrice = cents(item.unit_price_cents) ?? 0
  const itemDiscount = cents(item.discount_cents) ?? 0
  return Math.max(0, Math.floor((quantityMilli * unitPrice + 500) / 1000) - itemDiscount)
}
const itemTotals = computed(() => form.items.map(itemTotalCents))
const itemsTotal = computed(() => itemTotals.value.reduce((total, value) => total + value, 0))
const quoteTotal = computed(() => Math.max(0, itemsTotal.value - (cents(form.discount_cents) ?? 0) + (cents(form.extra_cents) ?? 0)))
const canSave = computed(() =>
  !mediaError.value
  && Number(form.client_id) > 0
  && form.title.trim().length > 0
  && form.title.trim().length <= 255
  && form.items.length > 0
  && form.items.every((item) => {
    const quantity = item.quantity.trim().replace(',', '.')
    return item.description.trim().length > 0
      && item.description.trim().length <= 255
      && item.unit.trim().length > 0
      && item.unit.trim().length <= 20
      && /^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/.test(quantity)
      && Number(quantity) > 0
      && item.unit_price_cents.trim().length > 0
      && cents(item.unit_price_cents) !== null
  })
)
function applyCatalogItem(item: QuoteItemForm) {
  const selected = catalogItems.find((catalogItem) => catalogItem.id === item.catalog_item_id)
  if (!selected) return
  item.description = selected.description || selected.name
  item.unit = selected.unit || 'un'
  item.unit_price_cents = selected.default_unit_price_cents === undefined ? '' : moneyInput(selected.default_unit_price_cents)
}
function submit() {
  if (!canSave.value || form.processing || mediaError.value) return
  form.transform((data) => ({
    ...data,
    discount_cents: cents(data.discount_cents) ?? 0,
    extra_cents: cents(data.extra_cents) ?? 0,
    items: data.items.map((item) => ({ ...item, catalog_item_id: item.catalog_item_id || null, unit_price_cents: cents(item.unit_price_cents), discount_cents: cents(item.discount_cents) })),
  })).post('/quotes', { forceFormData: true })
}
</script>

<template>
  <AppShell title="Novo orçamento" active="quotes">
    <Head title="Novo orçamento" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Novo orçamento" description="Monte uma proposta clara; os totais finais são calculados e confirmados pelo servidor.">
        <template #actions>
          <Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800" href="/quotes">Cancelar</Link>
          <SaveActionButton form="quote-create-form" :required-incomplete="!canSave" :processing="form.processing" />
        </template>
      </PageHeader>
      <p v-if="Object.keys(form.errors).length" class="error-summary rounded-lg border border-td-red/30 bg-td-red/5 px-4 py-3 text-sm leading-5 text-td-red" role="alert">
        Revise os campos destacados antes de salvar.
      </p>

      <form id="quote-create-form" class="grid min-w-0 grid-cols-12 items-start gap-6" @submit.prevent="submit">

        <main class="col-span-12 flex min-w-0 flex-col gap-6 [&>.field-label]:mb-[-12px]">
          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:p-6 [&>.field-label]:mb-[-12px] [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:leading-7 [&_h2]:text-td-ink">
            <div class="panel-heading flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center [&_p]:mt-1 [&_p]:text-sm [&_p]:leading-5 [&_p]:text-td-body">
              <div><h2>Cliente e proposta</h2><p>Comece pelo contexto que o cliente verá.</p></div>
            </div>
            <div class="form-grid grid min-w-0 grid-cols-12 gap-6">
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-client">Cliente</label>
                <div class="relative min-w-0"><FormSelect id="quote-client" v-model="form.client_id" required placeholder="Selecione um cliente"><option value="">Selecione</option><option v-for="client in props.clients" :key="client.id" :value="client.id">{{ client.name }}</option></FormSelect></div>
              </div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-title">Título</label>
                <input id="quote-title" v-model="form.title" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" required placeholder="Ex.: Instalação elétrica" />
              </div>
              <div class="field-group field-group--full col-span-12 min-w-0">
                <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-description">Descrição</label>
                <textarea id="quote-description" v-model="form.description" class="form-input textarea min-h-28 w-full min-w-0 resize-y scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" placeholder="Descreva o serviço e o que está incluído" />
              </div>
            </div>
          </section>

          <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 class="text-lg font-semibold text-td-ink">Imagens e vídeos</h2>
            <p class="mt-1 text-sm text-td-body">Anexe até 10 arquivos à proposta. Cada arquivo pode ter até 100 MB.</p>
            <label class="mt-4 block text-sm font-medium text-td-ink" for="quote-create-media">Arquivos do orçamento</label>
            <input id="quote-create-media" class="mt-2 block w-full rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink file:mr-3 file:border-0 file:bg-td-soft file:px-3 file:py-1 file:text-td-primary" type="file" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,application/pdf,text/plain" @change="selectMedia" />
            <p v-if="form.media.length" class="mt-2 text-sm text-td-body">{{ form.media.length }} {{ form.media.length === 1 ? 'arquivo selecionado' : 'arquivos selecionados' }}</p>
            <p v-if="mediaError || form.errors.media" class="mt-2 text-sm text-td-red" role="alert">{{ mediaError || form.errors.media }}</p>
          </section>

          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:p-6 [&>.field-label]:mb-[-12px] [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:leading-7 [&_h2]:text-td-ink">
            <div class="panel-heading flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center [&_p]:mt-1 [&_p]:text-sm [&_p]:leading-5 [&_p]:text-td-body">
              <div class="min-w-0"><div class="flex flex-wrap items-baseline gap-x-10 gap-y-1"><h2>Itens</h2><p class="whitespace-nowrap text-base font-semibold tabular-nums !text-gray-600 dark:!text-gray-300"><span class="sr-only">Soma total dos itens: </span>{{ money(itemsTotal) }}</p></div><p>Os valores do catálogo preenchem o item, mas o orçamento salva um snapshot.</p></div>
              <button class="btn-secondary inline-flex min-h-11 max-w-full items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-center text-sm font-medium leading-5 text-td-ink transition-colors hover:border-td-primary hover:text-td-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none [&_svg]:size-5 [&_svg]:shrink-0" type="button" @click="addItem"><PlusIcon class="size-5 shrink-0" aria-hidden="true" /> Adicionar item</button>
            </div>

            <div class="flex min-w-0 flex-col gap-4 [&>.field-label]:mb-[-12px]">
              <div v-for="(item, index) in form.items" :key="index" class="min-w-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-sm">
                <div class="flex min-w-0 items-center gap-2 border-b border-gray-200 bg-gray-50/70 pr-4 sm:pr-5">
                  <button class="flex min-h-16 min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 pr-0 text-left text-sm font-semibold text-td-ink transition-colors hover:bg-gray-50 focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-td-primary motion-reduce:transition-none sm:px-5 sm:pr-0" type="button" :aria-expanded="expandedItems[index]" :aria-controls="`quote-item-fields-${index}`" @click="expandedItems[index] = !expandedItems[index]">
                    <span class="min-w-0 flex-1 basis-full break-words sm:basis-auto">{{ item.description || `Item ${index + 1}` }}</span>
                    <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-600 dark:text-gray-300" :aria-label="`Valor total do item: ${money(itemTotals[index] ?? 0)}`">{{ money(itemTotals[index] ?? 0) }}</span>
                    <span class="shrink-0 text-sm font-medium tabular-nums text-td-body">{{ item.quantity || '0' }} {{ item.unit || 'un' }}</span>
                  </button>
                  <button v-if="form.items.length > 1" class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-td-red transition-colors hover:bg-td-red/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-red focus-visible:ring-offset-2 motion-reduce:transition-none" type="button" :aria-label="`Remover item ${index + 1}`" @click="removeItem(index)">
                    <TrashIcon class="size-5" aria-hidden="true" />
                  </button>
                  <button class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-td-body transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-td-primary focus-visible:ring-offset-2 motion-reduce:transition-none" type="button" :aria-label="`${expandedItems[index] ? 'Recolher' : 'Expandir'} item ${index + 1}`" :aria-expanded="expandedItems[index]" :aria-controls="`quote-item-fields-${index}`" @click="expandedItems[index] = !expandedItems[index]">
                    <ChevronDownIcon class="size-5 transition-transform motion-reduce:transition-none" :class="{ 'rotate-180': expandedItems[index] }" aria-hidden="true" />
                  </button>
                </div>

                <div v-show="expandedItems[index]" :id="`quote-item-fields-${index}`" class="form-grid grid min-w-0 grid-cols-12 gap-6 p-4 sm:p-5">
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-catalog-${index}`">Item do catálogo</label>
                    <div class="relative min-w-0"><FormSelect :id="`item-catalog-${index}`" v-model="item.catalog_item_id" @change="applyCatalogItem(item)"><option value="">Personalizado</option><option v-for="catalogItem in catalogItems" :key="catalogItem.id" :value="catalogItem.id">{{ catalogItem.name }}</option></FormSelect></div>
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-unit-${index}`">Unidade</label>
                    <input :id="`item-unit-${index}`" v-model="item.unit" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" required placeholder="Ex.: hora, unidade ou m²" />
                  </div>
                  <div class="field-group field-group--full col-span-12 min-w-0">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-description-${index}`">Descrição do item {{ index + 1 }}</label>
                    <input :id="`item-description-${index}`" v-model="item.description" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" required placeholder="Ex.: Instalação de luminária" />
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6 xl:col-span-3">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-quantity-${index}`">Quantidade</label>
                    <input :id="`item-quantity-${index}`" v-model="item.quantity" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" inputmode="decimal" required placeholder="Ex.: 1" />
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6 xl:col-span-3">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-price-${index}`">Preço unitário (R$)</label>
                    <input :id="`item-price-${index}`" v-model="item.unit_price_cents" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" inputmode="decimal" required placeholder="Ex.: 150,00" />
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6 xl:col-span-3">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-discount-${index}`">Desconto do item (R$)</label>
                    <input :id="`item-discount-${index}`" v-model="item.discount_cents" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" inputmode="decimal" placeholder="Ex.: 0,00" />
                  </div>
                  <div class="field-group col-span-12 min-w-0 sm:col-span-6 xl:col-span-3">
                    <label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" :for="`item-total-${index}`">Valor total do item (R$)</label>
                    <output :id="`item-total-${index}`" :for="`item-quantity-${index} item-price-${index} item-discount-${index}`" class="form-input flex min-h-11 w-full min-w-0 items-center rounded-lg border border-td-border bg-gray-50 px-4 py-2.5 text-sm font-medium tabular-nums text-td-ink dark:bg-gray-800 dark:text-white/90">{{ money(itemTotals[index] ?? 0) }}</output>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="panel flex min-w-0 flex-col gap-6 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:p-6 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:leading-7 [&_h2]:text-td-ink">
            <h2>Detalhes e observações</h2>
            <div class="form-grid grid min-w-0 grid-cols-12 gap-6">
              <div class="field-group col-span-12 min-w-0 sm:col-span-6"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="valid-until">Validade</label><TdDatePicker id="valid-until" v-model="form.valid_until" label="Validade" /></div>
              <div class="field-group col-span-12 min-w-0 sm:col-span-6"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="estimated-days">Prazo de execução</label><input id="estimated-days" v-model="form.estimated_days" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" type="number" min="0" placeholder="Ex.: 5" /></div>
              <div class="field-group col-span-12 min-w-0 md:col-span-4"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-discount">Desconto total (R$)</label><input id="quote-discount" v-model="form.discount_cents" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" inputmode="decimal" placeholder="Ex.: 0,00" /></div>
              <div class="field-group col-span-12 min-w-0 md:col-span-4"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-total">Valor total do orçamento (R$)</label><output id="quote-total" for="quote-discount quote-extra" class="form-input flex min-h-11 w-full min-w-0 items-center rounded-lg border border-td-border bg-gray-50 px-4 py-2.5 text-sm font-semibold tabular-nums text-td-ink dark:bg-gray-800 dark:text-white/90">{{ money(quoteTotal) }}</output></div>
              <div class="field-group col-span-12 min-w-0 md:col-span-4"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="quote-extra">Acréscimo total (R$)</label><input id="quote-extra" v-model="form.extra_cents" class="form-input w-full min-w-0 scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" inputmode="decimal" placeholder="Ex.: 0,00" /></div>
              <div class="field-group field-group--full col-span-12 min-w-0"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="payment-terms">Condições de pagamento</label><textarea id="payment-terms" v-model="form.payment_terms" class="form-input textarea min-h-28 w-full min-w-0 resize-y scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" placeholder="Ex.: 50% na aprovação e 50% na entrega" /></div>
              <div class="field-group field-group--full col-span-12 min-w-0"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="public-notes">Observações públicas</label><textarea id="public-notes" v-model="form.public_notes" class="form-input textarea min-h-28 w-full min-w-0 resize-y scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" placeholder="Informações que o cliente verá no orçamento" /></div>
              <div class="field-group field-group--full col-span-12 min-w-0"><label class="field-label mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400" for="internal-notes">Observações internas</label><textarea id="internal-notes" v-model="form.internal_notes" class="form-input textarea min-h-28 w-full min-w-0 resize-y scroll-mt-28 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink outline-none transition-colors placeholder:text-td-body/70 focus:border-td-primary focus-visible:ring-2 focus-visible:ring-td-primary/15 disabled:cursor-not-allowed disabled:bg-td-soft disabled:text-td-body aria-invalid:border-td-red motion-reduce:transition-none" placeholder="Notas visíveis apenas para sua equipe" /></div>
            </div>
          </section>
        </main>

        <div class="col-span-12 flex min-w-0 flex-col-reverse gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:flex-row sm:items-center sm:justify-end sm:p-5">
          <Link class="inline-flex min-h-11 w-full items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 motion-reduce:transition-none sm:w-auto dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800" href="/quotes">Cancelar</Link>
          <SaveActionButton class="w-full sm:w-auto" :required-incomplete="!canSave" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
</template>
