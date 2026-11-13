<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import TdSwitch from '../../Components/TdSwitch.vue'
import TdField from '../../Taildash/components/TdField.vue'
import TdMaskedField from '../../Taildash/components/TdMaskedField.vue'
import { maskModelValue } from '../../Taildash/components/masks/format'
import { measurementUnits } from '../../utils/measurementUnits'

const props = defineProps<{ item: any }>()
const form = useForm({ name: props.item.name || '', type: props.item.type || '', description: props.item.description || '', unit: props.item.unit || '', default_unit_price_cents: String(props.item.default_unit_price_cents ?? ''), default_cost_cents: String(props.item.default_cost_cents ?? ''), active: props.item.active !== false })
const requiredIncomplete = computed(() => !form.name.trim() || !form.type || !form.unit || form.default_unit_price_cents === '')
const priceDisplay = shallowRef(maskModelValue(form.default_unit_price_cents, 'currency'))
const costDisplay = shallowRef(maskModelValue(form.default_cost_cents, 'currency'))
const confirmOpen = shallowRef(false)
function submit() { if (!form.processing) confirmOpen.value = true }
function save() { confirmOpen.value = false; form.patch(`/catalog/${props.item.id}`) }
</script>

<template>
  <AppShell title="Editar item" active="catalog">
    <Head title="Editar item do catálogo" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Editar item do catálogo">
        <template #actions><Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/catalog">Cancelar</Link></template>
      </PageHeader>
      <FormErrorSummary :errors="form.errors" />
      <form class="grid min-w-0 grid-cols-12 rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6 [&>*]:col-span-12" @submit.prevent="submit">
        <div class="mb-6 border-b border-td-border pb-5">
          <h2 class="text-base font-semibold text-td-ink">Dados do item</h2>
          <p class="mt-1 text-sm text-td-body">Os valores são copiados como snapshot para cada orçamento.</p>
        </div>
        <div class="grid min-w-0 grid-cols-12 gap-5 sm:gap-6">
          <div class="col-span-12 sm:col-span-8"><TdField id="item-name" v-model="form.name" label="Nome" placeholder="Ex.: Manutenção preventiva" required :error="form.errors.name" /></div>
          <div class="col-span-12 grid min-w-0 content-start gap-2.5 sm:col-span-4">
            <label for="item-type" class="block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo <span class="text-td-red" aria-hidden="true">*</span></label>
            <FormSelect id="item-type" v-model="form.type" required label="tipo" placeholder="Selecione um tipo" :aria-invalid="Boolean(form.errors.type)" :aria-describedby="form.errors.type ? 'item-type-error' : undefined"><option value="service">Serviço</option><option value="material">Material</option><option value="fee">Taxa</option></FormSelect>
            <p v-if="form.errors.type" id="item-type-error" class="text-sm text-td-red" role="alert">{{ form.errors.type }}</p>
          </div>
          <div class="col-span-12 grid min-w-0 grid-cols-12 gap-5 sm:gap-6">
            <div class="col-span-12 grid min-w-0 content-start gap-2.5 sm:col-span-4">
              <label for="item-unit" class="block text-sm font-medium text-gray-700 dark:text-gray-400">Unidade de medida<span class="ml-1 text-td-red" aria-hidden="true">*</span></label>
              <FormSelect id="item-unit" v-model="form.unit" required label="unidade de medida" placeholder="Selecione a unidade" :aria-invalid="Boolean(form.errors.unit)" :aria-describedby="form.errors.unit ? 'item-unit-error' : undefined">
                <option v-if="form.unit && !measurementUnits.some(unit => unit.value === form.unit)" :value="form.unit">{{ form.unit }} (atual)</option>
                <option v-for="unit in measurementUnits" :key="unit.value" :value="unit.value">{{ unit.label }}</option>
              </FormSelect>
              <p v-if="form.errors.unit" id="item-unit-error" class="text-sm text-td-red" role="alert">{{ form.errors.unit }}</p>
            </div>
            <div class="col-span-12 sm:col-span-4"><TdMaskedField id="item-price" v-model="priceDisplay" mask="currency" label="Preço padrão (R$)" placeholder="0,00" required error-key="default_unit_price_cents" :error="form.errors.default_unit_price_cents" @raw="form.default_unit_price_cents = $event" /></div>
            <div class="col-span-12 sm:col-span-4"><TdMaskedField id="item-cost" v-model="costDisplay" mask="currency" label="Custo padrão (R$)" placeholder="0,00" error-key="default_cost_cents" :error="form.errors.default_cost_cents" @raw="form.default_cost_cents = $event" /></div>
          </div>
          <div class="col-span-12 min-w-0"><TdField id="item-description" v-model="form.description" type="textarea" label="Descrição" placeholder="Ex.: Instalação de luminária" :error="form.errors.description" /></div>
          <div class="col-span-12 min-w-0">
            <TdSwitch id="item-active" v-model="form.active" label="Item ativo" :error="form.errors.active" />
          </div>
        </div>
        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-td-border pt-6 sm:flex-row sm:justify-end">
          <Link href="/catalog" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary">Cancelar</Link>
          <SaveActionButton label="Salvar alterações" :required-incomplete="requiredIncomplete" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
  <ConfirmModal :open="confirmOpen" title="Confirmar edição" :message="`Deseja salvar as alterações em ${form.name || 'este item'}?`" :busy="form.processing" @cancel="confirmOpen = false" @confirm="save" />
</template>
