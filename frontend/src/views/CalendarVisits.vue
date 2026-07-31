<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Calendario de Visitas</h1>
        <p class="text-sm text-gray-500 mt-1">Programación de visitas comerciales</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="prevMonth" class="px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300">&larr;</button>
        <span class="text-sm font-medium dark:text-white w-32 text-center">{{ currentMonthLabel }}</span>
        <button @click="nextMonth" class="px-3 py-1.5 text-sm border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300">&rarr;</button>
      </div>
    </div>

    <Loader v-if="loading" />
    <div v-else class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
      <div class="grid grid-cols-7 border-b border-gray-100 dark:border-gray-700">
        <div v-for="d in dayNames" :key="d" class="px-3 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 text-center">{{ d }}</div>
      </div>
      <div class="grid grid-cols-7">
        <div v-for="(day, i) in calendarDays" :key="i"
          class="min-h-[90px] border-b border-r border-gray-100 dark:border-gray-700 p-1.5"
          :class="[day.isToday ? 'bg-blue-50 dark:bg-blue-900/20' : 'dark:bg-gray-900', !day.isCurrentMonth ? 'opacity-40' : '']">
          <span class="text-xs font-medium"
            :class="day.isToday ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'">
            {{ day.day }}
          </span>
          <div class="mt-1 space-y-0.5">
            <div v-for="v in day.visits" :key="v.id"
              @click="selectVisit(v)"
              class="text-[10px] px-1 py-0.5 rounded cursor-pointer truncate font-medium"
              :class="statusClass(v.status)">
              {{ v.time }} {{ v.contact }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedVisit" class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4" @click.self="selectedVisit = null">
      <div class="bg-white dark:bg-gray-800 rounded-xl p-6 max-w-md w-full shadow-xl">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">{{ selectedVisit.title }}</h3>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
          <p><strong>Contacto:</strong> {{ selectedVisit.contact }}</p>
          <p><strong>Estado:</strong> <span :class="statusBadge(selectedVisit.status)">{{ selectedVisit.status }}</span></p>
          <p><strong>Programado:</strong> {{ selectedVisit.scheduled_at }}</p>
          <p v-if="selectedVisit.summary"><strong>Resumen:</strong> {{ selectedVisit.summary }}</p>
        </div>
        <button @click="selectedVisit = null" class="mt-4 w-full py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm dark:text-white">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'
import Loader from '../components/Loader.vue'

const visits = ref([])
const now = new Date()
const currentMonth = ref(now.getMonth())
const currentYear = ref(now.getFullYear())
const selectedVisit = ref(null)
const loading = ref(true)

const dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']

const currentMonthLabel = computed(() => {
  return new Date(currentYear.value, currentMonth.value).toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
})

const calendarDays = computed(() => {
  const firstDay = new Date(currentYear.value, currentMonth.value, 1).getDay()
  const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
  const daysInPrev = new Date(currentYear.value, currentMonth.value, 0).getDate()
  const today = new Date()
  const result = []

  for (let i = firstDay - 1; i >= 0; i--) {
    result.push({ day: daysInPrev - i, isCurrentMonth: false, isToday: false, visits: [] })
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    const isToday = currentYear.value === today.getFullYear() && currentMonth.value === today.getMonth() && d === today.getDate()
    const dayVisits = visits.value
      .filter(v => v.scheduled_at?.startsWith(dateStr))
      .map(v => ({
        ...v,
        time: v.scheduled_at ? v.scheduled_at.split(' ')[1]?.substring(0, 5) || '' : '',
        contact: v.contact?.name || 'Sin contacto',
      }))
    result.push({ day: d, isCurrentMonth: true, isToday, visits: dayVisits })
  }

  const remaining = 42 - result.length
  for (let d = 1; d <= remaining; d++) {
    result.push({ day: d, isCurrentMonth: false, isToday: false, visits: [] })
  }

  return result
})

function prevMonth() {
  if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value-- }
  else { currentMonth.value-- }
  fetchVisits()
}

function nextMonth() {
  if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++ }
  else { currentMonth.value++ }
  fetchVisits()
}

function statusClass(s) {
  const map = { completed: 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300', cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300', scheduled: 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300', in_progress: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300' }
  return map[s] || 'bg-gray-100 text-gray-700'
}

function statusBadge(s) {
  return 'px-2 py-0.5 rounded-full text-xs font-medium ' + statusClass(s)
}

function selectVisit(v) {
  selectedVisit.value = v
}

async function fetchVisits() {
  loading.value = true
  try {
    const { data } = await api.get('/visits', {
      params: { month: currentMonth.value + 1, year: currentYear.value }
    })
    visits.value = Array.isArray(data) ? data : data.data || []
  } catch (e) {
    console.error(e)
    visits.value = []
  }
  finally { loading.value = false }
}

onMounted(fetchVisits)
</script>
