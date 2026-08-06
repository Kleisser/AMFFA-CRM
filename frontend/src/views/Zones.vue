<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Zonas</h1>
      <button @click="openZoneForm()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
        + Nueva Zona
      </button>
    </div>

    <Loader v-if="loading" />
    <div v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div v-for="zone in zones" :key="zone.id"
          class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
              <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: zone.color || '#6B7280' }"></span>
              <h2 class="text-lg font-semibold text-gray-800 dark:text-white">{{ zone.name }}</h2>
            </div>
            <div class="flex gap-2">
              <button @click="openZoneForm(zone)" title="Editar zona" class="text-blue-600 dark:text-blue-400 hover:text-blue-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
              </button>
              <button @click="deleteZone(zone)" title="Eliminar zona" class="text-red-500 hover:text-red-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
              </button>
            </div>
          </div>
          <div class="flex items-center gap-4 mt-2 text-sm text-gray-500 dark:text-gray-400">
            <span>{{ zone.localities_count }} localidades</span>
            <span>{{ zone.contacts_count }} contactos</span>
          </div>
          <button @click="openLocalities(zone)" class="mt-4 w-full text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 border border-blue-100 dark:border-blue-900/50 rounded-lg py-2 transition-colors">
            Gestionar localidades
          </button>
        </div>
      </div>

      <p v-if="!zones.length" class="text-center py-10 text-sm text-gray-400 dark:text-gray-500">No hay zonas. Creá la primera.</p>
    </div>

    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md shadow-xl">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">{{ editingZone ? 'Editar Zona' : 'Nueva Zona' }}</h3>
          <form @submit.prevent="saveZone" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
              <input v-model="zoneForm.name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
              <input v-model="zoneForm.color" type="color" class="w-full h-10 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showForm = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
              <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ editingZone ? 'Guardar' : 'Crear' }}</button>
            </div>
          </form>
        </div>
      </div>

      <div v-if="selectedZone" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="closeLocalities">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-2xl shadow-xl max-h-[85vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Localidades de {{ selectedZone.name }}</h3>
            <button @click="closeLocalities" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>

          <div class="flex gap-2 mb-3">
            <input v-model="localitySearch" @input="searchLocalities" type="text" placeholder="Buscar localidad o partido..."
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            <button @click="openLocalityForm()" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 whitespace-nowrap">+ Agregar</button>
          </div>

          <Loader v-if="localitiesLoading" />
          <div v-else>
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase">Localidad</th>
                  <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase">Partido</th>
                  <th class="text-left px-3 py-2 text-xs font-medium text-gray-500 uppercase">CP</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="l in localityPaged" :key="l.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                  <td class="px-3 py-2 text-sm text-gray-800 dark:text-white">{{ l.name }}</td>
                  <td class="px-3 py-2 text-sm text-gray-500">{{ l.partido || '-' }}</td>
                  <td class="px-3 py-2 text-sm text-gray-500">{{ l.code || '-' }}</td>
                  <td class="px-3 py-2 text-right">
                    <div class="flex justify-end gap-3">
                      <button @click="openLocalityForm(l)" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800">Editar</button>
                      <button @click="deleteLocality(l)" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <div class="flex items-center justify-between mt-4">
              <p class="text-xs text-gray-400">{{ filteredLocalities.length }} localidades ({{ selectedZone.localities_count }} total)</p>
              <div class="flex gap-2">
                <button @click="localityPage = Math.max(0, localityPage - 1)" :disabled="localityPage === 0"
                  class="px-3 py-1 text-xs border border-gray-200 dark:border-gray-600 rounded-lg disabled:opacity-40">Anterior</button>
                <button @click="localityPage++" :disabled="(localityPage + 1) * 25 >= filteredLocalities.length"
                  class="px-3 py-1 text-xs border border-gray-200 dark:border-gray-600 rounded-lg disabled:opacity-40">Siguiente</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="showLocalityForm" class="fixed inset-0 bg-black/40 z-[60] flex items-center justify-center p-4" @click.self="showLocalityForm = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-md shadow-xl">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">{{ editingLocality ? 'Editar' : 'Nueva' }} Localidad</h3>
          <form @submit.prevent="saveLocality" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
              <input v-model="localityForm.name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Partido</label>
                <input v-model="localityForm.partido" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código Postal</label>
                <input v-model="localityForm.code" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zona</label>
              <select v-model="localityForm.zone_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
                <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
              </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showLocalityForm = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
              <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ editingLocality ? 'Guardar' : 'Crear' }}</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'
