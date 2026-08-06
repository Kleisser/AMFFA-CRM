<template>
  <div>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Altas y Bajas (GECROS)</h1>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs text-gray-500 uppercase tracking-wide">Cierre:</span>
        <button
          v-for="m in cierres"
          :key="m"
          @click="setMes(m)"
          class="px-3 py-1.5 text-xs border rounded-lg transition-colors"
          :class="mes === m
            ? 'bg-blue-600 text-white border-blue-600'
            : 'border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'"
        >
          {{ mesLabel(m) }}
        </button>
      </div>
    </div>

    <div class="flex items-center gap-3 mb-6 flex-wrap">
      <span class="text-xs text-gray-500 uppercase tracking-wide">Zona:</span>
      <select
        v-model="zona"
        class="px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      >
        <option value="">Todas</option>
        <option v-for="z in zonas" :key="z.id" :value="z.id">{{ z.name }}</option>
      </select>
      <span class="text-xs text-gray-400">Los cierres son del 26 de cada mes al 25. Altas y bajas según GECROS.</span>
    </div>

    <p v-if="!configured" class="mb-4 px-4 py-3 rounded-lg text-sm bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
      El puente con el sistema externo aún no está configurado. Esta pantalla se activará cuando se configure (GECROS_BRIDGE_URL / GECROS_BRIDGE_KEY).
    </p>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Altas</p>
        <p class="text-2xl font-bold text-green-600">{{ kpis.altas }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Bajas</p>
        <p class="text-2xl font-bold text-red-600">{{ kpis.bajas }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Neto</p>
        <p class="text-2xl font-bold" :class="kpis.neto >= 0 ? 'text-gray-800 dark:text-white' : 'text-red-600'">
          {{ kpis.neto >= 0 ? '+' : '' }}{{ kpis.neto }}
        </p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Edad prom. altas</p>
        <p class="text-2xl font-bold text-yellow-600">{{ kpis.edad }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Plan + vendido</p>
        <p class="text-xl font-bold text-gray-800 dark:text-white break-words">{{ kpis.topPlan }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Vendedores activos</p>
        <p class="text-2xl font-bold text-blue-600">{{ kpis.vendedores }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 lg:col-span-2">
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Evolución mensual — Altas vs Bajas</p>
        <div class="h-64">
          <Line v-if="evolucionData" :data="evolucionData" :options="chartOptions.line" />
        </div>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Altas por Plan</p>
        <div class="h-64">
          <Doughnut v-if="altasPlanData" :data="altasPlanData" :options="chartOptions.doughnut" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 lg:col-span-2">
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Altas por Vendedor</p>
        <div class="h-64">
          <Bar v-if="vendedorData" :data="vendedorData" :options="chartOptions.bar" />
        </div>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3">Bajas por Plan</p>
        <div class="h-64">
          <Doughnut v-if="bajasPlanData" :data="bajasPlanData" :options="chartOptions.doughnut" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Neto por Equipo (según vendedor GECROS)</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Equipo</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Altas</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bajas</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Neto</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="e in equiposNeto" :key="e.name" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ e.name }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-green-600">{{ e.altas }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-red-600">{{ e.bajas }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold" :class="e.neto >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ e.neto >= 0 ? '+' : '' }}{{ e.neto }}
              </td>
            </tr>
            <tr v-if="!equiposNeto.length" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Sin datos para el período</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Neto por Zona del afiliado (según contactos del CRM)</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Zona</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Altas</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bajas</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Neto</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="z in zonasNeto" :key="z.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ z.name }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-green-600">{{ z.altas }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-red-600">{{ z.bajas }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold" :class="z.neto >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ z.neto >= 0 ? '+' : '' }}{{ z.neto }}
              </td>
            </tr>
            <tr v-if="!zonasNeto.length" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Sin datos para el período</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700">
          <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Neto por Vendedor (según vendedor GECROS)</p>
        </div>
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendedor</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Zona</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Altas</th>
              <th class="text-center px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bajas</th>
              <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Neto</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="v in vendedoresNeto" :key="v.name" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ v.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ v.zona }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-green-600">{{ v.altas }}</td>
              <td class="px-4 py-3 text-sm text-center font-semibold text-red-600">{{ v.bajas }}</td>
              <td class="px-4 py-3 text-sm text-right font-bold" :class="v.neto >= 0 ? 'text-green-600' : 'text-red-600'">
                {{ v.neto >= 0 ? '+' : '' }}{{ v.neto }}
              </td>
            </tr>
            <tr v-if="!vendedoresNeto.length" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">Sin altas ni bajas para el período</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-10">
      <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Ventas reales (Google Sheets)</h2>
        <span v-if="ventas.sincronizada_at" class="text-xs text-gray-400">Sincronizado: {{ ventas.sincronizada_at }}</span>
      </div>

      <p v-if="!ventas.configurada" class="mb-4 px-4 py-3 rounded-lg text-sm bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
        La planilla de ventas aún no está configurada (VENTAS_SPREADSHEET_ID). Esta sección se activará cuando se configure y se comparta con la cuenta de servicio.
      </p>

      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
          <div class="p-4 border-b border-gray-100 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ventas por Equipo</p>
          </div>
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Equipo</th>
                <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ventas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="(e, i) in ventas.por_equipo" :key="i" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ e.equipo || 'Sin equipo' }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-green-600">{{ money(e.monto) }}</td>
              </tr>
              <tr v-if="!ventas.por_equipo.length">
                <td colspan="2" class="px-4 py-8 text-center text-sm text-gray-400">Sin ventas para {{ mesLabel(mes) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <td class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Total</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-green-600">{{ money(ventas.total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden lg:col-span-2">
          <div class="p-4 border-b border-gray-100 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Ventas por Vendedor</p>
          </div>
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Vendedor</th>
                <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Equipo</th>
                <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ventas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="(v, i) in ventas.por_vendedor" :key="i" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-4 py-3 text-sm font-medium text-gray-700 dark:text-gray-200">{{ v.asesor }}</td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ v.equipo || 'Sin equipo' }}</td>
                <td class="px-4 py-3 text-sm text-right font-bold text-green-600">{{ money(v.monto) }}</td>
              </tr>
              <tr v-if="!ventas.por_vendedor.length">
                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">Sin ventas para {{ mesLabel(mes) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Line, Bar, Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler } from 'chart.js'
import api from '../api/axios'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler)

const configured = ref(false)
const mes = ref('')
const zona = ref('')
const cierres = ref([])
const zonas = ref([])
const altas = ref([])
const bajas = ref([])
const meses = ref([])
const ventas = ref({ configurada: false, total: '0.00', por_vendedor: [], por_equipo: [], sincronizada_at: null })
const loading = ref(true)

const PALETTE = ['#2563eb', '#00b386', '#f59e0b', '#ef4444', '#8b5cf6', '#fb923c', '#06b6d4', '#10b981']

const chartOptions = {
  line: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { font: { size: 10 } } } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, ticks: { font: { size: 10 } } }
    }
  },
  bar: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, ticks: { font: { size: 10 } } }
    }
  },
  doughnut: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } } }
  }
}

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/external-checks/altas-bajas', { params: { meses: 6 } })
    configured.value = data.configured
    meses.value = data.meses || []
    cierres.value = (data.meses || []).map(m => m.mes)
    zonas.value = data.zonas || []
    altas.value = data.altas || []
    bajas.value = data.bajas || []
    mes.value = mes.value || (cierres.value.length ? cierres.value[cierres.value.length - 1] : '')
    await loadVentas()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function loadVentas() {
  if (!mes.value) return
  try {
    const { data } = await api.get('/ventas', { params: { mes: mes.value } })
    ventas.value = data
  } catch (e) {
    console.error(e)
  }
}

