import { formatBrazilianPhone, formatCurrencyFromDigits, formatDocument, maskInputKeepingCaret } from './brazilianInputMasks'

type RequiredControl = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | HTMLButtonElement

const originalAttributes = new WeakMap<HTMLElement, { invalid: string | null; describedBy: string | null }>()
const errorNodes = new WeakMap<HTMLElement, HTMLParagraphElement>()
let errorSequence = 0

function isRequiredControl(target: EventTarget | null): target is RequiredControl {
  return target instanceof HTMLElement && (
    target.matches('input[required]:not([type="hidden"]), textarea[required], select[required], button[aria-required="true"]')
  )
}

function validationControl(control: RequiredControl): HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null {
  if (control instanceof HTMLButtonElement) {
    let container = control.parentElement

    while (container) {
      const bridge = container.querySelector<HTMLInputElement>('input[required].sr-only')
      if (bridge) return bridge

      // A bridge belongs to its custom field wrapper. Do not let a missing
      // bridge accidentally match another select elsewhere in the form.
      if (container instanceof HTMLFormElement) break
      container = container.parentElement
    }

    return null
  }
  return control
}

function fieldLabel(control: RequiredControl): string {
  const id = control.id
  const label = id ? document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(id)}"]`) : null
  return (label?.textContent || control.getAttribute('aria-label') || 'Este campo')
    .replace(/\*/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

function valueMissing(control: RequiredControl, validation: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null): boolean {
  if (control instanceof HTMLButtonElement) {
    return !validation || String(validation.value).trim() === ''
  }
  if (control instanceof HTMLInputElement && ['checkbox', 'radio'].includes(control.type)) {
    return !control.checked
  }
  if (control instanceof HTMLSelectElement && control.multiple) {
    return !Array.from(control.selectedOptions).some(option => option.value !== '')
  }
  return !String(control.value).trim()
}

function getErrorMessage(control: RequiredControl, exclude: HTMLParagraphElement | null = null): HTMLParagraphElement | null {
  const target = control instanceof HTMLButtonElement ? control.parentElement : control
  const nestedError = Array.from(target?.querySelectorAll<HTMLParagraphElement>('p[role="alert"], p.field-error') ?? [])
    .find(node => node !== exclude)
  if (nestedError) return nestedError
  const describedIds = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean)
  const describedError = describedIds.map(id => document.getElementById(id))
    .find(node => node !== exclude && node?.matches('[role="alert"], .field-error'))
  if (describedError instanceof HTMLParagraphElement) return describedError
  const parent = target?.parentElement
  const adjacentError = target?.nextElementSibling
  if (adjacentError instanceof HTMLParagraphElement && adjacentError !== exclude && adjacentError.matches('[role="alert"], .field-error')) return adjacentError
  return Array.from(parent?.querySelectorAll<HTMLParagraphElement>(':scope > p[role="alert"], :scope > p.field-error') ?? [])
    .find(node => node !== exclude) ?? null
}

function setInvalid(control: RequiredControl, validation: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null) {
  if (!originalAttributes.has(control)) {
    originalAttributes.set(control, {
      invalid: control.getAttribute('aria-invalid'),
      describedBy: control.getAttribute('aria-describedby'),
    })
  }
  control.dataset.requiredFeedbackTouched = 'true'
  control.classList.add('required-field-invalid')
  control.setAttribute('aria-invalid', 'true')

  const existingError = getErrorMessage(control)
  if (existingError) return

  let error = errorNodes.get(control)
  if (!error) {
    error = document.createElement('p')
    error.id = `${control.id || `required-field-${++errorSequence}`}-required-error`
    error.className = 'required-field-error mt-1.5 block text-sm text-td-red'
    error.setAttribute('role', 'alert')
    error.textContent = `${fieldLabel(control)} é obrigatório.`
    const anchor = control instanceof HTMLButtonElement ? control.parentElement : control
    anchor?.insertAdjacentElement('afterend', error)
    errorNodes.set(control, error)
  }

  const original = originalAttributes.get(control)
  const ids = new Set((original?.describedBy || '').split(/\s+/).filter(Boolean))
  ids.add(error.id)
  control.setAttribute('aria-describedby', [...ids].join(' '))
}

