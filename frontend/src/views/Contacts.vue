<template>
  <div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Contactos</h1>
      <div class="flex items-center gap-2">
        <button @click="handleExport" class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 transition-colors flex items-center gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
          Exportar CSV
        </button>
        <router-link to="/contacts/new" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
          + Nuevo Contacto
        </router-link>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-100 dark:border-gray-700">
        <input
          v-model="search"
          @input="debouncedSearch"
          type="text"
          placeholder="Buscar contactos..."
          class="w-full max-w-md px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nombre</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Teléfono</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Etapa</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Asignado a</th>
            <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Deal</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr v-for="contact in contacts" :key="contact.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <td class="px-4 py-3">
              <router-link :to="`/contacts/${contact.id}`" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800">
                {{ contact.name }}
              </router-link>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ contact.email || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ contact.phone || '-' }}</td>
            <td class="px-4 py-3">
              <span v-if="contact.pipeline_stage" class="inline-flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full"
                :style="{ backgroundColor: contact.pipeline_stage.color + '20', color: contact.pipeline_stage.color }">
                <span class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: contact.pipeline_stage.color }"></span>
                {{ contact.pipeline_stage.name }}
              </span>
              <span v-else class="text-sm text-gray-400">-</span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ contact.assigned_to?.name || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200 text-right font-medium">
              {{ contact.deal_value ? '$' + formatNumber(contact.deal_value) : '-' }}
            </td>
            <td class="px-4 py-3 text-right">
              <router-link :to="`/contacts/${contact.id}/edit`" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800">Editar</router-link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!contacts.length" class="text-center py-12 text-gray-400">No hay contactos aún</div>

      <div v-if="meta" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
        <span>Mostrando {{ meta.from || 0 }} - {{ meta.to || 0 }} de {{ meta.total || 0 }}</span>
        <div class="flex gap-2">
          <button :disabled="!prevPage" @click="changePage(meta.current_page - 1)" class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 disabled:opacity-50">Anterior</button>
          <button :disabled="!nextPage" @click="changePage(meta.current_page + 1)" class="px-3 py-1 border border-gray-200 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-800 dark:text-gray-300 disabled:opacity-50">Siguiente</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'
import { useExport } from '../composables/useExport'

const { exportCSV } = useExport()

const contacts = ref([])
const meta = ref(null)
const search = ref('')
let searchTimeout = null

const prevPage = computed(() => meta.value?.current_page > 1)
const nextPage = computed(() => meta.value?.current_page < meta.value?.last_page)

onMounted(() => loadContacts())

async function loadContacts(page = 1) {
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    const { data } = await api.get('/contacts', { params })
    contacts.value = data.data
    meta.value = data.meta || null
  } catch (e) {
    console.error('Error loading contacts:', e)
  }
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadContacts(), 300)
}

function changePage(page) {
  if (page < 1 || page > (meta?.last_page || 1)) return
  loadContacts(page)
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(n)
}

async function handleExport() {
  try {
    const { data } = await api.get('/contacts', { params: { per_page: 1000 } })
    const list = data.data || []
    const rows = list.map(c => ({
      Nombre: c.name,
      Email: c.email || '',
      Teléfono: c.phone || '',
      Empresa: c.company || '',
      Fuente: c.source || '',
      Etapa: c.pipeline_stage?.name || '',
      Asesor: c.assigned_to?.name || '',
      Deal: c.deal_value || 0,
    }))
    exportCSV(rows, 'contactos.csv')
  } catch (e) {
    console.error(e)
  }
}
</script>
