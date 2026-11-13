<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ value?: string | null; label?: string }>()
const labels: Record<string, string> = { active: 'Ativo', inactive: 'Inativo', draft: 'Rascunho', sent: 'Enviado', viewed: 'Visualizado', approved: 'Aprovado', rejected: 'Recusado', expired: 'Expirado', canceled: 'Cancelado', planned: 'Planejado', in_progress: 'Em andamento', paused: 'Pausado', completed: 'Concluído', pending: 'Pendente', paid: 'Pago', overdue: 'Vencido' }
const tone = computed(() => ['approved', 'active', 'paid', 'completed'].includes(props.value ?? '') ? 'success' : ['rejected', 'canceled', 'expired', 'inactive'].includes(props.value ?? '') ? 'danger' : ['overdue', 'paused'].includes(props.value ?? '') ? 'warning' : 'info')
</script>
<template><span class="status-badge inline-flex w-fit items-center gap-2 whitespace-nowrap rounded-full px-3 py-1 text-xs font-medium leading-5 [&>svg]:size-3.5 [&>svg]:shrink-0" :class="[`status-badge--${tone}`, tone === 'success' ? 'bg-td-green/10 text-td-green-ink' : tone === 'danger' ? 'bg-td-red/10 text-td-red' : tone === 'warning' ? 'bg-td-amber/10 text-td-amber-ink' : 'bg-td-primary/10 text-td-primary']"><span aria-hidden="true" class="size-1.5 shrink-0 rounded-full bg-current" />{{ label || labels[value || ''] || value || 'Sem status' }}</span></template>
