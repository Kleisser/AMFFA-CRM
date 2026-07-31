<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Usuarios</h1>
      <button @click="showForm = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">+ Nuevo</button>
    </div>

    <div v-if="selectedUser">
      <button @click="selectedUser = null; kpiData = null" class="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        Volver a usuarios
      </button>

      <Loader v-if="kpiLoading" />

      <div v-else-if="kpiData" class="space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-blue-600 flex items-center justify-center text-white text-xl font-bold">{{ kpiData.user.name.charAt(0) }}</div>
          <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white">{{ kpiData.user.name }}</h2>
            <p class="text-sm text-gray-500 capitalize">{{ kpiData.user.role }} &middot; {{ kpiData.user.email }}</p>
            <p v-if="kpiData.user.supervisor" class="text-xs text-gray-400">Supervisor: {{ kpiData.user.supervisor.name }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <StatCard title="Contactos" :value="kpiData.summary.total_contacts" :change="kpiData.summary.new_contacts_month" color="blue" />
          <StatCard title="Deals Ganados" :value="kpiData.summary.closed_won" :change="(kpiData.summary.win_rate + ' %')" color="green" />
          <StatCard title="Valor Deals" :value="formatCurrency(kpiData.summary.deal_value)" color="indigo" />
          <StatCard title="Tareas Pend." :value="kpiData.summary.pending_tasks" :change="(kpiData.summary.completed_tasks + ' comp')" color="orange" />
          <StatCard title="Seguimientos" :value="kpiData.summary.follow_ups" color="yellow" />
          <StatCard title="Llamadas" :value="kpiData.summary.total_calls" :change="(kpiData.summary.calls_today + ' hoy')" color="purple" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <ChartCard title="Contactos (últimos 30 días)">
            <Line v-if="lineChartData" :data="lineChartData" :options="chartOptions.line" />
            <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
          </ChartCard>
          <ChartCard title="Pipeline por Etapa">
            <Doughnut v-if="doughnutData" :data="doughnutData" :options="chartOptions.doughnut" />
            <p v-else class="text-gray-400 text-sm py-8 text-center">Sin datos</p>
          </ChartCard>
        </div>

        <div v-if="kpiData.goals.length" class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Metas</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="goal in kpiData.goals" :key="goal.id" class="border border-gray-100 dark:border-gray-700 rounded-lg p-3">
              <p class="text-sm font-medium text-gray-800 dark:text-white">{{ goal.title }}</p>
              <p class="text-xs text-gray-400">{{ goal.type }} &middot; {{ goal.target_value }} objetivo</p>
            </div>
          </div>
        </div>

        <div v-if="kpiData.contacts_by_stage.length" class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
          <h3 class="text-sm font-semibold text-gray-800 dark:text-white mb-3">Contactos por Etapa</h3>
          <div class="space-y-2">
            <div v-for="stage in kpiData.contacts_by_stage" :key="stage.pipeline_stage_id" class="flex items-center gap-3">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: stage.pipeline_stage?.color || '#94a3b8' }"></span>
              <span class="text-sm text-gray-600 dark:text-gray-400 flex-1">{{ stage.pipeline_stage?.name || 'Sin etapa' }}</span>
              <span class="text-sm font-medium text-gray-800 dark:text-white">{{ stage.total }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <template v-else-if="viewMode === 'hierarchy'">
      <Loader v-if="loading" />
      <div v-else class="space-y-4">
        <div v-for="sup in supervisors" :key="sup.id" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
          <button @click="toggleSup(sup.id)" class="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold">{{ sup.name.charAt(0) }}</div>
              <div class="text-left">
                <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ sup.name }}</p>
                <p class="text-xs text-gray-500">{{ sup.sellers?.length || 0 }} asesores</p>
              </div>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform" :class="expanded.has(sup.id) ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
          </button>
          <div v-if="expanded.has(sup.id)" class="divide-y divide-gray-100 dark:divide-gray-700 border-t border-gray-100 dark:border-gray-700">
            <div v-for="seller in sup.sellers" :key="seller.id" class="flex items-center px-5 py-3 pl-16 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ seller.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ seller.email }}</p>
              </div>
              <span class="text-xs font-medium px-2 py-0.5 rounded-full mr-3"
                :class="seller.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'">
                {{ seller.is_active ? 'Activo' : 'Inactivo' }}
              </span>
              <button @click="viewKpi(seller)" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1 flex-shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                Ver KPIs
              </button>
            </div>
          </div>
        </div>
        <p v-if="!supervisors.length" class="text-center py-10 text-sm text-gray-400 dark:text-gray-500">No hay supervisores registrados</p>
      </div>
    </template>

    <template v-else>
      <Loader v-if="loading" />
      <div v-else class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rol</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
              <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">KPI</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
              <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">{{ user.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</td>
              <td class="px-4 py-3">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full capitalize"
                  :class="user.role === 'supervisor' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'">
                  {{ user.role === 'supervisor' ? 'Supervisor' : 'Asesor' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="user.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'">
                  {{ user.is_active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <button @click="viewKpi(user)" class="text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                  Ver KPIs
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!users.length" class="text-center py-10 text-sm text-gray-400 dark:text-gray-500">No se encontraron usuarios</p>
      </div>
    </template>

    <div v-if="showForm" class="fixed inset-0 bg-black/30 z-50 flex items-center justify-center" @click.self="showForm = false">
      <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4 text-gray-800 dark:text-white">Nuevo Usuario</h2>
        <form @submit.prevent="createUser" class="space-y-3">
          <input v-model="form.name" placeholder="Nombre" required class="w-full px-3 py-2 border rounded-lg text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          <input v-model="form.email" type="email" placeholder="Email" required class="w-full px-3 py-2 border rounded-lg text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          <input v-model="form.password" type="password" placeholder="Contraseña" required class="w-full px-3 py-2 border rounded-lg text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          <select v-model="form.role" class="w-full px-3 py-2 border rounded-lg text-sm dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            <option value="seller">Asesor</option>
            <option value="supervisor">Supervisor</option>
            <option value="admin">Admin</option>
          </select>
          <div class="flex gap-2 pt-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">Crear</button>
            <button type="button" @click="showForm = false" class="px-4 py-2 border rounded-lg text-sm text-gray-600 dark:text-gray-400 dark:border-gray-600">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { Line, Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler } from 'chart.js'
import api from '../api/axios'
import Loader from '../components/Loader.vue'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Title, Tooltip, Legend, Filler)

const StatCard = {
  props: { title: String, value: [String, Number], change: [String, Number], color: String },
  setup(props) {
    const colors = {
      blue: 'bg-blue-50 text-blue-600 dark:bg-blue-900/50 dark:text-blue-300',
      green: 'bg-green-50 text-green-600 dark:bg-green-900/50 dark:text-green-300',
      indigo: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-300',
      orange: 'bg-orange-50 text-orange-600 dark:bg-orange-900/50 dark:text-orange-300',
      yellow: 'bg-yellow-50 text-yellow-600 dark:bg-yellow-900/50 dark:text-yellow-300',
      purple: 'bg-purple-50 text-purple-600 dark:bg-purple-900/50 dark:text-purple-300',
    }
    const dotClass = colors[props.color] || colors.blue
    return () => h('div', { class: 'bg-white dark:bg-gray-900 rounded-xl p-4 shadow-sm border border-gray-100 dark:border-gray-700' }, [
      h('div', { class: 'flex items-center gap-2 mb-1' }, [
        h('span', { class: `w-2 h-2 rounded-full ${dotClass}` }),
        h('span', { class: 'text-xs font-medium text-gray-500 dark:text-gray-400' }, props.title),
      ]),
      h('div', { class: 'text-xl font-bold text-gray-800 dark:text-white' }, props.value),
      props.change !== undefined ? h('span', { class: 'text-xs text-gray-400 dark:text-gray-500' }, props.change) : null,
    ])
  }
}

const ChartCard = {
  props: { title: String },
  setup(props, { slots }) {
    return () => h('div', { class: 'bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700' }, [
      h('h3', { class: 'text-sm font-semibold text-gray-800 dark:text-white mb-4' }, props.title),
      h('div', { class: 'h-64' }, slots.default())
    ])
  }
}

const viewMode = ref('flat')
const supervisors = ref([])
const users = ref([])
const showForm = ref(false)
const form = ref({ name: '', email: '', password: '', role: 'seller' })
const loading = ref(true)
const selectedUser = ref(null)
const kpiData = ref(null)
const kpiLoading = ref(false)
const expanded = ref(new Set())

onMounted(() => loadUsers())

function toggleSup(id) {
  const s = new Set(expanded.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  expanded.value = s
}

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await api.get('/users')
    if (data.view === 'hierarchy') {
      viewMode.value = 'hierarchy'
      supervisors.value = data.supervisors || []
    } else {
      viewMode.value = 'flat'
      users.value = data.users || []
    }
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function viewKpi(user) {
  selectedUser.value = user
  kpiLoading.value = true
  kpiData.value = null
  try {
    const { data } = await api.get(`/users/${user.id}/kpi`)
    kpiData.value = data
  } catch (e) {
    console.error(e)
    selectedUser.value = null
  }
  finally { kpiLoading.value = false }
}

async function createUser() {
  try {
    await api.post('/users', form.value)
    showForm.value = false
    form.value = { name: '', email: '', password: '', role: 'seller' }
    loadUsers()
  } catch (e) { console.error(e) }
}

const lineChartData = computed(() => {
  if (!kpiData.value?.contacts_over_time) return null
  return {
    labels: kpiData.value.contacts_over_time.map(d => {
      const parts = d.date.split('-')
      return `${parts[2]}/${parts[1]}`
    }),
    datasets: [{
      label: 'Contactos',
      data: kpiData.value.contacts_over_time.map(d => d.total),
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59,130,246,0.1)',
      fill: true,
      tension: 0.3,
      pointRadius: 3,
    }]
  }
})

const doughnutData = computed(() => {
  if (!kpiData.value?.contacts_by_stage?.length) return null
  return {
    labels: kpiData.value.contacts_by_stage.map(s => s.pipeline_stage?.name || 'Sin etapa'),
    datasets: [{
      data: kpiData.value.contacts_by_stage.map(s => s.total),
      backgroundColor: kpiData.value.contacts_by_stage.map(s => s.pipeline_stage?.color || '#94a3b8'),
      borderWidth: 0,
    }]
  }
})

const chartOptions = {
  line: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 }, color: '#94a3b8', stepSize: 1 } }
    }
  },
  doughnut: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 16, color: '#94a3b8' } } },
    cutout: '65%',
  }
}

function formatCurrency(n) {
  if (n === null || n === undefined) return '$0'
  return Number(n).toLocaleString('es-PE', { style: 'currency', currency: 'PEN', minimumFractionDigits: 0, maximumFractionDigits: 0 })
}
</script>