<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, nextTick, shallowRef, useTemplateRef, watch } from 'vue'
import AppShell from '../../Components/AppShell.vue'
import FormErrorSummary from '../../Components/FormErrorSummary.vue'
import FormSelect from '../../Components/FormSelect.vue'
import PageHeader from '../../Components/PageHeader.vue'
import SaveActionButton from '../../Components/SaveActionButton.vue'
import TdDatePicker from '../../Components/TdDatePicker.vue'
import { addDays, addMonthsClamped, saoPauloToday } from '../../utils/dateLimits'

type Quote = { id: string | number; display_number?: string; title?: string; description?: string | null }
const props = withDefaults(defineProps<{ quotes?: Quote[]; selectedQuoteId?: number | null }>(), { quotes: () => [], selectedQuoteId: null })
const initialQuote = props.quotes.find((quote) => Number(quote.id) === props.selectedQuoteId)
const form = useForm<{ quote_id: string; title: string; description: string; scheduled_start: string; scheduled_end: string; images: File[] }>({ quote_id: initialQuote ? String(initialQuote.id) : '', title: initialQuote?.title || '', description: initialQuote?.description || '', scheduled_start: '', scheduled_end: '', images: [] })
const imageInput = useTemplateRef<HTMLInputElement>('imageInput')
const imageError = shallowRef('')
const maxImageBytes = 10 * 1024 * 1024
function selectImages(event: Event) {
  const files = Array.from((event.target as HTMLInputElement).files || [])
  imageError.value = ''
  form.clearErrors('images')
  if (files.length > 10) imageError.value = 'Selecione no máximo 10 imagens.'
  else if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type))) imageError.value = 'Selecione somente imagens JPG, PNG ou WebP.'
  else if (files.some((file) => file.size > maxImageBytes)) imageError.value = 'Cada imagem deve ter no máximo 10 MB.'
  form.images = imageError.value ? [] : files
  if (imageError.value && imageInput.value) imageInput.value.value = ''
}
function removeImage(index: number) {
  form.images = form.images.filter((_, position) => position !== index)
  if (!form.images.length && imageInput.value) imageInput.value.value = ''
}
const requiredIncomplete = computed(() => !props.quotes.length || !form.quote_id || !form.title.trim())
const endDateInput = useTemplateRef<{ focus: () => void }>('endDateInput')
const today = saoPauloToday()
const latestStart = addDays(today, 90)
const latestEnd = computed(() => form.scheduled_start ? addMonthsClamped(form.scheduled_start, 6) : undefined)
watch(() => form.scheduled_start, () => {
  form.scheduled_end = ''
  form.clearErrors('scheduled_start', 'scheduled_end')
})
watch(() => form.quote_id, (id) => {
  const quote = props.quotes.find((item) => Number(item.id) === Number(id))
  form.title = quote?.title || ''
  form.description = quote?.description || ''
})

async function submit() {
  if (imageError.value) return
  const currentToday = saoPauloToday()
  if (form.scheduled_start && (form.scheduled_start < currentToday || form.scheduled_start > addDays(currentToday, 90))) {
    form.setError('scheduled_start', 'O início deve ser entre hoje e os próximos 90 dias.')
    return
  }
  if (form.scheduled_end && (!form.scheduled_start || form.scheduled_end < form.scheduled_start || form.scheduled_end > addMonthsClamped(form.scheduled_start, 6))) {
    form.setError('scheduled_end', 'O fim deve ser entre o início e seis meses depois.')
    await nextTick()
    endDateInput.value?.focus()
    return
  }

  form.clearErrors('scheduled_start', 'scheduled_end')
  form.post('/services', { forceFormData: true })
}
</script>

