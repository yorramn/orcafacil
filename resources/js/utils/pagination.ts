export function paginationLabel(label: string): string {
  const plain = label.replace(/&laquo;|&raquo;|[«»]/g, '').trim()
  if (/^previous$/i.test(plain)) return 'Anterior'
  if (/^next$/i.test(plain)) return 'Próxima'
  return plain.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
}
