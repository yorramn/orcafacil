export function formatDocument(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (digits.length > 14) return digits.slice(0, 14)

  if (digits.length <= 11) {
    const first = digits.slice(0, 3)
    const second = digits.slice(3, 6)
    const third = digits.slice(6, 9)
    const last = digits.slice(9, 11)
    return `${first}${second ? `.${second}` : ''}${third ? `.${third}` : ''}${last ? `-${last}` : ''}`
  }

  const first = digits.slice(0, 2)
  const second = digits.slice(2, 5)
  const third = digits.slice(5, 8)
  const branch = digits.slice(8, 12)
  const last = digits.slice(12, 14)
  return `${first}${second ? `.${second}` : ''}${third ? `.${third}` : ''}${branch ? `/${branch}` : ''}${last ? `-${last}` : ''}`
}

export function formatBrazilianPhone(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (digits.length > 11) return value
  if (!digits) return ''

  if (digits.length <= 2) return `(${digits}`
  const area = digits.slice(0, 2)
  const number = digits.slice(2)
  if (number.length <= 4) return `(${area}) ${number}`
  if (digits.length <= 10) return `(${area}) ${number.slice(0, 4)}-${number.slice(4)}`
  return `(${area}) ${number.slice(0, 5)}-${number.slice(5)}`
}

export function maskInputKeepingCaret(input: HTMLInputElement, format: (value: string) => string): string {
  const start = input.selectionStart ?? input.value.length
  const digitsBeforeCaret = input.value.slice(0, start).replace(/\D/g, '').length
  const formatted = format(input.value)
  input.value = formatted

  let seen = 0
  let caret = digitsBeforeCaret === 0 ? 0 : formatted.length
  for (let index = 0; index < formatted.length; index += 1) {
    if (/\d/.test(formatted[index]) && ++seen === digitsBeforeCaret) {
      caret = index + 1
      break
    }
  }
  input.setSelectionRange(caret, caret)
  return formatted
}

export function formatCurrencyFromDigits(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (!digits) return ''
  const cents = BigInt(digits)
  const whole = (cents / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `R$ ${whole},${(cents % 100n).toString().padStart(2, '0')}`
}
