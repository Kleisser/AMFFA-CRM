<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">{{ isEdit ? 'Editar Contacto' : 'Nuevo Contacto' }}</h1>

    <form @submit.prevent="save" class="max-w-2xl bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">DNI *</label>
          <input v-model="form.dni" required placeholder="DNI / Legajo" maxlength="20"
            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
        <div class="col-span-1">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
          <input v-model="form.name" required placeholder="Nombre completo" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
          <input v-model="form.email" type="email" placeholder="correo@ejemplo.com" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono / Celular</label>
          <input v-model="form.phone" placeholder="11 1234-5678" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
        <div class="col-span-2 relative">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Localidad</label>
          <input v-model="localityQuery" @input="searchLocalities" @focus="searchLocalities" @blur="hideLocalities"
            placeholder="Escribí para buscar (ej. Burzaco, Cañuelas...)" autocomplete="off"
            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          <div v-if="localityResults.length && showLocalityList"
            class="absolute z-20 mt-1 w-full max-h-60 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg">
            <button v-for="l in localityResults" :key="l.id" type="button" @mousedown.prevent="selectLocality(l)"
              class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
              <span class="text-gray-800 dark:text-gray-200">{{ l.name }}</span>
              <span v-if="l.partido" class="text-gray-400 text-xs ml-2">{{ l.partido }}</span>
              <span class="ml-2 text-xs font-medium" :style="{ color: l.zone?.color }">{{ l.zone?.name }}</span>
            </button>
            <p v-if="!localityResults.length" class="px-3 py-2 text-xs text-gray-400">Sin resultados</p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Zona *</label>
          <select v-model="form.zone_id" required
            class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            <option :value="null">Seleccionar zona...</option>
            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
          </select>
          <p class="text-xs text-gray-400 mt-1">Se autocompleta al elegir localidad</p>
        </div>
      </div>

      <div class="border-t border-gray-100 dark:border-gray-700 pt-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan</label>
            <select v-model="form.plan_id" @change="recalculateQuote"
              class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
              <option :value="null">Sin plan</option>
              <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuota calculada</label>
            <div class="px-3 py-2 border rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
              <template v-if="quote">
                <span class="font-semibold text-gray-800 dark:text-white">${{ formatNumber(quote.total) }}</span>
                <span class="text-xs text-gray-400 ml-1">{{ periodLabel(quote.period) }}</span>
                <div v-if="quote.breakdown?.length" class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600 space-y-0.5">
                  <div v-for="item in quote.breakdown" :key="item.label" class="flex justify-between text-xs text-gray-600 dark:text-gray-300">
                    <span>{{ item.label }}</span>
                    <span class="font-medium text-gray-800 dark:text-white">${{ formatNumber(item.amount) }}</span>
                  </div>
                </div>
              </template>
              <span v-else-if="quoteError" class="text-red-500">{{ quoteError }}</span>
              <span v-else class="text-gray-400">—</span>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Familia</label>
            <button type="button" @click="addHijo" :disabled="hijos.length >= 7" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 disabled:opacity-40">
              + Agregar hijo ({{ hijos.length }}/7)
            </button>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Titular — edad *</label>
              <input v-model.number="titularAge" type="number" min="0" max="110" required
                @change="recalculateQuote"
                class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cónyuge — edad</label>
              <input v-model.number="conyugeAge" type="number" min="0" max="110"
                @change="recalculateQuote"
                class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            </div>
            <div v-for="(h, i) in hijos" :key="i" class="relative">
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hijo/a {{ i + 1 }} — edad</label>
              <input v-model.number="h.age" type="number" min="0" max="110"
                @change="recalculateQuote"
                class="w-full px-3 py-2 pr-8 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
              <button type="button" @click="removeHijo(i)" class="absolute right-2 top-7 text-gray-400 hover:text-red-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="canAssign" class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Asignar a</label>
          <select v-model="form.assigned_to" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            <option :value="auth.user.id">A mí mismo</option>
            <option v-for="u in assignableUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Etapa inicial</label>
          <select v-model="form.pipeline_stage_id" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            <option :value="null">Nuevo Lead</option>
            <option v-for="stage in stages" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fuente</label>
          <select v-model="form.source" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
            <option value="">Seleccionar...</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="facebook">Facebook</option>
            <option value="instagram">Instagram</option>
            <option value="website">Sitio Web</option>
            <option value="referral">Referido</option>
            <option value="call">Llamada</option>
            <option value="email">Email</option>
            <option value="walk_in">Presencial</option>
            <option value="other">Otro</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Empresa</label>
          <input v-model="form.company" placeholder="Nombre de empresa" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargo</label>
          <input v-model="form.position" placeholder="Cargo en la empresa" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
        </div>
      </div>

      <div v-if="showAdvanced" class="border-t border-gray-100 dark:border-gray-700 pt-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cierre estimado</label>
            <input v-model="form.expected_close_date" type="date" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
            <input v-model="form.address" placeholder="Dirección completa" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
            <textarea v-model="form.notes" rows="3" placeholder="Notas adicionales..." class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200"></textarea>
          </div>
        </div>
      </div>

      <button v-if="!canAssign && !isEdit" type="button" @click="showAdvanced = !showAdvanced" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 flex items-center gap-1">
        <svg class="w-3.5 h-3.5" :class="showAdvanced ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
        {{ showAdvanced ? 'Menos opciones' : 'Más opciones' }}
      </button>

      <p v-if="error" class="text-red-500 text-sm">{{ error }}</p>

      <div class="flex gap-3 pt-2">
        <button type="submit" :disabled="loading" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
          {{ loading ? 'Guardando...' : 'Guardar' }}
        </button>
        <router-link to="/contacts" class="px-4 py-2 border rounded-lg text-sm text-gray-600 dark:text-gray-400 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800">Cancelar</router-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const isEdit = !!route.params.id
