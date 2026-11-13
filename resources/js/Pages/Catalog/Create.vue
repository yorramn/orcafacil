<script setup lang="ts">
import FormSelect from '../../Components/FormSelect.vue'
import TdSwitch from '../../Components/TdSwitch.vue'
import { computed, shallowRef } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import PageHeader from '../../Components/PageHeader.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import TdField from '../../Taildash/components/TdField.vue'
import TdMaskedField from '../../Taildash/components/TdMaskedField.vue'
import { measurementUnits } from '../../utils/measurementUnits'

const page = usePage<{ flash?: { success?: string } }>()
const priceDisplay = shallowRef('')
const costDisplay = shallowRef('')
const form = useForm({
  type: '', name: '', description: '', unit: '',
  default_unit_price_cents: '', default_cost_cents: null as string | null, active: true,
})
const requiredIncomplete = computed(() => !form.name.trim() || !form.type || !form.unit || form.default_unit_price_cents === '')

function submit() {
  form.post('/catalog-items')
}
</script>

<template>
  <AppShell title="Novo item do catálogo" active="catalog">
    <Head title="Novo item do catálogo" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Novo item do catálogo" description="Mantenha serviços, materiais e taxas com preços padrão para acelerar seus orçamentos.">
        <template #actions>
          <Link href="/catalog-items" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary">Cancelar</Link>
        </template>
      </PageHeader>
      <p v-if="page.props.flash?.success" class="rounded-lg border border-td-green/25 bg-td-green/5 px-4 py-3 text-sm text-td-green-ink" role="status">{{ page.props.flash.success }}</p>
      <FormErrorSummary :errors="form.errors" />
      <form class="grid min-w-0 grid-cols-12 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm shadow-td-card sm:p-6 [&>*]:col-span-12" @submit.prevent="submit">
        <div class="mb-6 border-b border-td-border pb-6">
          <h2 class="text-lg font-semibold leading-7 text-td-ink">Dados do item</h2>
          <p class="mt-1 text-sm leading-5 text-td-body">Os valores são copiados como snapshot para cada orçamento.</p>
        </div>
        <div class="grid min-w-0 grid-cols-12 gap-5 sm:gap-6">
          <div class="col-span-12 sm:col-span-8"><TdField id="name" v-model="form.name" label="Nome" placeholder="Ex.: Manutenção preventiva" required :error="form.errors.name" /></div>
          <div class="col-span-12 grid min-w-0 content-start gap-2.5 sm:col-span-4">
            <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-400">Tipo<span class="ml-1 text-td-red" aria-hidden="true">*</span></label>
            <div class="relative min-w-0">
              <FormSelect id="type" v-model="form.type" required placeholder="Selecione um tipo" :aria-invalid="Boolean(form.errors.type)" :aria-describedby="form.errors.type ? 'type-error' : undefined">
                <option value="service">Serviço</option>
                <option value="material">Material</option>
                <option value="fee">Taxa</option>
              </FormSelect>
            </div>
            <p v-if="form.errors.type" id="type-error" class="text-sm text-td-red" role="alert">{{ form.errors.type }}</p>
          </div>
          <div class="col-span-12 grid min-w-0 grid-cols-12 gap-5 sm:gap-6">
            <div class="col-span-12 grid min-w-0 content-start gap-2.5 sm:col-span-4">
              <label for="unit" class="block text-sm font-medium text-gray-700 dark:text-gray-400">Unidade de medida<span class="ml-1 text-td-red" aria-hidden="true">*</span></label>
              <FormSelect id="unit" v-model="form.unit" required placeholder="Selecione a unidade" :aria-invalid="Boolean(form.errors.unit)" :aria-describedby="form.errors.unit ? 'unit-error' : undefined"><option v-for="unit in measurementUnits" :key="unit.value" :value="unit.value">{{ unit.label }}</option></FormSelect>
              <p v-if="form.errors.unit" id="unit-error" class="text-sm text-td-red" role="alert">{{ form.errors.unit }}</p>
            </div>
            <div class="col-span-12 sm:col-span-4"><TdMaskedField id="default_unit_price_cents" v-model="priceDisplay" mask="currency" label="Preço padrão" required :error="form.errors.default_unit_price_cents" @raw="form.default_unit_price_cents = $event" /></div>
            <div class="col-span-12 sm:col-span-4"><TdMaskedField id="default_cost_cents" v-model="costDisplay" mask="currency" label="Custo padrão" :error="form.errors.default_cost_cents" @raw="form.default_cost_cents = $event || null" /></div>
          </div>
          <div class="col-span-12 min-w-0">
            <TdField id="description" v-model="form.description" type="textarea" label="Descrição" placeholder="Descreva o item ou serviço" :error="form.errors.description" />
          </div>
          <div class="col-span-12 min-w-0">
            <TdSwitch id="active" v-model="form.active" label="Item ativo" :error="form.errors.active" />
          </div>
        </div>
        <div class="mt-6 flex flex-col gap-3 border-t border-td-border pt-6 sm:flex-row sm:justify-end">
          <Link href="/catalog-items" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary">Cancelar</Link>
          <SaveActionButton label="Salvar item" :required-incomplete="requiredIncomplete" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
</template>
