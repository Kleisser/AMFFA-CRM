<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Verificación Sistema Externo (GECROS)</h1>
      <button @click="load" class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 9a8 8 0 0114.7-2M20 15a8 8 0 01-14.7 2" /></svg>
        Refrescar
      </button>
    </div>

    <p v-if="!summary?.bridge_configured" class="mb-4 px-4 py-3 rounded-lg text-sm bg-yellow-50 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300 border border-yellow-200 dark:border-yellow-800">
      El puente con el sistema externo aún no está configurado. Los datos de esta pantalla se completarán cuando se configure (GECROS_BRIDGE_URL / GECROS_BRIDGE_KEY).
    </p>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Contactos con DNI</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ summary?.total_contacts_with_dni || 0 }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Encontrados en GECROS</p>
        <p class="text-2xl font-bold text-green-600">{{ summary?.found_in_gecros || 0 }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">No encontrados</p>
        <p class="text-2xl font-bold text-yellow-600">{{ summary?.not_found || 0 }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Errores de consulta</p>
        <p class="text-2xl font-bold text-red-600">{{ summary?.errors || 0 }}</p>
      </div>
      <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Sin verificar</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white">{{ summary?.unchecked || 0 }}</p>
        <p v-if="summary?.last_check_at" class="text-[10px] text-gray-400 mt-1">Última consulta: {{ formatTime(summary.last_check_at) }}</p>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-100 dark:border-gray-700">
        <input v-model="search" @input="debouncedSearch" type="text" placeholder="Buscar por DNI, nombre o email..."
          class="w-full max-w-md px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Contacto</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">DNI</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asignado a</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Verificado</th>
            <th class="text-right px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr v-for="c in contacts" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <td class="px-4 py-3">
              <router-link :to="`/contacts/${c.id}`" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800">{{ c.name }}</router-link>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ c.dni }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ c.plan?.name || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ c.assigned_to?.name || '-' }}</td>
            <td class="px-4 py-3">
              <span v-if="c.external_check" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium"
                :class="statusClass(c.external_check.status)">
                {{ statusLabel(c.external_check.status) }}
              </span>
              <span v-else class="text-sm text-gray-400">Sin verificar</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ c.external_check ? formatTime(c.external_check.checked_at) : '-' }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="refresh(c)" :disabled="refreshingId === c.id" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 disabled:opacity-50">
                {{ refreshingId === c.id ? 'Consultando...' : 'Verificar' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!contacts.length" class="text-center py-12 text-gray-400">Sin contactos</div>
      <div v-if="meta" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
        <span>Mostrando {{ meta.from || 0 }} - {{ meta.to || 0 }} de {{ meta.total || 0 }}</span>
        <div class="flex gap-2">
          <button :disabled="!meta.current_page || meta.current_page <= 1" @click="changePage(meta.current_page - 1)" class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 disabled:opacity-50">Anterior</button>
          <button :disabled="!meta.current_page || meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)" class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 disabled:opacity-50">Siguiente</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api/axios'
import { useToastStore } from '../stores/toast'

const toast = useToastStore()
const summary = ref(null)
const contacts = ref([])
const meta = ref(null)
const search = ref('')
const refreshingId = ref(null)
let searchTimeout = null

onMounted(load)

async function load(page = 1) {
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    const { data } = await api.get('/external-checks', { params })
    summary.value = data.summary
    contacts.value = data.contacts?.data || []
    meta.value = data.contacts?.meta || null
  } catch (e) {
    console.error(e)
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => load(), 300)
}

function changePage(page) {
  if (page < 1 || page > (meta.value?.last_page || 1)) return
  load(page)
}

async function refresh(c) {
  refreshingId.value = c.id
  try {
    const { data } = await api.post(`/contacts/${c.id}/external-check`)
    if (data.status === 'unconfigured') {
      toast.add('Puente GECROS no configurado', 'error')
    } else {
      toast.add('Verificación completada', 'success')
    }
    load()
  } catch (e) {
    toast.add('Error al verificar', 'error')
  } finally {
    refreshingId.value = null
  }
}

function statusLabel(status) {
  const map = { found: 'Encontrado', not_found: 'No encontrado', error: 'Error' }
  return map[status] || status
}

function statusClass(status) {
  if (status === 'found') return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
  if (status === 'not_found') return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300'
  return 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300'
}

function formatTime(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
