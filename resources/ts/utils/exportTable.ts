// أدوات تصدير بسيطة بدون أي مكتبة خارجية — CSV (للإكسل) وطباعة PDF عبر المتصفح.

export interface ExportColumn {
  title: string
  value: (row: any) => string | number | null | undefined
}

const triggerDownload = (blob: Blob, filename: string) => {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

const csvCell = (val: unknown): string => {
  const s = val === null || val === undefined ? '' : String(val)

  // تهريب القيم التي تحتوي فواصل أو علامات اقتباس أو أسطر جديدة
  return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
}

/**
 * تصدير إلى ملف CSV يفتح مباشرة في Excel (مع BOM لدعم العربية).
 */
export const exportToCsv = (filename: string, columns: ExportColumn[], rows: any[]) => {
  const header = columns.map(c => csvCell(c.title))
  const body = rows.map(row => columns.map(c => csvCell(c.value(row))))

  const csv = [header, ...body].map(line => line.join(',')).join('\r\n')
  const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8;' })

  triggerDownload(blob, filename.endsWith('.csv') ? filename : `${filename}.csv`)
}

const escapeHtml = (val: unknown): string =>
  String(val ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

/**
 * طباعة إلى PDF عبر نافذة المتصفح (المستخدم يختار "حفظ كـ PDF").
 */
export const exportToPdf = (title: string, columns: ExportColumn[], rows: any[]) => {
  const win = window.open('', '_blank')
  if (!win)
    return

  const head = columns.map(c => `<th>${escapeHtml(c.title)}</th>`).join('')
  const bodyRows = rows
    .map(row => `<tr>${columns.map(c => `<td>${escapeHtml(c.value(row))}</td>`).join('')}</tr>`)
    .join('')

  win.document.write(`<!doctype html>
<html dir="rtl" lang="ar">
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(title)}</title>
  <style>
    * { font-family: "Thmanyah Sans", system-ui, sans-serif; }
    body { padding: 28px; color: #1a1a1a; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .meta { color: #6b7280; font-size: 12px; margin-bottom: 18px; }
    table { inline-size: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { border: 1px solid #e5e7eb; padding: 8px 10px; text-align: start; }
    thead th { background: #059669; color: #fff; }
    tbody tr:nth-child(even) { background: #f3faf6; }
  </style>
</head>
<body>
  <h1>${escapeHtml(title)}</h1>
  <div class="meta">${new Date().toLocaleString('en-GB')} — ${rows.length} صف</div>
  <table>
    <thead><tr>${head}</tr></thead>
    <tbody>${bodyRows}</tbody>
  </table>
  <script>window.onload = () => { window.print() }<\/script>
</body>
</html>`)
  win.document.close()
}
