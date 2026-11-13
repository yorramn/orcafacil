export const serviceStatusOptions = [
  { value: 'planned', label: 'Planejado' },
  { value: 'in_progress', label: 'Em andamento' },
  { value: 'paused', label: 'Pausado' },
  { value: 'completed', label: 'Concluído' },
  { value: 'canceled', label: 'Cancelado' },
] as const

export function catalogTypeLabel(value: string): string {
  const labels: Record<string, string> = {
    service: 'Serviço',
    material: 'Material',
    fee: 'Taxa',
  }

  return labels[value] || value
}
