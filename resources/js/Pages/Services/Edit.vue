<script setup lang="ts">
import { computed, nextTick, reactive, shallowRef, useTemplateRef, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { PhotoIcon, TrashIcon } from '@heroicons/vue/24/outline'
import AppShell from '../../Components/AppShell.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import StatusBadge from '../../Components/StatusBadge.vue'
import TdDatePicker from '../../Components/TdDatePicker.vue'
import { formatDateTimeBR } from '../../utils/dateDisplay'
import { addDays, addMonthsClamped, saoPauloToday } from '../../utils/dateLimits'
import { serviceStatusOptions } from '../../utils/displayLabels'
import ServiceImageGallery from './ServiceImageGallery.vue'

type Task = { id: number; title: string; description?: string | null; status: string }
type Note = { id: number; body: string; created_at?: string | null }
type Occurrence = { id: number; description: string; occurred_at?: string | null }
type Attachment = { id: number; original_name: string; mime_type?: string | null }
type Service = { id: number; title: string; description?: string | null; status: string; scheduled_start?: string | null; scheduled_end?: string | null; tasks?: Task[]; notes?: Note[]; occurrences?: Occurrence[]; attachments?: Attachment[] }
const props = withDefaults(defineProps<{ service: Service; tasks?: Task[]; notes?: Note[]; occurrences?: Occurrence[]; attachments?: Attachment[]; availableStatuses?: string[] }>(), { tasks: () => [], notes: () => [], occurrences: () => [], attachments: () => [], availableStatuses: () => [] })
const page = usePage<{ flash?: { success?: string } }>()
const tasks = computed(() => props.tasks.length ? props.tasks : props.service.tasks || [])
const notes = computed(() => props.notes.length ? props.notes : props.service.notes || [])
const occurrences = computed(() => props.occurrences.length ? props.occurrences : props.service.occurrences || [])
const attachments = computed(() => props.attachments.length ? props.attachments : props.service.attachments || [])
const today = saoPauloToday()
const latestStart = addDays(today, 90)
const originalStart = props.service.scheduled_start?.slice(0, 10) || ''
const originalEnd = props.service.scheduled_end?.slice(0, 10) || ''
const form = useForm({ title: props.service.title, description: props.service.description || '', scheduled_start: originalStart, scheduled_end: originalEnd })
const requiredIncomplete = computed(() => !form.title.trim())
const latestEnd = computed(() => form.scheduled_start ? addMonthsClamped(form.scheduled_start, 6) : undefined)
const pickerStartMin = computed(() => form.scheduled_start === originalStart && originalStart < today ? originalStart : today)
const pickerStartMax = computed(() => form.scheduled_start === originalStart && originalStart > latestStart ? originalStart : latestStart)
const pickerEndMin = computed(() => form.scheduled_end === originalEnd && originalEnd < (form.scheduled_start || today) ? originalEnd : (form.scheduled_start || today))
const pickerEndMax = computed(() => form.scheduled_end === originalEnd && latestEnd.value && originalEnd > latestEnd.value ? originalEnd : latestEnd.value)
const endDateInput = useTemplateRef<{ focus: () => void }>('endDateInput')
watch(() => form.scheduled_start, () => { form.scheduled_end = ''; form.clearErrors('scheduled_start', 'scheduled_end') })
async function saveDetails() {
  const currentToday = saoPauloToday()
  if (form.scheduled_start && form.scheduled_start !== originalStart && (form.scheduled_start < currentToday || form.scheduled_start > addDays(currentToday, 90))) {
    form.setError('scheduled_start', 'O início deve ser entre hoje e os próximos 90 dias.')
    return
  }
  if (form.scheduled_end && form.scheduled_end !== originalEnd && (!form.scheduled_start || form.scheduled_end < form.scheduled_start || form.scheduled_end > addMonthsClamped(form.scheduled_start, 6))) {
    form.setError('scheduled_end', 'O fim deve ser entre o início e seis meses depois.')
    await nextTick()
    endDateInput.value?.focus()
    return
  }
  form.clearErrors('scheduled_start', 'scheduled_end')
  form.patch(`/services/${props.service.id}`)
}

const statusOptions = computed(() => serviceStatusOptions.filter((option) => props.availableStatuses.includes(option.value)))
const statusForm = useForm({ status: '' })
const statusConfirmationOpen = shallowRef(false)
function changeStatus() {
  if (!statusForm.status || !statusOptions.value.some((option) => option.value === statusForm.status) || statusForm.processing) return
  if (statusForm.status === 'completed') {
    router.get(`/services/${props.service.id}/billing`)
    return
  }
  statusConfirmationOpen.value = true
}
function cancelStatus() { statusConfirmationOpen.value = false; statusForm.status = '' }
function confirmStatus() { statusConfirmationOpen.value = false; statusForm.patch(`/services/${props.service.id}/status`, { preserveScroll: true }) }

const taskForm = useForm({ title: '', description: '', position: tasks.value.length })
function addTask() { taskForm.post(`/services/${props.service.id}/tasks`, { preserveScroll: true, onSuccess: () => taskForm.reset('title', 'description') }) }
const taskState = reactive<Record<number, { processing: boolean; error: string }>>({})
function toggleTask(task: Task, event: Event) {
  const checked = (event.target as HTMLInputElement).checked
  const state = taskState[task.id] || (taskState[task.id] = { processing: false, error: '' })
  if (state.processing) return
  state.processing = true
  state.error = ''
  router.patch(`/services/${props.service.id}/tasks/${task.id}/status`, { status: checked ? 'completed' : 'pending' }, {
    preserveScroll: true,
    onError: (errors) => { state.error = Object.values(errors)[0] || 'Não foi possível atualizar a tarefa.'; (event.target as HTMLInputElement).checked = !checked },
    onFinish: () => { state.processing = false },
  })
}
const noteForm = useForm({ body: '' })
function addNote() { noteForm.post(`/services/${props.service.id}/notes`, { preserveScroll: true, onSuccess: () => noteForm.reset() }) }
const occurrenceForm = useForm({ description: '' })
function addOccurrence() { occurrenceForm.post(`/services/${props.service.id}/occurrences`, { preserveScroll: true, onSuccess: () => occurrenceForm.reset() }) }

const fileForm = useForm<{ file: File | null }>({ file: null })
const fileInput = useTemplateRef<HTMLInputElement>('fileInput')
const imageError = shallowRef('')
function selectImage(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0] || null
  imageError.value = ''
  fileForm.clearErrors('file')
  if (file && !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) imageError.value = 'Selecione uma imagem JPG, PNG ou WebP.'
  else if (file && file.size > 10 * 1024 * 1024) imageError.value = 'A imagem deve ter no máximo 10 MB.'
  fileForm.file = imageError.value ? null : file
}
function uploadImage() {
  if (!fileForm.file || fileForm.processing) return
  fileForm.post(`/services/${props.service.id}/attachments`, { forceFormData: true, preserveScroll: true, onSuccess: () => { fileForm.reset(); if (fileInput.value) fileInput.value.value = '' } })
}
const attachmentToRemove = shallowRef<number | null>(null)
const removeAttachmentForm = useForm({})
function confirmRemoveAttachment() {
  if (attachmentToRemove.value === null) return
  const id = attachmentToRemove.value
  attachmentToRemove.value = null
  removeAttachmentForm.delete(`/attachments/${id}`, { preserveScroll: true })
}
const operationError = computed(() => Object.values(statusForm.errors)[0] || Object.values(taskForm.errors)[0] || Object.values(noteForm.errors)[0] || Object.values(occurrenceForm.errors)[0] || Object.values(fileForm.errors)[0] || Object.values(removeAttachmentForm.errors)[0] || imageError.value)
</script>