import { useToastStore } from '../stores/toast'
import Loader from '../components/Loader.vue'

const toast = useToastStore()
const zones = ref([])
const loading = ref(true)
const showForm = ref(false)
const editingZone = ref(null)
const zoneForm = ref({ name: '', color: '#6B7280' })
const selectedZone = ref(null)
const localitySearch = ref('')
const localityPage = ref(0)
const localities = ref([])
const localitiesLoading = ref(false)
const showLocalityForm = ref(false)
const editingLocality = ref(null)
const localityForm = ref({ name: '', partido: '', code: '', zone_id: null })
let searchTimeout = null

onMounted(() => loadZones())

const filteredLocalities = computed(() => {
  const s = localitySearch.value.trim().toLowerCase()
  if (!s) return localities.value
  return localities.value.filter(l =>
    l.name.toLowerCase().includes(s) || (l.partido || '').toLowerCase().includes(s)
  )
})

const localityPaged = computed(() => {
  const start = localityPage.value * 25
  return filteredLocalities.value.slice(start, start + 25)
})

async function loadZones() {
  loading.value = true
  try {
    const { data } = await api.get('/zones')
    zones.value = data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

function openZoneForm(zone) {
  editingZone.value = zone || null
  zoneForm.value = { name: zone?.name || '', color: zone?.color || '#6B7280' }
  showForm.value = true
}

async function saveZone() {
  try {
    if (editingZone.value) {
      await api.put(`/zones/${editingZone.value.id}`, zoneForm.value)
      toast.add('Zona actualizada', 'success')
    } else {
      await api.post('/zones', zoneForm.value)
      toast.add('Zona creada', 'success')
    }
    showForm.value = false
    loadZones()
  } catch (e) {
    toast.add(e.response?.data?.message || e.response?.data?.errors?.name?.[0] || 'Error al guardar', 'error')
    console.error(e)
  }
}

async function deleteZone(zone) {
  if (!confirm(`¿Eliminar la zona ${zone.name}?`)) return
  try {
    await api.delete(`/zones/${zone.id}`)
    toast.add('Zona eliminada', 'success')
    loadZones()
  } catch (e) {
    toast.add(e.response?.data?.message || 'Error al eliminar', 'error')
    console.error(e)
  }
}

async function openLocalities(zone) {
  selectedZone.value = zone
  localitySearch.value = ''
  localityPage.value = 0
  localitiesLoading.value = true
  try {
    const { data } = await api.get('/localities', { params: { zone_id: zone.id, limit: 500 } })
    localities.value = data
  } catch (e) { console.error(e) }
  finally { localitiesLoading.value = false }
}

function closeLocalities() {
  selectedZone.value = null
  localities.value = []
}

function searchLocality() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { localityPage.value = 0 }, 150)
}

function openLocalityForm(locality) {
  editingLocality.value = locality || null
  localityForm.value = {
    name: locality?.name || '',
    partido: locality?.partido || '',
    code: locality?.code || '',
    zone_id: selectedZone.value?.id,
  }
  showLocalityForm.value = true
}

async function saveLocality() {
  try {
    if (editingLocality.value) {
      await api.put(`/localities/${editingLocality.value.id}`, localityForm.value)
      toast.add('Localidad actualizada', 'success')
    } else {
      await api.post(`/zones/${localityForm.value.zone_id}/localities`, localityForm.value)
      toast.add('Localidad creada', 'success')
    }
    showLocalityForm.value = false
    await openLocalities(selectedZone.value)
    loadZones()
  } catch (e) {
    toast.add(e.response?.data?.message || 'Error al guardar', 'error')
    console.error(e)
  }
}

async function deleteLocality(locality) {
  if (!confirm(`¿Eliminar la localidad ${locality.name}?`)) return
  try {
    await api.delete(`/localities/${locality.id}`)
    toast.add('Localidad eliminada', 'success')
    await openLocalities(selectedZone.value)
    loadZones()
  } catch (e) {
    toast.add('Error al eliminar', 'error')
    console.error(e)
  }
}
</script>