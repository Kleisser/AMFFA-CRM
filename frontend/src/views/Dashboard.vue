<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Dashboard</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Panel de rendimiento comercial</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="exportDashboard" class="px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 flex items-center gap-1.5 transition-colors">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          Exportar
        </button>
        <span class="text-xs text-gray-400 dark:text-gray-500">Actualizado: <span class="font-medium">{{ lastUpdate }}</span></span>
      </div>
    </div>

    <Loader v-if="loading" text="Cargando dashboard..." />

    <div v-if="error" class="text-center py-20 text-red-500">{{ error }}</div>

    <div v-if="!loading && !error">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <KPICard title="Contactos" :value="kpi.summary.total_contacts" subtitle="Nuevos hoy" :change="kpi.summary.new_contacts_today" color="blue" icon="users" />
        <KPICard title="Deals Ganados" :value="kpi.summary.closed_won" subtitle="Tasa de cierre" :change="kpi.summary.win_rate + '%'" color="green" icon="trending" />
        <KPICard title="Seguimientos" :value="kpi.summary.total_follow_ups" subtitle="Hoy" :change="kpi.summary.follow_ups_today" color="yellow" icon="follow" />
        <KPICard title="Llamadas" :value="kpi.summary.total_calls" subtitle="Hoy" :change="kpi.summary.calls_today" color="purple" icon="phone" />
        <KPICard title="Valor Deals" :value="formatCurrency(kpi.summary.total_deal_value)" subtitle="Promedio" :change="formatCurrency(kpi.summary.avg_deal_value)" color="indigo" icon="cash" />
        <KPICard title="Bandeja" :value="kpi.summary.open_conversations" subtitle="Abiertas" :change="kpi.summary.total_calls + ' total'" color="orange" icon="chat" />
        <KPICard title="Tareas" :value="kpi.summary.pending_tasks" subtitle="Pendientes" color="red" icon="task" />
        <KPICard title="Nuevos (mes)" :value="kpi.summary.new_contacts_month" subtitle="Esta semana" :change="kpi.summary.new_contacts_week" color="teal" icon="growth" />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard title="Contactos (últimos 30 días)">
          <Line v-if="chartData.contactsOverTime" :data="chartData.contactsOverTime" :options="chartOptions.line" />
          <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
        </ChartCard>
        <ChartCard title="Seguimientos (últimos 14 días)">
          <Bar v-if="chartData.followUps" :data="chartData.followUps" :options="chartOptions.bar" />
          <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
        </ChartCard>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <ChartCard title="Pipeline de Ventas (Embudos)">
          <Bar v-if="chartData.funnel" :data="chartData.funnel" :options="chartOptions.funnel" />
          <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
        </ChartCard>
        <ChartCard title="Contactos por Fuente">
          <Doughnut v-if="chartData.source" :data="chartData.source" :options="chartOptions.doughnut" />
          <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
        </ChartCard>
      </div>

      <div v-if="forecast" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Sales Forecasting</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-5">
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Pipeline Total</p>
            <p class="text-xl font-bold text-gray-800 dark:text-white">${{ formatCurrency(forecast.total_pipeline_value) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Forecast Ponderado</p>
            <p class="text-xl font-bold text-blue-600">${{ formatCurrency(forecast.weighted_forecast) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Cerrado Ganado</p>
            <p class="text-xl font-bold text-green-600">${{ formatCurrency(forecast.won_amount) }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Próximos 6 meses</p>
            <p class="text-xl font-bold text-purple-600">${{ formatCurrency(forecastTotal) }}</p>
          </div>
        </div>
        <div v-if="forecastChartData" class="px-5 pb-5" style="height: 200px;">
          <Bar :data="forecastChartData" :options="forecastChartOptions" />
        </div>
      </div>

      <div v-if="kpi.sales_by_seller?.length" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 mb-6">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white">Ranking de Asesores</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                <th class="text-left px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asesor</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Contactos</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ganados</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Deals</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Seguimientos</th>
                <th class="text-center px-5 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rendimiento</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="(seller, i) in sortedSellers" :key="seller.user_id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="px-5 py-3 text-sm font-bold text-gray-400 dark:text-gray-500">#{{ i + 1 }}</td>
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium flex-shrink-0"
                      :class="i === 0 ? 'bg-yellow-500' : i === 1 ? 'bg-gray-400' : i === 2 ? 'bg-amber-700' : 'bg-blue-500'">
                      {{ seller.name.charAt(0) }}
                    </div>
                    <span class="text-sm font-medium text-gray-800 dark:text-white">{{ seller.name }}</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ seller.total_contacts }}</td>
                <td class="px-5 py-3 text-center text-sm font-medium"
                  :class="seller.closed_won > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                  {{ seller.closed_won }}
                </td>
                <td class="px-5 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ formatCurrency(seller.deal_value) }}</td>
                <td class="px-5 py-3 text-center text-sm text-gray-700 dark:text-gray-300">{{ seller.follow_ups }}</td>
                <td class="px-5 py-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <div class="w-20 bg-gray-200 rounded-full h-2">
                      <div class="h-2 rounded-full" :style="{ width: sellerPerformance(seller) + '%' }"
                        :class="sellerPerformance(seller) >= 70 ? 'bg-green-500' : sellerPerformance(seller) >= 40 ? 'bg-yellow-500' : 'bg-red-500'">
                      </div>
                    </div>
                    <span class="text-xs font-medium">{{ sellerPerformance(seller) }}%</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="!kpi.sales_by_seller?.length" class="text-center py-8 text-gray-400 text-sm">Sin datos de asesores</div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Metas Semanales</h2>
          <div v-if="kpi.goals?.length">
            <div v-for="goal in kpi.goals" :key="goal.id" class="mb-4">
              <div class="flex items-center justify-between mb-1.5">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize"
                    :class="goalTypeClass(goal.type)">{{ goal.type }}</span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ goal.progress }} / {{ goal.target }}</span>
                </div>
                <span class="text-xs font-bold" :class="goalProgress(goal) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'">
                  {{ goalProgress(goal) }}%
                </span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                <div class="h-2.5 rounded-full transition-all" :style="{ width: Math.min(goalProgress(goal), 100) + '%' }"
                  :class="goalProgress(goal) >= 100 ? 'bg-green-500' : goalProgress(goal) >= 50 ? 'bg-blue-500' : 'bg-yellow-500'">
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">Sin metas definidas para esta semana</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Próximas Tareas</h2>
          <div v-if="kpi.upcoming_tasks?.length">
            <div v-for="task in kpi.upcoming_tasks" :key="task.id"
              class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
              <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0"
                :class="task.priority === 'high' ? 'bg-red-500' : task.priority === 'medium' ? 'bg-yellow-500' : 'bg-green-500'">
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ task.title }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                  <span v-if="task.contact" class="text-xs text-blue-600 dark:text-blue-400">{{ task.contact.name }}</span>
                  <span class="text-xs text-gray-400 dark:text-gray-500">{{ task.due_date ? formatDate(task.due_date) : '' }}</span>
                </div>
              </div>
              <span class="text-xs px-2 py-0.5 rounded-full capitalize"
                :class="task.status === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/50 text-yellow-700 dark:text-yellow-300' : 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300'">
                {{ task.status }}
              </span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 dark:text-gray-500 py-4 text-center">Sin tareas pendientes</p>
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
import { useExport } from '../composables/useExport'
import Loader from '../components/Loader.vue'
import KPICard from '../components/KPICard.vue'
import ChartCard from '../components/ChartCard.vue'