function money(v) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(Number(v || 0))
}

function setMes(m) {
  mes.value = m
  loadVentas()
}

const zonaById = computed(() => {
  const map = {}
  zonas.value.forEach(z => { map[z.id] = z.name })
  return map
})

const zonaName = computed(() => (id) => (id == null ? 'Sin zona' : (zonaById.value[id] || 'Zona ' + id)))

function filtradas(rows, tipo) {
  return rows.filter(r =>
    (!mes.value || r.mes === mes.value) &&
    (!zona.value || r.zona_id == zona.value)
  )
}

function dedupe(rows) {
  const seen = new Set()
  const out = []
  rows.forEach(r => {
    if (!seen.has(r.grupo)) {
      seen.add(r.grupo)
      out.push(r)
    }
  })
  return out
}

const fa = computed(() => dedupe(filtradas(altas.value)))
const fb = computed(() => dedupe(filtradas(bajas.value)))

const kpis = computed(() => {
  const totalAltas = fa.value.length
  const totalBajas = fb.value.length
  const edades = fa.value.map(r => r.edad).filter(e => e != null)
  const edad = edades.length ? (edades.reduce((s, e) => s + e, 0) / edades.length).toFixed(1) : '—'
  const planCount = {}
  fa.value.forEach(r => {
    if (r.plan) planCount[r.plan] = (planCount[r.plan] || 0) + 1
  })
  const top = Object.entries(planCount).sort((a, b) => b[1] - a[1])[0]
  const vendedores = new Set(fa.value.map(r => r.vendedor).filter(Boolean))
  return {
    altas: totalAltas,
    bajas: totalBajas,
    neto: totalAltas - totalBajas,
    edad,
    topPlan: top ? top[0] : '—',
    vendedores: vendedores.size,
  }
})

