import { useToastStore } from '../stores/toast'

export function useExport() {
  const toast = useToastStore()

  function exportCSV(data, filename = 'export.csv') {
    if (!data.length) {
      toast.add('No hay datos para exportar', 'warning')
      return
    }

    const headers = Object.keys(data[0])
    const csvRows = [headers.join(',')]

    for (const row of data) {
      const values = headers.map(h => {
        const val = row[h]?.toString() || ''
        return val.includes(',') || val.includes('"') || val.includes('\n')
          ? `"${val.replace(/"/g, '""')}"`
          : val
      })
      csvRows.push(values.join(','))
    }

    const BOM = '\uFEFF'
    const blob = new Blob([BOM + csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
    toast.add(`Exportado ${data.length} registros`, 'success')
  }

  return { exportCSV }
}
