export function maskModelValue(value: string | number | null | undefined, mask = 'currency'): string {
  if (value === null || value === undefined || value === '') return ''
  const digits = String(value).replace(/\D/g, '')
  if (mask !== 'currency') return String(value)
  return (Number(digits) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}
