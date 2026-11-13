const saoPaulo = 'America/Sao_Paulo'

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
  timeZone: saoPaulo, day: '2-digit', month: '2-digit', year: 'numeric',
})

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  timeZone: saoPaulo, day: '2-digit', month: '2-digit', year: 'numeric',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
})

export function formatDateBR(value?: string | null, fallback = '—'): string {
  if (!value) return fallback
  const calendarDate = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value)
  if (calendarDate) return `${calendarDate[3]}/${calendarDate[2]}/${calendarDate[1]}`
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? fallback : dateFormatter.format(date)
}

export function formatCalendarDateBR(value?: string | null, fallback = '—'): string {
  if (!value) return fallback
  const calendarDate = /^(\d{4})-(\d{2})-(\d{2})(?:$|T| )/.exec(value)
  return calendarDate ? `${calendarDate[3]}/${calendarDate[2]}/${calendarDate[1]}` : fallback
}

export function formatDateTimeBR(value?: string | null, fallback = 'Data não informada'): string {
  if (!value) return fallback
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? fallback : dateTimeFormatter.format(date)
}