function clearInvalid(control: RequiredControl) {
  const error = errorNodes.get(control)
  const existingError = getErrorMessage(control, error ?? null)
  error?.remove()
  errorNodes.delete(control)

  const original = originalAttributes.get(control)
  if (existingError) {
    control.classList.add('required-field-invalid')
    control.setAttribute('aria-invalid', 'true')
    const ids = new Set((original?.describedBy || '').split(/\s+/).filter(Boolean))
    if (!existingError.id) existingError.id = `${control.id || `required-field-${++errorSequence}`}-server-error`
    ids.add(existingError.id)
    control.setAttribute('aria-describedby', [...ids].join(' '))
  } else {
    control.classList.remove('required-field-invalid')
    if (original?.invalid === null) control.removeAttribute('aria-invalid')
    else if (original?.invalid) control.setAttribute('aria-invalid', original.invalid)
    if (original?.describedBy === null) control.removeAttribute('aria-describedby')
    else if (original?.describedBy) control.setAttribute('aria-describedby', original.describedBy)
  }
}

function validate(control: RequiredControl) {
  if (!control.isConnected || control.matches(':disabled')) return
  const validation = validationControl(control)
  if (valueMissing(control, validation)) setInvalid(control, validation)
  else clearInvalid(control)
}

function asRequiredControl(target: EventTarget | null): RequiredControl | null {
  if (isRequiredControl(target)) return target
  if (target instanceof HTMLInputElement && target.matches('input[required].sr-only')) {
    return target.parentElement?.querySelector<HTMLButtonElement>('button[aria-required="true"]') ?? target
  }
  return null
}

function handleFocusOut(event: FocusEvent) {
  const control = asRequiredControl(event.target)
  if (control) validate(control)
}

function handleValueChange(event: Event) {
  const control = asRequiredControl(event.target)
  if (control?.dataset.requiredFeedbackTouched === 'true') validate(control)
}

function isCurrencyInput(input: HTMLInputElement): boolean {
  if (input.dataset.mask === 'currency') return true
  if (input.inputMode !== 'decimal' && input.inputMode !== 'numeric') return false
  const label = input.id
    ? document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(input.id)}"]`)?.textContent || ''
    : ''
  return /preço|valor|custo|desconto|acréscimo/i.test(label)
}

function handleCurrencyInput(event: Event) {
  const input = event.target
  if (!(input instanceof HTMLInputElement) || !isCurrencyInput(input)) return
  const formatted = formatCurrencyFromDigits(input.value)
  input.value = formatted
  input.setSelectionRange(formatted.length, formatted.length)
}

function handleCurrencyFocus(event: FocusEvent) {
  const input = event.target
  if (!(input instanceof HTMLInputElement) || !isCurrencyInput(input) || !input.value) return
  const formatted = formatCurrencyFromDigits(input.value)
  input.value = formatted
  input.setSelectionRange(formatted.length, formatted.length)
}

function handleBrazilianMask(event: Event) {
  const input = event.target
  if (!(input instanceof HTMLInputElement)) return
  const label = input.id
    ? document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(input.id)}"]`)?.textContent || ''
    : ''
  if (input.type === 'tel') {
    maskInputKeepingCaret(input, formatBrazilianPhone)
  } else if (/cpf\s*\/\s*cnpj|cpf\/cnpj|documento/i.test(label)) {
    maskInputKeepingCaret(input, formatDocument)
  }
}

export function installFormFieldFeedback(): void {
  document.addEventListener('focusout', handleFocusOut, true)
  document.addEventListener('input', handleValueChange, true)
  document.addEventListener('change', handleValueChange, true)
  document.addEventListener('input', handleCurrencyInput, true)
  document.addEventListener('input', handleBrazilianMask, true)
  document.addEventListener('focusin', handleCurrencyFocus, true)
}