const { exportCSV } = useExport()

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler)

const kpi = ref({ summary: {}, contacts_over_time: [], contacts_by_stage: [], contacts_by_source: [], sales_by_seller: [], follow_ups_by_day: [], calls_by_day: [], conversion_funnel: [], tasks_by_status: {}, goals: [], recent_contacts: [], upcoming_tasks: [] })
const forecast = ref(null)
const loading = ref(true)
const error = ref('')
const lastUpdate = ref('')

const chartOptions = {
  line: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } }
    }
  },
  bar: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } }
    }
  },
  funnel: {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: {
      x: { beginAtZero: true, grid: { display: false }, ticks: { font: { size: 10 } } },
      y: { grid: { display: false }, ticks: { font: { size: 10 } } }
    }
  },
  doughnut: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } } }
  }
}

const chartData = computed(() => {
  const data = kpi.value
  return {
    contactsOverTime: data.contacts_over_time?.length ? {
      labels: data.contacts_over_time.map(d => {
        const date = new Date(d.date)
        return `${date.getDate()}/${date.getMonth() + 1}`
      }),
      datasets: [{
        label: 'Contactos',
        data: data.contacts_over_time.map(d => d.total),
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59,130,246,0.1)',
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointBackgroundColor: '#3B82F6',
      }]
    } : null,
    followUps: data.follow_ups_by_day?.length ? {
      labels: data.follow_ups_by_day.map(d => {
        const date = new Date(d.date)
        return `${date.getDate()}/${date.getMonth() + 1}`
      }),
      datasets: [{
        label: 'Seguimientos',
        data: data.follow_ups_by_day.map(d => d.total),
        backgroundColor: '#F59E0B',
        borderRadius: 4,
      }]
    } : null,
    funnel: data.conversion_funnel?.length ? {
      labels: data.conversion_funnel.map(s => s.stage),
      datasets: [{
        label: 'Contactos',
        data: data.conversion_funnel.map(s => s.count),
        backgroundColor: data.conversion_funnel.map(s => s.color + '80'),
        borderColor: data.conversion_funnel.map(s => s.color),
        borderWidth: 1,
      }]
    } : null,
    source: data.contacts_by_source?.length ? {
      labels: data.contacts_by_source.map(s => s.source),
      datasets: [{
        data: data.contacts_by_source.map(s => s.total),
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4'],
        borderWidth: 0,
      }]
    } : null,
  }
})

