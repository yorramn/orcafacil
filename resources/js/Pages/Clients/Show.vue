<script setup lang="ts">
import { computed, shallowRef } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import AppShell from '../../Components/AppShell.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import DataState from '../../Components/DataState.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import { EnvelopeIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import { formatDateBR } from '../../utils/dateDisplay'
import ClientHistory from '../../Components/Clients/ClientHistory.vue'
import WhatsappIcon from '../../TailAdminSource/icons/layout/WhatsappIcon.vue'

type Page = { data: any[]; total: number; current_page: number; last_page: number; prev_page_url: string | null; next_page_url: string | null }

const props = withDefaults(defineProps<{
  client: any
  quotes: Page
  services: Page
  receivables: Page
  tab: 'quotes' | 'services' | 'receivables'
  activity?: any[]
  notFound?: boolean
}>(), { activity: () => [], notFound: false })

const page = usePage<{ flash?: { success?: string } }>()
const archiveForm = useForm({})
const archiving = shallowRef(false)
const archiveConfirmationOpen = shallowRef(false)
const emailHref = computed(() => props.client?.email?.trim() ? `mailto:${props.client.email.trim()}` : null)
const whatsappHref = computed(() => {
  for (const value of [props.client?.whatsapp, props.client?.phone]) {
    const digits = String(value || '').replace(/\D/g, '')
    if (/^55\d{10,11}$/.test(digits)) return `https://wa.me/${digits}`
    if (/^\d{10,11}$/.test(digits)) return `https://wa.me/55${digits}`
  }
  return null
})

function formatDate(value: string | null) {
  return formatDateBR(value, 'Data não informada')
}

function formatDocument(value: string, kind: string) {
  const digits = value.replace(/\D/g, '')
  if (kind === 'company' && digits.length === 14) {
    return digits.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2')
  }
  if (kind !== 'company' && digits.length === 11) {
    return digits.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1-$2')
  }
  return value
}

function phoneHref(value: string) {
  return `tel:${value.replace(/[^\d+]/g, '')}`
}

function archive() {
  if (!archiveForm.processing) archiveConfirmationOpen.value = true
}
function confirmArchive() {
  if (archiveForm.processing) return
  archiveConfirmationOpen.value = false
  archiving.value = true
  archiveForm.delete(`/clients/${props.client.id}`, { onFinish: () => { archiving.value = false } })
}
</script>

<template>
  <AppShell title="Detalhe do cliente" active="clients">
    <Head :title="props.client?.name || 'Cliente'" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <p v-if="page.props.flash?.success" class="status-message flex min-w-0 items-start gap-3 rounded-lg border border-td-green/25 bg-td-green/5 p-4 text-sm leading-6 text-td-green-ink" role="status" aria-live="polite">{{ page.props.flash.success }}</p>
      <p v-if="archiveForm.errors.client" class="error-summary flex min-w-0 items-start gap-3 rounded-lg border border-td-red/25 bg-td-red/5 p-4 text-sm leading-6 text-td-red" role="alert">{{ archiveForm.errors.client }}</p>

      <PageHeader v-if="!props.notFound" :title="props.client?.name || 'Cliente'" description="Dados e histórico deste relacionamento." back-href="/clients" back-label="Clientes">
        <template #actions>
          <Link class="btn-primary inline-flex min-h-11 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm font-medium leading-5 text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :href="`/clients/${props.client.id}/edit`"><PencilSquareIcon class="size-5 shrink-0" aria-hidden="true" /> Editar cliente</Link>
          <button v-if="!props.client.deleted_at" class="btn-secondary inline-flex min-h-11 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm leading-5 text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="archiveForm.processing" :aria-busy="archiveForm.processing" @click="archive"><TrashIcon class="size-5 shrink-0" aria-hidden="true" /> {{ archiving ? 'Excluindo…' : 'Excluir' }}</button>
        </template>
      </PageHeader>

      <DataState v-if="props.notFound" kind="error" title="Cliente não encontrado" description="Não foi possível localizar este registro ou você não tem acesso a ele." />
      <template v-else>
        <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,1fr)]">
          <div class="grid min-w-0 gap-6">
            <section class="panel min-w-0 rounded-xl border border-td-border bg-white p-5 shadow-td-card sm:p-6" aria-labelledby="client-contact-title">
              <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div><h2 id="client-contact-title" class="text-lg font-semibold leading-7 text-td-ink">Dados de contato</h2><p class="mt-1 text-sm leading-5 text-td-body">Informações fornecidas pelo cliente.</p></div>
                <StatusBadge :value="props.client.status || 'active'" />
              </div>
              <dl class="grid min-w-0 gap-x-7 gap-y-5 sm:grid-cols-2 [&_dd]:mt-1 [&_dd]:break-words [&_dd]:font-medium [&_dd]:text-td-ink [&_dt]:text-sm [&_dt]:leading-5 [&_dt]:text-td-body">
                <div><dt>Tipo</dt><dd>{{ props.client.kind === 'company' ? 'Pessoa jurídica' : 'Pessoa física' }}</dd></div>
                <div v-if="props.client.document"><dt>Documento</dt><dd class="tabular-nums">{{ formatDocument(props.client.document, props.client.kind) }}</dd></div>
                <div v-if="props.client.phone"><dt>Telefone</dt><dd><a class="rounded-sm hover:text-td-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :href="phoneHref(props.client.phone)">{{ props.client.phone }}</a></dd></div>
                <div v-if="props.client.email"><dt>E-mail</dt><dd><a class="break-all rounded-sm hover:text-td-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :href="`mailto:${props.client.email}`">{{ props.client.email }}</a></dd></div>
                <div v-if="props.client.address" class="sm:col-span-2"><dt>Endereço de atendimento</dt><dd>{{ props.client.address }}</dd></div>
              </dl>
              <div v-if="emailHref || whatsappHref" class="mt-6 flex flex-wrap gap-3 border-t border-td-border pt-5">
                <a v-if="emailHref" :href="emailHref" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm font-medium text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"><EnvelopeIcon class="size-5" aria-hidden="true" /> Enviar email</a>
                <a v-if="whatsappHref" :href="whatsappHref" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-[#25D366] bg-white px-4 py-2.5 text-sm font-medium text-[#128C4A] hover:bg-[#25D366]/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#128C4A]" :aria-label="`Conversar com ${props.client.name} pelo WhatsApp (abre em nova aba)`"><WhatsappIcon class="size-5" aria-hidden="true" /> WhatsApp</a>
              </div>
            </section>

            <ClientHistory :client-id="props.client.id" :tab="props.tab" :quotes="props.quotes" :services="props.services" :receivables="props.receivables" />
          </div>

          <aside class="grid min-w-0 gap-6">
            <section class="panel min-w-0 rounded-xl border border-td-border bg-white p-5 shadow-td-card sm:p-6" aria-labelledby="client-notes-title"><h2 id="client-notes-title" class="text-lg font-semibold leading-7 text-td-ink">Observações</h2><p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-td-body">{{ props.client.notes || 'Nenhuma observação registrada.' }}</p></section>
            <section v-if="props.activity.length" class="panel min-w-0 rounded-xl border border-td-border bg-white p-5 shadow-td-card sm:p-6" aria-labelledby="client-activity-title">
              <h2 id="client-activity-title" class="text-lg font-semibold leading-7 text-td-ink">Atividade</h2>
              <ul class="mt-4 divide-y divide-td-border" aria-label="Atividades do cliente"><li v-for="event in props.activity" :key="event.id" class="flex min-w-0 gap-3 py-4 first:pt-2 last:pb-0"><span class="mt-2 size-2 shrink-0 rounded-full bg-td-primary" aria-hidden="true" /><div class="min-w-0"><strong class="break-words font-medium text-td-ink">{{ event.description || event.event }}</strong><span class="mt-1 block text-sm leading-5 text-td-body">{{ formatDate(event.occurred_at) }}</span></div></li></ul>
            </section>
          </aside>
        </div>
      </template>
    </div>
  </AppShell>
  <ConfirmModal :open="archiveConfirmationOpen" title="Excluir cliente" :message="`Deseja excluir ${props.client.name}?`" :busy="archiveForm.processing" @cancel="archiveConfirmationOpen = false" @confirm="confirmArchive" />
</template>