const loading = ref(false)
const error = ref('')
const stages = ref([])
const assignableUsers = ref([])
const showAdvanced = ref(false)
const zones = ref([])
const localities = ref([])
const localityQuery = ref('')
const localityResults = ref([])
const showLocalityList = ref(false)
let localitySearchTimer = null

const plans = ref([])
const titularAge = ref(null)
const conyugeAge = ref(null)
const hijos = ref([])
const quote = ref(null)
const quoteError = ref('')
let quoteTimer = null

const canAssign = computed(() => auth.isAdmin || auth.isSupervisor)

const form = ref({
  name: '',
  dni: '',
  email: '',
  phone: '',
  company: '',
  position: '',
  source: '',
  notes: '',
  address: '',
  zone_id: null,
  locality_id: null,
  plan_id: null,
  pipeline_stage_id: null,
  expected_close_date: null,
  assigned_to: auth.user?.id || null,
})

onMounted(async () => {
  try {
    const [stagesRes, usersRes, zonesRes, plansRes] = await Promise.all([
      api.get('/pipelines'),
      api.get('/users'),
      api.get('/zones'),
      api.get('/plans'),
    ])
    stages.value = stagesRes.data.flatMap(p => p.stages)
    zones.value = zonesRes.data
    plans.value = plansRes.data.data || []

    if (canAssign.value) {
      assignableUsers.value = usersRes.data.filter(u => u.role === 'seller')
    }
  } catch (e) { console.error(e) }

  if (isEdit) {
    showAdvanced.value = true
    try {
      const { data } = await api.get(`/contacts/${route.params.id}`)
      form.value = {
        name: data.name,
        dni: data.dni,
        email: data.email,
        phone: data.phone,
        company: data.company,
        position: data.position,
        source: data.source || '',
        notes: data.notes || '',
        address: data.address || '',
        zone_id: data.zone_id,
        locality_id: data.locality_id,
        plan_id: data.plan_id,
        pipeline_stage_id: data.pipeline_stage_id,
        expected_close_date: data.expected_close_date?.split('T')[0] || null,
        assigned_to: data.assigned_to,
      }
      if (data.locality) {
        localityQuery.value = data.locality.name
      }
      const family = data.family_members || []
      titularAge.value = family.find(m => m.relation === 'titular')?.age ?? null
      conyugeAge.value = family.find(m => m.relation === 'conyuge')?.age ?? null
      hijos.value = family.filter(m => m.relation === 'hijo').map(m => ({ age: m.age }))
      recalculateQuote()
    } catch (e) { console.error(e) }
  }
})

async function searchLocalities() {
  showLocalityList.value = true
  clearTimeout(localitySearchTimer)
  localitySearchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/localities', { params: { search: localityQuery.value, limit: 30 } })
      localityResults.value = data
    } catch (e) { console.error(e) }
  }, 250)
}

function hideLocalities() {
  setTimeout(() => { showLocalityList.value = false }, 150)
}

function selectLocality(locality) {
  localityQuery.value = locality.name
  form.value.locality_id = locality.id
  if (locality.zone) {
    form.value.zone_id = locality.zone.id
  }
  showLocalityList.value = false
}

function addHijo() {
  if (hijos.value.length >= 7) return
  hijos.value.push({ age: null })
  recalculateQuote()
}

function removeHijo(i) {
  hijos.value.splice(i, 1)
  recalculateQuote()
}

function recalculateQuote() {
  quote.value = null
  quoteError.value = ''
  clearTimeout(quoteTimer)

  if (!form.value.plan_id || titularAge.value === null || titularAge.value === '') {
    return
  }

  quoteTimer = setTimeout(async () => {
    try {
      const { data } = await api.post('/plans/quote', {
        plan_id: form.value.plan_id,
        titular_age: titularAge.value,
        conyuge_age: conyugeAge.value || null,
        child_ages: hijos.value.map(h => h.age).filter(a => a !== null && a !== ''),
      })
      if (data.error) {
        quoteError.value = data.error
      } else {
        quote.value = data
      }
    } catch (e) {
      console.error(e)
    }
  }, 250)
}

function familyPayload() {
  const family = [{ relation: 'titular', age: titularAge.value }]
  if (conyugeAge.value !== null && conyugeAge.value !== '') {
    family.push({ relation: 'conyuge', age: conyugeAge.value })
  }
  for (const h of hijos.value) {
    if (h.age !== null && h.age !== '') {
      family.push({ relation: 'hijo', age: h.age })
    }
  }
  return family
}

async function save() {
  error.value = ''
  loading.value = true
  try {
    const payload = { ...form.value, family: familyPayload() }
    if (isEdit) {
      await api.put(`/contacts/${route.params.id}`, payload)
    } else {
      await api.post('/contacts', payload)
    }
    router.push('/contacts')
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    loading.value = false
  }
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(Number(n || 0).toFixed(2))
}

function periodLabel(period) {
  if (!period) return ''
  const [y, m] = period.split('-')
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
  return months[Number(m) - 1] + ' ' + y
}
</script>