const sortedSellers = computed(() => {
  if (!kpi.value.sales_by_seller?.length) return []
  return [...kpi.value.sales_by_seller].sort((a, b) => b.closed_won - a.closed_won || b.deal_value - a.deal_value)
})

const forecastTotal = computed(() => {
  if (!Array.isArray(forecast.value?.monthly_forecast)) return 0
  return forecast.value.monthly_forecast.reduce((sum, m) => sum + (m.amount || 0), 0)
})

const forecastChartData = computed(() => {
  if (!Array.isArray(forecast.value?.monthly_forecast) || !forecast.value.monthly_forecast.length) return null
  return {
    labels: forecast.value.monthly_forecast.map(m => m.label),
    datasets: [{
      label: 'Proyección',
      data: forecast.value.monthly_forecast.map(m => m.amount),
      backgroundColor: 'rgba(139,92,246,0.5)',
      borderColor: '#8B5CF6',
      borderWidth: 1,
      borderRadius: 4,
    }]
  }
})

const forecastChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 }, callback: v => '$' + v.toLocaleString('es-AR') } }
  }
}

onMounted(fetchDashboard)

async function fetchDashboard() {
  try {
    const [kpiRes, forecastRes] = await Promise.all([
      api.get('/dashboard'),
      api.get('/dashboard/forecast').catch(() => null)
    ])
    kpi.value = kpiRes.data
    forecast.value = forecastRes?.data || null
    lastUpdate.value = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })
  } catch (e) {
    error.value = 'Error al cargar KPIs'
    console.error(e)
  } finally {
    loading.value = false
  }
}

function formatCurrency(n) {
  if (!n) return '$0'
  return '$' + new Intl.NumberFormat('es-AR').format(Number(n).toFixed(2))
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('es-AR', { day: 'numeric', month: 'short' })
}

function sellerPerformance(seller) {
  const maxContacts = Math.max(...kpi.value.sales_by_seller.map(s => s.total_contacts), 1)
  const maxWon = Math.max(...kpi.value.sales_by_seller.map(s => s.closed_won), 1)
  const score = ((seller.total_contacts / maxContacts) * 0.4 + (seller.closed_won / maxWon) * 0.6) * 100
  return Math.round(score)
}

function goalProgress(goal) {
  if (!goal.target) return 0
  return Math.round((goal.progress / goal.target) * 100)
}

function goalTypeClass(type) {
  const map = {
    contacts: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300',
    follow_ups: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
    sales: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
    calls: 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-300',
    visits: 'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300',
  }
  return map[type] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
}

function exportDashboard() {
  const rows = [
    { Métrica: 'Total Contactos', Valor: kpi.value.summary.total_contacts },
    { Métrica: 'Nuevos Hoy', Valor: kpi.value.summary.new_contacts_today },
    { Métrica: 'Deals Ganados', Valor: kpi.value.summary.closed_won },
    { Métrica: 'Tasa de Cierre', Valor: kpi.value.summary.win_rate + '%' },
    { Métrica: 'Valor Total Deals', Valor: kpi.value.summary.total_deal_value },
    { Métrica: 'Tareas Pendientes', Valor: kpi.value.summary.pending_tasks },
    { Métrica: 'Llamadas Totales', Valor: kpi.value.summary.total_calls },
    { Métrica: 'Conversaciones Abiertas', Valor: kpi.value.summary.open_conversations },
  ]
  exportCSV(rows, 'dashboard-kpis.csv')
}
</script>