<template>
  <AppShell title="Editar serviço" active="services">
    <Head :title="`Editar ${props.service.title}`" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Editar serviço" :description="props.service.title" back-href="/services" back-label="Serviços">
        <template #actions><Link :href="`/services/${props.service.id}`" class="inline-flex min-h-11 items-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm font-medium text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-td-primary">Visualizar serviço</Link></template>
      </PageHeader>
      <p v-if="page.props.flash?.success" role="status" class="rounded-lg border border-td-green/25 bg-td-green/5 px-4 py-3 text-sm text-td-green-ink">{{ page.props.flash.success }}</p>
      <p v-if="operationError" role="alert" class="rounded-lg border border-td-red/30 bg-td-red/5 px-4 py-3 text-sm text-td-red">{{ operationError }}</p>
      <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
        <div class="min-w-0 space-y-6">
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-edit-heading">
            <h2 id="service-edit-heading" class="text-lg font-semibold text-td-ink">Dados do serviço</h2>
            <p class="mt-1 text-sm text-td-body">Atualize o título, a descrição e as datas previstas.</p>
            <FormErrorSummary :errors="form.errors" class="mt-4" />
            <form class="mt-5 grid grid-cols-12 gap-5" @submit.prevent="saveDetails">
              <div class="col-span-12"><label for="edit-service-title" class="mb-2 block text-sm font-medium text-td-ink">Título <span class="text-td-red" aria-hidden="true">*</span></label><input id="edit-service-title" v-model="form.title" required maxlength="255" class="h-11 w-full rounded-lg border border-td-border px-3.5 text-td-ink focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15" /><p v-if="form.errors.title" class="mt-1 text-sm text-td-red">{{ form.errors.title }}</p></div>
              <div class="col-span-12 sm:col-span-6"><label for="edit-service-start" class="mb-2 block text-sm font-medium text-td-ink">Início previsto</label><TdDatePicker id="edit-service-start" v-model="form.scheduled_start" label="Início previsto" :min="pickerStartMin" :max="pickerStartMax" /><p class="mt-1 text-xs text-td-body">De hoje até 90 dias à frente.</p><p v-if="form.errors.scheduled_start" class="mt-1 text-sm text-td-red">{{ form.errors.scheduled_start }}</p></div>
              <div class="col-span-12 sm:col-span-6"><label for="edit-service-end" class="mb-2 block text-sm font-medium text-td-ink">Fim previsto</label><TdDatePicker id="edit-service-end" ref="endDateInput" v-model="form.scheduled_end" label="Fim previsto" :disabled="!form.scheduled_start" :min="pickerEndMin" :max="pickerEndMax" /><p class="mt-1 text-xs text-td-body">{{ form.scheduled_start ? 'Do início até seis meses depois.' : 'Escolha o início para habilitar.' }}</p><p v-if="form.errors.scheduled_end" class="mt-1 text-sm text-td-red">{{ form.errors.scheduled_end }}</p></div>
              <div class="col-span-12"><label for="edit-service-description" class="mb-2 block text-sm font-medium text-td-ink">Descrição</label><textarea id="edit-service-description" v-model="form.description" class="min-h-28 w-full resize-y rounded-lg border border-td-border px-3.5 py-3 text-td-ink focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15" /><p v-if="form.errors.description" class="mt-1 text-sm text-td-red">{{ form.errors.description }}</p></div>
              <div class="col-span-12 flex flex-wrap justify-end gap-3 border-t border-td-border pt-5"><Link :href="`/services/${props.service.id}`" class="inline-flex min-h-11 items-center rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink">Cancelar</Link><SaveActionButton label="Salvar alterações" :required-incomplete="requiredIncomplete" :processing="form.processing" /></div>
            </form>
          </section>
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-checklist-heading">
            <h2 id="service-checklist-heading" class="text-lg font-semibold text-td-ink">Checklist</h2>
            <p class="mt-1 text-sm text-td-body">Marque as tarefas concluídas e adicione novas etapas.</p>
            <ul v-if="tasks.length" class="mt-4 divide-y divide-td-border"><li v-for="task in tasks" :key="task.id" class="py-3"><label class="flex min-h-11 items-center gap-3 text-td-ink"><input type="checkbox" class="size-5 shrink-0 accent-td-primary" :checked="task.status === 'completed'" :disabled="taskState[task.id]?.processing" @change="toggleTask(task, $event)" /><span class="min-w-0 flex-1">{{ task.title }}</span><StatusBadge :value="task.status" /></label><p v-if="task.description" class="pl-8 text-sm text-td-body">{{ task.description }}</p><p v-if="taskState[task.id]?.error" role="alert" class="pl-8 text-sm text-td-red">{{ taskState[task.id]?.error }}</p></li></ul>
            <p v-else class="mt-4 text-sm text-td-body">Nenhuma tarefa cadastrada.</p>
            <form class="mt-5 grid grid-cols-12 gap-4 border-t border-td-border pt-5" @submit.prevent="addTask"><div class="col-span-12 sm:col-span-6"><label for="new-task-title" class="mb-2 block text-sm font-medium text-td-ink">Nova tarefa</label><input id="new-task-title" v-model="taskForm.title" required maxlength="255" class="h-11 w-full rounded-lg border border-td-border px-3 text-td-ink" placeholder="Ex.: Comprar materiais" /></div><div class="col-span-12 sm:col-span-6"><label for="new-task-description" class="mb-2 block text-sm font-medium text-td-ink">Detalhes</label><input id="new-task-description" v-model="taskForm.description" class="h-11 w-full rounded-lg border border-td-border px-3 text-td-ink" placeholder="Opcional" /></div><div class="col-span-12"><button type="submit" :disabled="taskForm.processing || !taskForm.title.trim()" class="min-h-11 rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink hover:border-td-primary disabled:opacity-50">Adicionar tarefa</button></div></form>
          </section>
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-notes-heading"><h2 id="service-notes-heading" class="text-lg font-semibold text-td-ink">Notas e ocorrências</h2><p class="mt-1 text-sm text-td-body">Registre o andamento e os fatos relevantes.</p>
            <div class="mt-5 grid grid-cols-12 gap-5">
              <form class="col-span-12 sm:col-span-6" @submit.prevent="addNote"><label for="service-note" class="mb-2 block text-sm font-medium text-td-ink">Nova nota</label><textarea id="service-note" v-model="noteForm.body" required class="min-h-28 w-full resize-y rounded-lg border border-td-border px-3 py-2 text-td-ink" /><button type="submit" :disabled="noteForm.processing || !noteForm.body.trim()" class="mt-2 min-h-11 rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink disabled:opacity-50">Adicionar nota</button></form>
              <form class="col-span-12 sm:col-span-6" @submit.prevent="addOccurrence"><label for="service-occurrence" class="mb-2 block text-sm font-medium text-td-ink">Nova ocorrência</label><textarea id="service-occurrence" v-model="occurrenceForm.description" required class="min-h-28 w-full resize-y rounded-lg border border-td-border px-3 py-2 text-td-ink" /><button type="submit" :disabled="occurrenceForm.processing || !occurrenceForm.description.trim()" class="mt-2 min-h-11 rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink disabled:opacity-50">Adicionar ocorrência</button></form>
            </div>
            <div v-if="notes.length || occurrences.length" class="mt-5 grid grid-cols-12 gap-5 border-t border-td-border pt-5"><div class="col-span-12 sm:col-span-6"><h3 class="text-sm font-semibold text-td-ink">Notas registradas</h3><ul class="mt-2 space-y-3"><li v-for="note in notes" :key="note.id" class="rounded-lg bg-td-soft p-3"><p class="break-words text-sm text-td-ink">{{ note.body }}</p><time class="mt-1 block text-xs text-td-body">{{ formatDateTimeBR(note.created_at) }}</time></li></ul><p v-if="!notes.length" class="mt-2 text-sm text-td-body">Nenhuma nota.</p></div><div class="col-span-12 sm:col-span-6"><h3 class="text-sm font-semibold text-td-ink">Ocorrências registradas</h3><ul class="mt-2 space-y-3"><li v-for="occurrence in occurrences" :key="occurrence.id" class="rounded-lg bg-td-soft p-3"><p class="break-words text-sm text-td-ink">{{ occurrence.description }}</p><time class="mt-1 block text-xs text-td-body">{{ formatDateTimeBR(occurrence.occurred_at) }}</time></li></ul><p v-if="!occurrences.length" class="mt-2 text-sm text-td-body">Nenhuma ocorrência.</p></div></div>
          </section>
        </div>
        <aside class="min-w-0 space-y-6">
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-status-heading"><h2 id="service-status-heading" class="text-lg font-semibold text-td-ink">Situação</h2><div class="mt-4 flex flex-wrap items-center gap-3"><StatusBadge :value="props.service.status" /><FormSelect v-if="statusOptions.length" v-model="statusForm.status" aria-label="Alterar situação" :disabled="statusForm.processing" @change="changeStatus"><option value="" disabled>Alterar situação</option><option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option></FormSelect></div><p v-if="!statusOptions.length" class="mt-3 text-sm text-td-body">Este serviço está finalizado e não permite alterar a situação.</p></section>
          <ServiceImageGallery :attachments="attachments" />
          <section class="rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6" aria-labelledby="service-images-edit-heading"><h2 id="service-images-edit-heading" class="text-lg font-semibold text-td-ink">Gerenciar imagens</h2><form class="mt-4" @submit.prevent="uploadImage"><label for="service-image" class="mb-2 block text-sm font-medium text-td-ink">Adicionar imagem</label><input id="service-image" ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full min-w-0 rounded-lg border border-td-border text-sm file:mr-3 file:min-h-11 file:border-0 file:bg-td-soft file:px-3 file:text-td-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" @change="selectImage" /><p class="mt-2 text-xs text-td-body">JPG, PNG ou WebP, até 10 MB.</p><p v-if="imageError || fileForm.errors.file" role="alert" class="mt-1 text-sm text-td-red">{{ imageError || fileForm.errors.file }}</p><button type="submit" :disabled="!fileForm.file || fileForm.processing" class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg border border-td-border px-4 text-sm font-medium text-td-ink hover:border-td-primary disabled:opacity-50"><PhotoIcon class="size-5" aria-hidden="true" />{{ fileForm.processing ? 'Enviando…' : 'Enviar imagem' }}</button></form><ul v-if="attachments.length" class="mt-5 divide-y divide-td-border border-t border-td-border"><li v-for="attachment in attachments" :key="attachment.id" class="flex min-w-0 items-center justify-between gap-2 py-2"><span class="min-w-0 truncate text-sm text-td-ink">{{ attachment.original_name }}</span><button type="button" class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded px-2 text-sm text-td-red focus-visible:outline-2 focus-visible:outline-td-primary" :aria-label="`Remover ${attachment.original_name}`" @click="attachmentToRemove = attachment.id"><TrashIcon class="size-4" aria-hidden="true" />Remover</button></li></ul></section>
        </aside>
      </div>
    </div>
  </AppShell>
  <ConfirmModal :open="statusConfirmationOpen" title="Confirmar alteração" message="Deseja alterar a situação deste serviço?" :busy="statusForm.processing" @cancel="cancelStatus" @confirm="confirmStatus" />
  <ConfirmModal :open="attachmentToRemove !== null" title="Excluir imagem" message="Deseja excluir esta imagem?" :busy="removeAttachmentForm.processing" @cancel="attachmentToRemove = null" @confirm="confirmRemoveAttachment" />
</template>
