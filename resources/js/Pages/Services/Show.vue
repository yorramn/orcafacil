<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { CalendarDaysIcon, MapPinIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'
import AppShell from '../../Components/AppShell.vue'
import PageHeader from '../../Components/PageHeader.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import { formatCalendarDateBR, formatDateTimeBR } from '../../utils/dateDisplay'
import ServiceImageGallery from './ServiceImageGallery.vue'

type Attachment = { id: number; original_name: string; mime_type?: string | null }
type Entry = { id: string | number; type?: string; description?: string | null; event?: string | null; status?: string | null; occurred_at?: string | null; created_at?: string | null; properties?: { status?: string } | null }
type Service = { id: number; title: string; description?: string | null; status: string; contracted_total_cents?: number | null; scheduled_start?: string | null; scheduled_end?: string | null; address_snapshot_json?: Record<string, string | null>; client?: { name?: string } | null; assignee?: { name?: string } | null; quote?: { id: number; display_number?: string } | null; attachments?: Attachment[] }
const props = withDefaults(defineProps<{ service: Service; attachments?: Attachment[]; history?: Entry[]; canEdit?: boolean; canBill?: boolean }>(), { attachments: () => [], history: () => [], canEdit: false, canBill: false })
const attachments = computed(() => props.attachments.length ? props.attachments : props.service.attachments || [])
const address = computed(() => Object.values(props.service.address_snapshot_json || {}).filter(Boolean).join(', '))
const money = computed(() => props.service.contracted_total_cents == null ? '—' : `R$ ${(props.service.contracted_total_cents / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`)
const timeline = computed(() => props.history.filter((entry) => entry.type !== 'task').sort((a, b) => new Date(b.occurred_at || b.created_at || 0).getTime() - new Date(a.occurred_at || a.created_at || 0).getTime()))
const statusLabels: Record<string, string> = { planned: 'planejado', in_progress: 'em andamento', paused: 'pausado', completed: 'concluído', canceled: 'cancelado' }
function timelineLabel(entry: Entry): string {
  if (entry.type === 'activity') {
    if (entry.event === 'service.status_changed') return `Status alterado para ${statusLabels[entry.properties?.status || ''] || 'atualizado'}.`
    const labels: Record<string, string> = { 'service.task_completed': 'Tarefa concluída.', 'service.task_reopened': 'Tarefa reaberta.', 'service.attachment_deleted': 'Imagem removida.', 'service.image_uploaded': 'Imagem adicionada ao serviço.', 'service.created': 'Serviço criado.', 'service.updated': 'Dados do serviço atualizados.', 'service.billed': 'Serviço faturado.' }
    return labels[entry.event || ''] || 'Serviço atualizado.'
  }
  if (entry.type === 'note') return `Nota: ${entry.description || 'sem texto'}`
  if (entry.type === 'occurrence') return `Ocorrência: ${entry.description || 'sem detalhes'}`
  return 'Atualização do serviço.'
}
</script>

<template>
  <AppShell title="Detalhe do serviço" active="services">
    <Head :title="props.service.title" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader :title="props.service.title" :description="props.service.description || 'Acompanhe os dados e o andamento deste serviço.'" back-href="/services" back-label="Serviços">
        <template #actions>
          <StatusBadge :value="props.service.status" />
          <Link v-if="props.canBill" :href="`/services/${props.service.id}/billing`" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary">{{ props.service.status === 'completed' ? 'Faturar serviço' : 'Concluir e faturar' }}</Link>
          <Link v-if="props.canEdit" :href="`/services/${props.service.id}/edit`" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-td-primary bg-td-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-td-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary"><PencilSquareIcon class="size-5" aria-hidden="true" />Editar serviço</Link>
        </template>
      </PageHeader>
      <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <div class="flex min-w-0 flex-col gap-6">
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-summary-heading">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4 border-b border-td-border pb-4"><div><h2 id="service-summary-heading" class="text-lg font-semibold text-td-ink">Resumo do serviço</h2><p class="mt-1 text-sm text-td-body">Informações da contratação e prazo previsto.</p></div><div class="text-left sm:text-right"><span class="text-sm text-td-body">Valor contratado</span><p class="text-2xl font-semibold text-td-ink">{{ money }}</p></div></div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
              <div><dt class="text-sm text-td-body">Cliente</dt><dd class="mt-1 font-medium text-td-ink">{{ props.service.client?.name || 'Não informado' }}</dd></div>
              <div><dt class="text-sm text-td-body">Responsável</dt><dd class="mt-1 font-medium text-td-ink">{{ props.service.assignee?.name || 'Não atribuído' }}</dd></div>
              <div><dt class="flex items-center gap-1 text-sm text-td-body"><CalendarDaysIcon class="size-4" aria-hidden="true" />Início previsto</dt><dd class="mt-1 font-medium text-td-ink">{{ formatCalendarDateBR(props.service.scheduled_start) }}</dd></div>
              <div><dt class="flex items-center gap-1 text-sm text-td-body"><CalendarDaysIcon class="size-4" aria-hidden="true" />Fim previsto</dt><dd class="mt-1 font-medium text-td-ink">{{ formatCalendarDateBR(props.service.scheduled_end) }}</dd></div>
              <div v-if="props.service.quote"><dt class="text-sm text-td-body">Orçamento de origem</dt><dd class="mt-1"><Link :href="`/quotes/${props.service.quote.id}`" class="font-medium text-td-primary underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-td-primary">{{ props.service.quote.display_number || `Orçamento ${props.service.quote.id}` }}</Link></dd></div>
              <div v-if="address" class="sm:col-span-2"><dt class="flex items-center gap-1 text-sm text-td-body"><MapPinIcon class="size-4" aria-hidden="true" />Endereço do serviço</dt><dd class="mt-1 break-words font-medium text-td-ink">{{ address }}</dd></div>
            </dl>
          </section>
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-timeline-heading"><h2 id="service-timeline-heading" class="text-lg font-semibold text-td-ink">Linha do tempo</h2><p v-if="!timeline.length" class="mt-4 text-sm text-td-body">Nenhum registro no histórico.</p><ol v-else class="mt-4 divide-y divide-td-border"><li v-for="entry in timeline" :key="entry.id" class="flex gap-3 py-4"><span class="mt-2 size-2 shrink-0 rounded-full bg-td-primary" aria-hidden="true" /><div class="min-w-0"><p class="break-words font-medium text-td-ink">{{ timelineLabel(entry) }}</p><time v-if="entry.occurred_at || entry.created_at" class="mt-1 block text-sm text-td-body" :datetime="entry.occurred_at || entry.created_at || undefined">{{ formatDateTimeBR(entry.occurred_at || entry.created_at) }}</time></div></li></ol></section>
        </div>
        <ServiceImageGallery :attachments="attachments" />
      </div>
    </div>
  </AppShell>
</template>
