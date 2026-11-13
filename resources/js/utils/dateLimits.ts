const saoPauloDate = new Intl.DateTimeFormat('en-US', {
  timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit',
})

export function saoPauloToday(): string {
  const parts = Object.fromEntries(saoPauloDate.formatToParts(new Date()).map(({ type, value }) => [type, value]))
  return `${parts.year}-${parts.month}-${parts.day}`
}

export function addDays(date: string, days: number): string {
  const value = new Date(`${date}T12:00:00Z`)
  value.setUTCDate(value.getUTCDate() + days)
  return value.toISOString().slice(0, 10)
}

export function addMonthsClamped(date: string, months: number): string {
  const [year, month, day] = date.split('-').map(Number)
  const firstOfTargetMonth = new Date(Date.UTC(year, month - 1 + months, 1))
  const lastDay = new Date(Date.UTC(firstOfTargetMonth.getUTCFullYear(), firstOfTargetMonth.getUTCMonth() + 1, 0)).getUTCDate()
  firstOfTargetMonth.setUTCDate(Math.min(day, lastDay))
  return firstOfTargetMonth.toISOString().slice(0, 10)
}
