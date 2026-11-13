<script setup lang="ts">
import { computed, nextTick, shallowRef, useTemplateRef } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { ArrowDownTrayIcon, ArrowPathIcon, LinkIcon, PaperAirplaneIcon, PaperClipIcon, PencilSquareIcon, PhotoIcon, TrashIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'
import AppShell from '../../Components/AppShell.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import DataState from '../../Components/DataState.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import TailAdminTable from '../../TailAdminSource/components/ui/table/Table.vue'
import { formatDateBR } from '../../utils/dateDisplay'

type QuoteItem = {
  id: number
  description_snapshot?: string
  description?: string
  quantity: string | number
  unit: string
  unit_price_cents?: number
  discount_cents?: number
  subtotal_cents?: number
}
type Attachment = { id: number; original_name: string; mime_type?: string }
type Quote = {
  id: number
  number?: string
  display_number?: string
  title?: string
  description?: string
  status: string
  client?: { id?: number; name?: string; email?: string | null }
  created_at?: string
  valid_until?: string | null
  sent_at?: string | null
  items?: QuoteItem[]
  attachments?: Attachment[]
  service?: { id: number } | null
  subtotal_cents?: number
  discount_cents?: number
  extra_cents?: number
  total_cents?: number
  public_notes?: string | null
  resend_rate_limited?: boolean
  resendRateLimited?: boolean
  resend_retry_after?: number
}

const props = defineProps<{ quote: Quote; publicUrl?: string | null; legacyLink?: boolean }>()
const page = usePage<{ flash?: { success?: string; error?: string } }>()
const sendForm = useForm({})
const resendForm = useForm({})
const linkForm = useForm({})
const attachmentForm = useForm<{ file: File | null }>({ file: null })
const removeAttachmentForm = useForm({})
const fileInput = useTemplateRef<HTMLInputElement>('quoteFileInput')
const showUpload = shallowRef(false)
const selectedFileName = shallowRef('')
const removingAttachmentId = shallowRef<number | null>(null)
const attachmentToRemove = shallowRef<number | null>(null)
const resendConfirmOpen = shallowRef(false)
const copyState = shallowRef<'idle' | 'copied' | 'error'>('idle')
const uploadStatus = shallowRef('')
const deleteStatus = shallowRef('')
const resendStatus = shallowRef('')
const items = computed(() => props.quote.items || [])
const attachments = computed(() => props.quote.attachments || [])
const publicUrl = computed(() => props.publicUrl || null)
const canEmail = computed(() => Boolean(props.quote.client?.email?.trim()))
const canResend = computed(() => ['sent', 'viewed'].includes(props.quote.status) && canEmail.value)
const rateLimited = computed(() => Boolean(props.quote.resend_rate_limited || props.quote.resendRateLimited))
const rateLimitMessage = computed(() => props.quote.resend_retry_after
  ? `Aguarde ${props.quote.resend_retry_after} segundos antes de reenviar.`
  : 'O reenvio está temporariamente limitado. Tente novamente em instantes.')
const errorMessage = computed(() => page.props.flash?.error
  || Object.values(sendForm.errors)[0]
  || Object.values(resendForm.errors)[0]
  || Object.values(linkForm.errors)[0]
  || Object.values(removeAttachmentForm.errors)[0]
  || null)
const money = (cents?: number | null) => cents === null || cents === undefined
  ? '—'
  : `R$ ${(cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const dateLabel = (value?: string | null) => formatDateBR(value, 'Não informada')

function send() {
  if (props.quote.status === 'draft' && canEmail.value) {
    sendForm.post(`/quotes/${props.quote.id}/send`, { preserveScroll: true })
  }
}
function resend() {
  if (canResend.value && !rateLimited.value) resendConfirmOpen.value = true
}
function confirmResend() {
  resendConfirmOpen.value = false
  resendStatus.value = 'Reenviando orçamento…'
  resendForm.post(`/quotes/${props.quote.id}/resend-link`, {
    preserveScroll: true,
    onSuccess: () => { resendStatus.value = 'Orçamento reenviado por e-mail.' },
    onError: () => { resendStatus.value = 'Não foi possível reenviar. Tente novamente.' },
  })
}
function createLink() {
  if (!linkForm.processing) linkForm.post(`/quotes/${props.quote.id}/public-link`, { preserveScroll: true })
}
async function copyLink() {
  if (!publicUrl.value) return
  try {
    await navigator.clipboard.writeText(publicUrl.value)
    copyState.value = 'copied'
  } catch {
    copyState.value = 'error'
  }
}
function openUpload() {
  showUpload.value = true
  nextTick(() => fileInput.value?.focus())
}
function cancelUpload() {
  showUpload.value = false
  attachmentForm.reset()
  attachmentForm.clearErrors()
  selectedFileName.value = ''
}
function selectFile(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] || null
  selectedFileName.value = file ? file.name : ''
  attachmentForm.clearErrors('file')
  attachmentForm.file = file
  if (file && file.size > 100 * 1024 * 1024) {
    attachmentForm.file = null
    attachmentForm.setError('file', 'O arquivo excede o limite de 100 MB. Escolha um arquivo menor.')
    uploadStatus.value = 'Arquivo recusado por exceder 100 MB.'
  }
}
function uploadAttachment() {
  if (!attachmentForm.file || attachmentForm.processing) return
  uploadStatus.value = 'Enviando anexo…'
  attachmentForm.post(`/quotes/${props.quote.id}/attachments`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      attachmentForm.reset()
      selectedFileName.value = ''
      uploadStatus.value = 'Anexo enviado.'
      showUpload.value = false
      if (fileInput.value) fileInput.value.value = ''
    },
    onError: () => { uploadStatus.value = 'Não foi possível enviar o anexo. Tente novamente.' },
  })
}
function removeAttachment(id: number) {
  if (!removeAttachmentForm.processing) attachmentToRemove.value = id
}
function confirmRemoveAttachment() {
  const id = attachmentToRemove.value
  if (id === null) return
  attachmentToRemove.value = null
  removingAttachmentId.value = id
  deleteStatus.value = 'Removendo anexo…'
  removeAttachmentForm.delete(`/attachments/${id}`, {
    preserveScroll: true,
    onSuccess: () => { deleteStatus.value = 'Anexo removido.' },
    onError: () => { deleteStatus.value = 'Não foi possível remover o anexo. Tente novamente.' },
    onFinish: () => { removingAttachmentId.value = null },
  })
}
</script>

<template>
  <AppShell title="Orçamento" active="quotes">
    <Head :title="props.quote.display_number || 'Orçamento'" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader :title="props.quote.display_number ? `Orçamento ${props.quote.display_number}` : 'Orçamento'" :description="props.quote.title" back-href="/quotes" back-label="Orçamentos">
        <template #actions>
          <Link v-if="props.quote.status === 'approved'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" :href="props.quote.service?.id ? `/services/${props.quote.service.id}` : `/services/create?quote=${props.quote.id}`">
            <WrenchScrewdriverIcon class="size-4" aria-hidden="true" />{{ props.quote.service?.id ? 'Ver Ordem de Serviço' : 'Criar Ordem de Serviço' }}
          </Link>
          <Link v-if="props.quote.status === 'draft'" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" :href="`/quotes/${props.quote.id}/edit`">
            <PencilSquareIcon class="size-4" aria-hidden="true" /> Editar
          </Link>
          <a v-if="canEmail" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 gap-2" :href="`/quotes/${props.quote.id}/download`"><ArrowDownTrayIcon class="size-5 shrink-0" aria-hidden="true" /> Baixar PDF</a>
          <button v-if="props.quote.status === 'draft' && canEmail" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" type="button" :disabled="sendForm.processing" :aria-busy="sendForm.processing" @click="send">
            <PaperAirplaneIcon class="size-4" aria-hidden="true" /> {{ sendForm.processing ? 'Enviando…' : 'Enviar por e-mail' }}
          </button>
          <button v-else-if="canResend" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" type="button" :disabled="resendForm.processing || rateLimited" :aria-busy="resendForm.processing" @click="resend">
            <ArrowPathIcon class="size-4" aria-hidden="true" /> {{ resendForm.processing ? 'Reenviando…' : 'Reenviar por e-mail' }}
          </button>
          <a v-else-if="!canEmail" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 gap-2" :href="`/quotes/${props.quote.id}/download`"><ArrowDownTrayIcon class="size-5 shrink-0" aria-hidden="true" /> Baixar PDF</a>
        </template>
      </PageHeader>

      <p v-if="page.props.flash?.success" class="rounded-lg border border-td-green/25 bg-td-green/5 px-4 py-3 text-sm text-td-green-ink" role="status" aria-live="polite">{{ page.props.flash.success }}</p>
      <p v-if="rateLimited" class="rounded-lg border border-td-red/30 bg-td-red/5 px-4 py-3 text-sm text-td-red" role="alert">{{ rateLimitMessage }}</p>
      <p v-if="errorMessage" class="rounded-lg border border-td-red/30 bg-td-red/5 px-4 py-3 text-sm text-td-red" role="alert">{{ errorMessage }}</p>

      <section aria-label="Resumo do orçamento" class="rounded-xl border border-gray-200 bg-white p-5 shadow-theme-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-6 border-b border-gray-100 pb-5">
          <div class="min-w-0 space-y-2">
            <StatusBadge :value="props.quote.status" />
            <p class="text-sm text-gray-500">Valor total do orçamento</p>
            <strong class="block text-3xl font-semibold tabular-nums text-gray-900">{{ money(props.quote.total_cents) }}</strong>
          </div>
          <p v-if="!canEmail" class="max-w-sm rounded-lg bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-600">Cliente sem e-mail cadastrado. Baixe o PDF ou copie o link público para compartilhar.</p>
        </div>
        <dl class="grid grid-cols-12 gap-x-6 gap-y-4 pt-5 text-sm">
          <div class="col-span-12 min-w-0 sm:col-span-6 xl:col-span-3"><dt class="text-gray-500">Cliente</dt><dd class="mt-1 break-words font-medium text-gray-900"><Link v-if="props.quote.client?.id" :href="`/clients/${props.quote.client.id}`" class="rounded-sm text-brand-600 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" :aria-label="`Ver cadastro de ${props.quote.client.name || 'cliente'}`">{{ props.quote.client.name || 'Ver cliente' }}</Link><span v-else>{{ props.quote.client?.name || 'Não informado' }}</span></dd></div>
          <div class="col-span-12 min-w-0 sm:col-span-6 xl:col-span-3"><dt class="text-gray-500">Criado em</dt><dd class="mt-1 font-medium text-gray-900">{{ dateLabel(props.quote.created_at) }}</dd></div>
          <div class="col-span-12 min-w-0 sm:col-span-6 xl:col-span-3"><dt class="text-gray-500">Válido até</dt><dd class="mt-1 font-medium text-gray-900">{{ dateLabel(props.quote.valid_until) }}</dd></div>
          <div class="col-span-12 min-w-0 sm:col-span-6 xl:col-span-3"><dt class="text-gray-500">Itens</dt><dd class="mt-1 font-medium text-gray-900">{{ items.length }}</dd></div>
        </dl>
      </section>

      <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <main class="flex min-w-0 flex-col gap-6">
          <section aria-labelledby="quote-items-title" class="min-w-0 rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 id="quote-items-title" class="text-lg font-semibold text-gray-900">Itens do orçamento</h2>
            <p v-if="props.quote.description" class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600">{{ props.quote.description }}</p>
            <DataState v-if="!items.length" kind="empty" title="Nenhum item no orçamento" description="O servidor não retornou itens para este orçamento." />
            <div v-else class="mt-5 min-w-0 overflow-x-auto focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" tabindex="0" role="region" aria-label="Itens do orçamento, tabela com rolagem horizontal">
              <TailAdminTable class="w-full min-w-[680px] border-collapse text-left text-sm [&_th]:border-b [&_th]:border-gray-100 [&_th]:px-3 [&_th]:py-3 [&_th]:text-xs [&_th]:font-medium [&_th]:text-gray-500 [&_td]:border-b [&_td]:border-gray-100 [&_td]:px-3 [&_td]:py-4 [&_td]:align-top">
                <caption class="sr-only">Descrição, quantidade, preço, desconto e total dos itens</caption>
                <thead><tr><th scope="col">Item</th><th scope="col">Qtd.</th><th scope="col">Preço unitário</th><th scope="col">Desconto</th><th scope="col">Total</th></tr></thead>
                <tbody>
                  <tr v-for="item in items" :key="item.id">
                    <td class="font-medium text-gray-900">{{ item.description_snapshot || item.description }}</td>
                    <td class="whitespace-nowrap text-gray-600">{{ item.quantity }} {{ item.unit }}</td>
                    <td class="whitespace-nowrap tabular-nums text-gray-600">{{ money(item.unit_price_cents) }}</td>
                    <td class="whitespace-nowrap tabular-nums text-gray-600">{{ money(item.discount_cents || 0) }}</td>
                    <td class="whitespace-nowrap tabular-nums font-medium text-gray-900">{{ money(item.subtotal_cents) }}</td>
                  </tr>
                </tbody>
              </TailAdminTable>
            </div>
            <dl class="ml-auto mt-5 w-full max-w-sm space-y-2 border-t border-gray-100 pt-5 text-sm">
              <div class="flex justify-between gap-4 text-gray-600"><dt>Subtotal dos itens</dt><dd class="tabular-nums">{{ money(props.quote.subtotal_cents) }}</dd></div>
              <div v-if="props.quote.discount_cents" class="flex justify-between gap-4 text-gray-600"><dt>Desconto total</dt><dd class="tabular-nums">− {{ money(props.quote.discount_cents) }}</dd></div>
              <div v-if="props.quote.extra_cents" class="flex justify-between gap-4 text-gray-600"><dt>Acréscimo</dt><dd class="tabular-nums">+ {{ money(props.quote.extra_cents) }}</dd></div>
              <div class="flex justify-between gap-4 border-t border-gray-100 pt-3 text-base font-semibold text-gray-900"><dt>Total do orçamento</dt><dd class="tabular-nums">{{ money(props.quote.total_cents) }}</dd></div>
            </dl>
          </section>
          <section v-if="props.quote.public_notes" aria-labelledby="quote-notes-title" class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 id="quote-notes-title" class="text-lg font-semibold text-gray-900">Observações para o cliente</h2>
            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600">{{ props.quote.public_notes }}</p>
          </section>
        </main>

        <aside class="flex min-w-0 flex-col gap-6">
          <section class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900">Compartilhar orçamento</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600">{{ publicUrl ? 'O link está ativo e pode ser copiado novamente.' : props.legacyLink ? 'O link antigo não pode ser recuperado. Gerar outro desativará o anterior.' : 'Gere um link para compartilhar a visualização com o cliente.' }}</p>
            <div class="mt-5 flex flex-col gap-3">
              <button v-if="!publicUrl" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" type="button" :disabled="linkForm.processing" @click="createLink">{{ linkForm.processing ? 'Gerando…' : props.legacyLink ? 'Gerar novo link' : 'Gerar link público' }}</button>
              <button v-else class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" type="button" @click="copyLink"><LinkIcon class="size-4" aria-hidden="true" />{{ copyState === 'copied' ? 'Copiar novamente' : 'Copiar link' }}</button>
              <Link class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-brand-500 hover:bg-brand-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" :href="`/quotes/${props.quote.id}/versions`"><ArrowPathIcon class="size-4" aria-hidden="true" />Versões e histórico</Link>
            </div>
            <p v-if="copyState === 'copied'" class="mt-3 text-sm text-td-green-ink" role="status">Link copiado.</p>
            <p v-if="copyState === 'error'" class="mt-3 text-sm text-td-red" role="alert">Não foi possível copiar o link.</p>
          </section>

          <section id="quote-attachments" class="rounded-xl border border-gray-200 bg-white p-4 shadow-theme-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div><h2 class="text-lg font-semibold text-gray-900">Anexos</h2><p class="mt-1 text-sm text-gray-500">{{ attachments.length }} {{ attachments.length === 1 ? 'arquivo anexado' : 'arquivos anexados' }}</p></div>
              <button v-if="!showUpload" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" type="button" @click="openUpload"><PaperClipIcon class="size-4" aria-hidden="true" />Anexar arquivo</button>
            </div>
            <form v-if="showUpload" class="mt-5 grid grid-cols-12 gap-3 rounded-lg bg-gray-50 p-4 [&>*]:col-span-12" @submit.prevent="uploadAttachment">
              <label for="quote-file" class="text-sm font-medium text-gray-800">Escolha uma imagem, vídeo ou arquivo</label>
              <input id="quote-file" ref="quoteFileInput" class="min-h-11 w-full min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-800 file:mr-3 file:border-0 file:bg-gray-50 file:px-2 file:py-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime,application/pdf,text/plain" @change="selectFile" />
              <p class="text-xs text-gray-500">{{ selectedFileName || 'Limite de 100 MB por arquivo.' }}</p>
              <p v-if="attachmentForm.errors.file" class="text-sm text-td-red" role="alert">{{ attachmentForm.errors.file }}</p>
              <div class="flex flex-wrap gap-2">
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 disabled:opacity-50" type="submit" :disabled="!attachmentForm.file || attachmentForm.processing" :aria-busy="attachmentForm.processing"><PhotoIcon class="mr-2 size-4" aria-hidden="true" />{{ attachmentForm.processing ? 'Enviando…' : 'Enviar anexo' }}</button>
                <button class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500" type="button" :disabled="attachmentForm.processing" @click="cancelUpload">Cancelar</button>
              </div>
            </form>
            <ul v-if="attachments.length" class="mt-5 divide-y divide-gray-100">
              <li v-for="attachment in attachments" :key="attachment.id" class="py-4 first:pt-0 last:pb-0">
                <div class="flex min-w-0 items-start gap-2">
                  <PaperClipIcon class="mt-1 size-4 shrink-0 text-gray-500" aria-hidden="true" />
                  <a class="min-w-0 flex-1 break-words text-sm font-medium text-brand-500 hover:underline" :href="`/attachments/${attachment.id}/view`" target="_blank" rel="noopener noreferrer">{{ attachment.original_name }}</a>
                  <button class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-lg text-td-red hover:bg-td-red/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-red disabled:opacity-50" type="button" :aria-label="`Remover ${attachment.original_name}`" :disabled="removeAttachmentForm.processing" @click="removeAttachment(attachment.id)"><TrashIcon class="size-4" aria-hidden="true" /></button>
                </div>
                <img v-if="attachment.mime_type?.startsWith('image/')" class="mt-3 max-h-56 w-full rounded-lg bg-gray-50 object-contain" :src="`/attachments/${attachment.id}/view`" :alt="attachment.original_name" loading="lazy" />
                <video v-else-if="attachment.mime_type?.startsWith('video/')" class="mt-3 max-h-56 w-full rounded-lg bg-black" :src="`/attachments/${attachment.id}/view`" controls preload="metadata">Seu navegador não suporta vídeo.</video>
              </li>
            </ul>
            <p v-else class="mt-5 text-sm text-gray-500">Nenhum anexo adicionado.</p>
          </section>
        </aside>
      </div>
      <div class="sr-only" role="status" aria-live="polite">{{ copyState === 'copied' ? 'Link copiado.' : resendStatus || uploadStatus || deleteStatus }}</div>
    </div>
  </AppShell>
  <ConfirmModal :open="resendConfirmOpen" title="Reenviar orçamento" message="Deseja reenviar este orçamento por e-mail ao cliente?" :busy="resendForm.processing" @cancel="resendConfirmOpen = false" @confirm="confirmResend" />
  <ConfirmModal :open="attachmentToRemove !== null" title="Excluir anexo" message="Deseja excluir este anexo?" :busy="removeAttachmentForm.processing" @cancel="attachmentToRemove = null" @confirm="confirmRemoveAttachment" />
</template>