const evolucionData = computed(() => {
  if (!meses.value.length) return null
  return {
    labels: meses.value.map(m => mesLabel(m.mes)),
    datasets: [
      { label: 'Altas', data: meses.value.map(m => m.altas), borderColor: '#059669', backgroundColor: 'rgba(5,150,105,0.1)', tension: 0.35, fill: true },
      { label: 'Bajas', data: meses.value.map(m => m.bajas), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.1)', tension: 0.35, fill: true },
    ]
  }
})

function agruparPorPlan(rows) {
  const counts = {}
  rows.forEach(r => { if (r.plan) counts[r.plan] = (counts[r.plan] || 0) + 1 })
  const entries = Object.entries(counts).sort((a, b) => b[1] - a[1])
  return {
    labels: entries.map(e => e[0]),
    datasets: [{ data: entries.map(e => e[1]), backgroundColor: entries.map((_, i) => PALETTE[i % PALETTE.length]), borderWidth: 0 }]
  }
}

const altasPlanData = computed(() => fa.value.length ? agruparPorPlan(fa.value) : null)
const bajasPlanData = computed(() => fb.value.length ? agruparPorPlan(fb.value) : null)

const vendedorData = computed(() => {
  const counts = {}
  fa.value.forEach(r => {
    if (r.vendedor) counts[r.vendedor] = (counts[r.vendedor] || 0) + 1
  })
  const entries = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 20)
  if (!entries.length) return null
  return {
    labels: entries.map(e => e[0]),
    datasets: [{ data: entries.map(e => e[1]), backgroundColor: '#2563eb', borderRadius: 3 }]
  }
})

const equiposNeto = computed(() => {
  const eData = {}
  fa.value.forEach(r => {
    const name = r.equipo || 'Sin equipo'
    const e = eData[name] || (eData[name] = { name, altas: 0, bajas: 0 })
    e.altas += 1
  })
  fb.value.forEach(r => {
    const name = r.equipo || 'Sin equipo'
    const e = eData[name] || (eData[name] = { name, altas: 0, bajas: 0 })
    e.bajas += 1
  })
  return Object.values(eData).map(e => ({
    ...e,
    neto: e.altas - e.bajas,
  })).sort((a, b) => a.neto - b.neto)
})

const zonasNeto = computed(() => {
  const altasPorZona = {}
  fa.value.forEach(r => {
    const id = r.zona_id ?? 'sin_zona'
    altasPorZona[id] = (altasPorZona[id] || 0) + 1
  })
  const bajasPorZona = {}
  fb.value.forEach(r => {
    const id = r.zona_id ?? 'sin_zona'
    bajasPorZona[id] = (bajasPorZona[id] || 0) + 1
  })
  const ids = new Set([...Object.keys(altasPorZona), ...Object.keys(bajasPorZona)])
  return [...ids].map(id => ({
    id,
    name: id === 'sin_zona' ? 'Sin zona' : (zonaById.value[id] || 'Zona ' + id),
    altas: altasPorZona[id] || 0,
    bajas: bajasPorZona[id] || 0,
    neto: (altasPorZona[id] || 0) - (bajasPorZona[id] || 0),
  })).sort((a, b) => a.neto - b.neto)
})

const vendedoresNeto = computed(() => {
  const vData = {}
  fa.value.forEach(r => {
    if (!r.vendedor) return
    const v = vData[r.vendedor] || (vData[r.vendedor] = { name: r.vendedor, zonaId: null, altas: 0, bajas: 0 })
    v.altas += 1
    if (v.zonaId == null && r.zona_id != null) v.zonaId = r.zona_id
  })
  fb.value.forEach(r => {
    if (!r.vendedor) return
    const v = vData[r.vendedor] || (vData[r.vendedor] = { name: r.vendedor, zonaId: null, altas: 0, bajas: 0 })
    v.bajas += 1
    if (v.zonaId == null && r.zona_id != null) v.zonaId = r.zona_id
  })
  return Object.values(vData).map(v => ({
    ...v,
    zona: v.zonaId == null ? '—' : zonaName.value(v.zonaId),
    neto: v.altas - v.bajas,
  })).sort((a, b) => a.neto - b.neto)
})

function mesLabel(m) {
  if (!m) return ''
  const [anio, mm] = m.split('-')
  const nombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic']
  return `${nombres[parseInt(mm, 10) - 1]} ${anio}`
}
</script>
