<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppShell from '../../Components/AppShell.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import { formatBrazilianPhone, formatDocument, maskInputKeepingCaret } from '../../utils/brazilianInputMasks'

const form = useForm({ name: '', kind: '', document: '', phone: '', whatsapp: '', email: '', address: '', notes: '' })
const requiredIncomplete = computed(() => !form.name.trim() || !form.kind)
function mask(field: 'document' | 'phone' | 'whatsapp', event: Event) {
  const input = event.target as HTMLInputElement
  const value = maskInputKeepingCaret(input, field === 'document' ? formatDocument : formatBrazilianPhone)
  form[field] = value
}
function submit() { form.post('/clients') }
</script>

<template>
  <AppShell title="Novo cliente" active="clients">
    <Head title="Novo cliente" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Novo cliente" description="Registre os dados necessários para criar propostas e acompanhar atendimentos.">
        <template #actions><Link class="btn-secondary inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/clients">Cancelar</Link></template>
      </PageHeader>
      <FormErrorSummary :errors="form.errors" />
      <form class="grid min-w-0 grid-cols-12 rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6 [&>*]:col-span-12" @submit.prevent="submit">
        <section aria-labelledby="client-identification-title">
          <div class="mb-5 border-b border-td-border pb-4">
            <h2 id="client-identification-title" class="text-base font-semibold text-td-ink">Identificação</h2>
            <p class="mt-1 text-sm text-td-body">Dados básicos para reconhecer o cliente.</p>
          </div>
          <div class="grid min-w-0 grid-cols-12 gap-5">
            <div class="col-span-12 min-w-0 sm:col-span-8">
              <label for="name" class="mb-2 block text-sm font-medium text-td-ink">Nome ou razão social <span class="text-td-red" aria-hidden="true">*</span></label>
              <input id="name" v-model="form.name" required autocomplete="name" placeholder="Ex.: Ana Souza" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'name-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.name" id="name-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.name }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-4">
              <label for="kind" class="mb-2 block text-sm font-medium text-td-ink">Tipo <span class="text-td-red" aria-hidden="true">*</span></label>
              <FormSelect id="kind" v-model="form.kind" required label="tipo de cliente" placeholder="Selecione o tipo de cliente" :aria-invalid="Boolean(form.errors.kind)" :aria-describedby="form.errors.kind ? 'kind-error' : undefined"><option value="person">Pessoa física</option><option value="company">Pessoa jurídica</option></FormSelect>
              <p v-if="form.errors.kind" id="kind-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.kind }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="document" class="mb-2 block text-sm font-medium text-td-ink">CPF/CNPJ <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="document" :value="form.document" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" :aria-invalid="Boolean(form.errors.document)" :aria-describedby="form.errors.document ? 'document-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('document', $event)" />
              <p v-if="form.errors.document" id="document-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.document }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="email" class="mb-2 block text-sm font-medium text-td-ink">E-mail <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="email" v-model="form.email" type="email" autocomplete="email" placeholder="nome@empresa.com.br" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'email-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.email" id="email-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.email }}</p>
            </div>
          </div>
        </section>

        <section class="mt-7 border-t border-td-border pt-6" aria-labelledby="client-contact-title">
          <div class="mb-5">
            <h2 id="client-contact-title" class="text-base font-semibold text-td-ink">Contato e atendimento</h2>
            <p class="mt-1 text-sm text-td-body">Informe ao menos os canais que costuma usar para falar com este cliente.</p>
          </div>
          <div class="grid min-w-0 grid-cols-12 gap-5">
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="phone" class="mb-2 block text-sm font-medium text-td-ink">Telefone <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="phone" :value="form.phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 3333-4444" :aria-invalid="Boolean(form.errors.phone)" :aria-describedby="form.errors.phone ? 'phone-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('phone', $event)" />
              <p v-if="form.errors.phone" id="phone-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.phone }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="whatsapp" class="mb-2 block text-sm font-medium text-td-ink">WhatsApp <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="whatsapp" :value="form.whatsapp" type="tel" inputmode="tel" placeholder="(11) 99999-9999" :aria-invalid="Boolean(form.errors.whatsapp)" :aria-describedby="form.errors.whatsapp ? 'whatsapp-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('whatsapp', $event)" />
              <p class="mt-1.5 text-xs text-td-body">Preencha apenas se for diferente do telefone.</p>
              <p v-if="form.errors.whatsapp" id="whatsapp-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.whatsapp }}</p>
            </div>
            <div class="col-span-12 min-w-0">
              <label for="address" class="mb-2 block text-sm font-medium text-td-ink">Endereço de atendimento <span class="font-normal text-td-body">(opcional)</span></label>
              <textarea id="address" v-model="form.address" autocomplete="street-address" placeholder="Rua, número, bairro e cidade" :aria-invalid="Boolean(form.errors.address)" :aria-describedby="form.errors.address ? 'address-error' : undefined" class="form-input min-h-24 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-3.5 py-3 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.address" id="address-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.address }}</p>
            </div>
            <div class="col-span-12 min-w-0">
              <label for="notes" class="mb-2 block text-sm font-medium text-td-ink">Observações <span class="font-normal text-td-body">(opcional)</span></label>
              <textarea id="notes" v-model="form.notes" placeholder="Preferências ou informações úteis sobre este cliente" :aria-invalid="Boolean(form.errors.notes)" :aria-describedby="form.errors.notes ? 'notes-error' : undefined" class="form-input min-h-24 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-3.5 py-3 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.notes" id="notes-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.notes }}</p>
            </div>
          </div>
        </section>

        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-td-border pt-6 sm:flex-row sm:justify-end">
          <Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/clients">Cancelar</Link>
          <SaveActionButton label="Salvar cliente" :required-incomplete="requiredIncomplete" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
</template>