<template>
  <AppShell title="Novo serviço" active="services">
    <Head title="Novo serviço" />
    <div class="page-content min-w-0 space-y-6 p-4 sm:p-6 2xl:p-11">
      <PageHeader title="Novo serviço" description="Converta um orçamento aprovado em serviço e acompanhe sua execução.">
        <template #actions><Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/services">Cancelar</Link></template>
      </PageHeader>
      <FormErrorSummary :errors="form.errors" />
      <form class="grid min-w-0 grid-cols-12 rounded-xl border border-td-border bg-white p-4 shadow-td-card sm:p-6 [&>*]:col-span-12" @submit.prevent="submit">
        <div class="mb-5 border-b border-td-border pb-4">
          <h2 class="text-base font-semibold text-td-ink">Dados do serviço</h2>
          <p class="mt-1 text-sm text-td-body">Selecione o orçamento e informe os detalhes da execução.</p>
        </div>
        <div class="grid min-w-0 grid-cols-12 gap-5">
          <div class="col-span-12 min-w-0">
            <label for="service-quote" class="mb-2 block text-sm font-medium text-td-ink">Orçamento aprovado <span class="text-td-red" aria-hidden="true">*</span></label>
            <FormSelect id="service-quote" v-model="form.quote_id" required label="orçamento aprovado" placeholder="Selecione um orçamento" :aria-invalid="Boolean(form.errors.quote_id)" :aria-describedby="form.errors.quote_id ? 'service-quote-error' : undefined"><option v-for="quote in props.quotes" :key="quote.id" :value="quote.id">{{ quote.display_number || `Orçamento ${quote.id}` }} · {{ quote.title || 'Sem título' }}</option></FormSelect>
            <p v-if="form.errors.quote_id" id="service-quote-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.quote_id }}</p>
            <p v-else-if="!props.quotes.length" class="mt-1.5 text-sm text-td-body">Não há orçamentos aprovados disponíveis.</p>
          </div>
          <div class="col-span-12 min-w-0 sm:col-span-6">
            <label for="service-title" class="mb-2 block text-sm font-medium text-td-ink">Título <span class="text-td-red" aria-hidden="true">*</span></label>
            <input id="service-title" v-model="form.title" required placeholder="Ex.: Reforma do banheiro" :aria-invalid="Boolean(form.errors.title)" :aria-describedby="form.errors.title ? 'service-title-error' : undefined" class="h-11 w-full min-w-0 rounded-lg border border-td-border bg-white px-3.5 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
            <p v-if="form.errors.title" id="service-title-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.title }}</p>
          </div>
          <div class="col-span-12 min-w-0 sm:col-span-3">
            <label for="service-start" class="mb-2 block text-sm font-medium text-td-ink">Início previsto <span class="font-normal text-td-body">(opcional)</span></label>
            <TdDatePicker id="service-start" v-model="form.scheduled_start" label="Início previsto" :min="today" :max="latestStart" :aria-invalid="Boolean(form.errors.scheduled_start)" :aria-describedby="form.errors.scheduled_start ? 'service-start-error' : 'service-start-help'" />
            <p id="service-start-help" class="mt-1.5 text-xs text-td-body">De hoje até 90 dias à frente.</p>
            <p v-if="form.errors.scheduled_start" id="service-start-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.scheduled_start }}</p>
          </div>
          <div class="col-span-12 min-w-0 sm:col-span-3">
            <label for="service-end" class="mb-2 block text-sm font-medium text-td-ink">Fim previsto <span class="font-normal text-td-body">(opcional)</span></label>
            <TdDatePicker id="service-end" ref="endDateInput" v-model="form.scheduled_end" label="Fim previsto" :disabled="!form.scheduled_start" :min="form.scheduled_start || today" :max="latestEnd" :aria-invalid="Boolean(form.errors.scheduled_end)" :aria-describedby="form.errors.scheduled_end ? 'service-end-error' : 'service-end-help'" />
            <p id="service-end-help" class="mt-1.5 text-xs text-td-body">{{ form.scheduled_start ? 'Do início até seis meses depois.' : 'Escolha a data de início para habilitar.' }}</p>
            <p v-if="form.errors.scheduled_end" id="service-end-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.scheduled_end }}</p>
          </div>
          <div class="col-span-12 min-w-0">
            <label for="service-description" class="mb-2 block text-sm font-medium text-td-ink">Descrição <span class="font-normal text-td-body">(opcional)</span></label>
            <textarea id="service-description" v-model="form.description" placeholder="Descreva o serviço e o escopo previsto" :aria-invalid="Boolean(form.errors.description)" :aria-describedby="form.errors.description ? 'service-description-error' : undefined" class="min-h-28 w-full min-w-0 resize-y rounded-lg border border-td-border bg-white px-3.5 py-3 text-sm text-td-ink placeholder:text-td-body/70 focus:border-td-primary focus:outline-none focus:ring-2 focus:ring-td-primary/15 aria-[invalid=true]:border-td-red" />
            <p v-if="form.errors.description" id="service-description-error" class="mt-1.5 text-sm text-td-red" role="alert">{{ form.errors.description }}</p>
          </div>
          <div class="col-span-12 min-w-0 border-t border-td-border pt-5">
            <label for="service-images" class="mb-2 block text-sm font-medium text-td-ink">Imagens do serviço <span class="font-normal text-td-body">(opcional)</span></label>
            <input id="service-images" ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="block w-full min-w-0 rounded-lg border border-td-border bg-white text-sm text-td-ink file:mr-4 file:min-h-11 file:border-0 file:bg-td-soft file:px-4 file:text-td-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" :aria-invalid="Boolean(imageError || form.errors.images)" aria-describedby="service-images-help service-images-error" @change="selectImages" />
            <p id="service-images-help" class="mt-2 text-sm text-td-body">Até 10 imagens JPG, PNG ou WebP, com no máximo 10 MB cada.</p>
            <p v-if="imageError || form.errors.images" id="service-images-error" class="mt-1 text-sm text-td-red" role="alert">{{ imageError || form.errors.images }}</p>
            <ul v-if="form.images.length" class="mt-3 flex flex-wrap gap-2" aria-label="Imagens selecionadas"><li v-for="(image, index) in form.images" :key="`${image.name}-${index}`" class="flex min-w-0 items-center gap-2 rounded-lg border border-td-border bg-td-soft px-3 py-1 text-sm text-td-ink"><span class="max-w-48 truncate">{{ image.name }}</span><button type="button" class="min-h-11 rounded px-2 text-td-red focus-visible:outline-2 focus-visible:outline-td-primary" :aria-label="`Remover ${image.name}`" @click="removeImage(index)">Remover</button></li></ul>
          </div>
        </div>
        <div class="mt-7 flex flex-col-reverse gap-3 border-t border-td-border pt-6 sm:flex-row sm:justify-end">
          <Link class="inline-flex min-h-11 items-center justify-center rounded-lg border border-td-border bg-white px-4 py-2.5 text-sm text-td-ink hover:bg-td-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-td-primary" href="/services">Cancelar</Link>
          <SaveActionButton label="Salvar serviço" :required-incomplete="requiredIncomplete" :processing="form.processing" />
        </div>
      </form>
    </div>
  </AppShell>
</template>
