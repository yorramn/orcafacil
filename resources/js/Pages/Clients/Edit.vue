<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, shallowRef } from 'vue'
import AppShell from '../../Components/AppShell.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import { formatBrazilianPhone, formatDocument, maskInputKeepingCaret } from '../../utils/brazilianInputMasks'

const props = defineProps<{ client: any }>()
const form = useForm({ name: props.client.name || '', kind: ['company', 'PJ'].includes(props.client.kind) ? 'company' : 'person', document: props.client.document || '', phone: props.client.phone || '', whatsapp: props.client.whatsapp || '', email: props.client.email || '', address: props.client.address || '', notes: props.client.notes || '' })
const requiredIncomplete = computed(() => !form.name.trim() || !form.kind)
const confirmOpen = shallowRef(false)
function mask(field: 'document' | 'phone' | 'whatsapp', event: Event) {
  const input = event.target as HTMLInputElement
  const value = maskInputKeepingCaret(input, field === 'document' ? formatDocument : formatBrazilianPhone)
  form[field] = value
}
function submit() { if (!form.processing) confirmOpen.value = true }
function save() { confirmOpen.value = false; form.patch(`/clients/${props.client.id}`) }
</script>

<template>
  <AppShell title="Editar cliente" active="clients">
    <Head title="Editar cliente" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Editar cliente">
        <template #actions><Link class="btn-secondary inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :href="`/clients/${props.client.id}`">Voltar</Link></template>
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
              <label for="edit-name" class="mb-2 block text-sm font-medium text-td-ink">Nome ou razão social <span class="text-td-red" aria-hidden="true">*</span></label>
              <input id="edit-name" v-model="form.name" required autocomplete="name" placeholder="Ex.: Ana Souza" :aria-invalid="Boolean(form.errors.name)" :aria-describedby="form.errors.name ? 'edit-name-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.name" id="edit-name-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.name }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-4">
              <label for="edit-kind" class="mb-2 block text-sm font-medium text-td-ink">Tipo <span class="text-td-red" aria-hidden="true">*</span></label>
              <FormSelect id="edit-kind" v-model="form.kind" required label="tipo de cliente" placeholder="Selecione o tipo de cliente" :aria-invalid="Boolean(form.errors.kind)" :aria-describedby="form.errors.kind ? 'edit-kind-error' : undefined"><option value="person">Pessoa física</option><option value="company">Pessoa jurídica</option></FormSelect>
              <p v-if="form.errors.kind" id="edit-kind-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.kind }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="edit-document" class="mb-2 block text-sm font-medium text-td-ink">CPF/CNPJ <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="edit-document" :value="form.document" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" :aria-invalid="Boolean(form.errors.document)" :aria-describedby="form.errors.document ? 'edit-document-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('document', $event)" />
              <p v-if="form.errors.document" id="edit-document-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.document }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="edit-email" class="mb-2 block text-sm font-medium text-td-ink">E-mail <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="edit-email" v-model="form.email" type="email" autocomplete="email" placeholder="nome@empresa.com.br" :aria-invalid="Boolean(form.errors.email)" :aria-describedby="form.errors.email ? 'edit-email-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.email" id="edit-email-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.email }}</p>
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
              <label for="edit-phone" class="mb-2 block text-sm font-medium text-td-ink">Telefone <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="edit-phone" :value="form.phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="(11) 3333-4444" :aria-invalid="Boolean(form.errors.phone)" :aria-describedby="form.errors.phone ? 'edit-phone-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('phone', $event)" />
              <p v-if="form.errors.phone" id="edit-phone-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.phone }}</p>
            </div>
            <div class="col-span-12 min-w-0 sm:col-span-6">
              <label for="edit-whatsapp" class="mb-2 block text-sm font-medium text-td-ink">WhatsApp <span class="font-normal text-td-body">(opcional)</span></label>
              <input id="edit-whatsapp" :value="form.whatsapp" type="tel" inputmode="tel" placeholder="(11) 99999-9999" :aria-invalid="Boolean(form.errors.whatsapp)" :aria-describedby="form.errors.whatsapp ? 'edit-whatsapp-error' : undefined" class="form-input h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" @input="mask('whatsapp', $event)" />
              <p class="mt-1.5 text-xs text-td-body">Preencha apenas se for diferente do telefone.</p>
              <p v-if="form.errors.whatsapp" id="edit-whatsapp-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.whatsapp }}</p>
            </div>
            <div class="col-span-12 min-w-0">
              <label for="edit-address" class="mb-2 block text-sm font-medium text-td-ink">Endereço de atendimento <span class="font-normal text-td-body">(opcional)</span></label>
              <textarea id="edit-address" v-model="form.address" autocomplete="street-address" placeholder="Rua, número, bairro e cidade" :aria-invalid="Boolean(form.errors.address)" :aria-describedby="form.errors.address ? 'edit-address-error' : undefined" class="form-input min-h-24 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-3.5 py-3 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.address" id="edit-address-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.address }}</p>
            </div>
            <div class="col-span-12 min-w-0">
              <label for="edit-notes" class="mb-2 block text-sm font-medium text-td-ink">Observações <span class="font-normal text-td-body">(opcional)</span></label>
              <textarea id="edit-notes" v-model="form.notes" placeholder="Preferências ou informações úteis sobre este cliente" :aria-invalid="Boolean(form.errors.notes)" :aria-describedby="form.errors.notes ? 'edit-notes-error' : undefined" class="form-input min-h-24 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-3.5 py-3 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
              <p v-if="form.errors.notes" id="edit-notes-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.notes }}</p>
            </div>
          </div>
        </section>

        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-td-border pt-6 sm:flex-row sm:justify-end">
          <Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :href="`/clients/${props.client.id}`">Cancelar</Link>
          <SaveActionButton label="Salvar alterações" :required-incomplete="requiredIncomplete" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
  <ConfirmModal :open="confirmOpen" title="Confirmar edição" :message="`Deseja salvar as alterações em ${form.name || 'este cliente'}?`" :busy="form.processing" @cancel="confirmOpen = false" @confirm="save" />
</template>
