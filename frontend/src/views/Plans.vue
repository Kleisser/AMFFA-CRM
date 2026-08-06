<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Planes</h1>
      <div class="flex gap-2">
        <button v-if="isAdmin" @click="openIncrease" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
          + Aumento
        </button>
        <button v-if="isAdmin" @click="openPlanForm(null)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
          + Nuevo Plan
        </button>
      </div>
    </div>

    <Loader v-if="loading" />
    <div v-else class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-100 dark:border-gray-700">
        <input v-model="search" @input="debouncedSearch" type="text" placeholder="Buscar planes..."
          class="w-full max-w-md px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div v-for="p in plans" :key="p.id" class="border-b border-gray-100 dark:border-gray-700 last:border-b-0">
        <div class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50">
          <div class="flex items-center gap-3">
            <button @click="togglePlan(p.id)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-transform" :class="expanded === p.id ? 'rotate-90' : ''">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
            <span class="font-medium text-gray-800 dark:text-white">{{ p.name }}</span>
            <span v-if="!p.is_active" class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700">Archivado</span>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600 dark:text-gray-300">
              <template v-if="p.prices?.length">
                <span class="font-semibold text-gray-800 dark:text-white">${{ formatNumber(priceLabel(p.prices[0])) }}</span>
                <span class="text-xs text-gray-400 ml-1">({{ periodLabel(p.prices[0].period) }})</span>
              </template>
              <span v-else class="text-xs text-gray-400">Sin precio</span>
            </span>
            <div v-if="isAdmin" class="flex gap-2">
              <button @click="openPriceEditor(p)" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800">Precios</button>
              <button @click="openPlanForm(p)" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800">Editar</button>
              <button @click="archivePlan(p)" class="text-xs text-red-500 hover:text-red-700">Archivar</button>
            </div>
          </div>
        </div>

        <div v-if="expanded === p.id" class="px-4 pb-4 bg-gray-50 dark:bg-gray-800/30">
          <p v-if="p.description" class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ p.description }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Historial de precios por mes (por edad):</p>
          <table class="w-full text-sm" v-if="p.prices?.length">
            <thead>
              <tr class="text-left text-xs text-gray-400 uppercase">
                <th class="py-1 pr-3">Mes</th>
                <th class="py-1 pr-3">Titular / Cónyuge por edad</th>
                <th class="py-1 pr-3">Hijos</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="price in p.prices" :key="price.period">
                <td class="py-1.5 pr-3 text-gray-700 dark:text-gray-300 align-top whitespace-nowrap">{{ periodLabel(price.period) }}</td>
                <td class="py-1.5 pr-3 align-top">
                  <template v-if="price.structure?.manual">
                    <span class="text-gray-800 dark:text-white font-medium">${{ formatNumber(price.structure.manual_price) }}</span>
                    <span class="text-xs text-gray-400 ml-1">(precio manual)</span>
                  </template>
                  <template v-else>
                    <div v-for="(b, i) in adultsWithRanges(price.structure)" :key="i" class="text-xs text-gray-700 dark:text-gray-300 whitespace-nowrap">
                      <span class="text-gray-400 inline-block w-16">{{ b.range }}</span>
                      <span class="font-medium text-gray-800 dark:text-white">${{ formatNumber(b.price) }}</span>
                    </div>
                  </template>
                </td>
                <td class="py-1.5 pr-3 text-xs text-gray-500 dark:text-gray-400 align-top">{{ childrenLabel(price.structure) }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="text-xs text-gray-400">Sin precios cargados.</p>
        </div>
      </div>

      <div v-if="!plans.length" class="text-center py-12 text-gray-400">Sin planes</div>
    </div>

    <div v-if="isAdmin" class="mt-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
      <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Registro de Aumentos</h2>
      <Loader v-if="increasesLoading" />
      <table v-else class="w-full text-sm">
        <thead class="text-left text-xs text-gray-400 uppercase border-b border-gray-100 dark:border-gray-700">
          <tr>
            <th class="py-2 pr-3">Fecha</th>
            <th class="py-2 pr-3">Porcentaje</th>
            <th class="py-2 pr-3">Desde</th>
            <th class="py-2 pr-3">Hasta</th>
            <th class="py-2 pr-3">Planes</th>
            <th class="py-2 pr-3">Aplicado por</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr v-for="inc in increases" :key="inc.id">
            <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">{{ formatDate(inc.created_at) }}</td>
            <td class="py-2 pr-3 text-gray-800 dark:text-white font-medium">{{ inc.percentage }}%</td>
            <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ periodLabel(inc.from_period) }}</td>
            <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ periodLabel(inc.to_period) }}</td>
            <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ inc.plan_ids?.length || 0 }} planes</td>
            <td class="py-2 pr-3 text-gray-600 dark:text-gray-400">{{ inc.user?.name || '-' }}</td>
          </tr>
          <tr v-if="!increases.length"><td colspan="6" class="py-4 text-center text-gray-400">Sin aumentos registrados</td></tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="showPlanForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPlanForm = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-lg shadow-xl">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">{{ editingPlan ? 'Editar Plan' : 'Nuevo Plan' }}</h3>
          <form @submit.prevent="savePlan" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
              <input v-model="planForm.name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
              <textarea v-model="planForm.description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showPlanForm = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
              <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ editingPlan ? 'Guardar' : 'Crear' }}</button>
            </div>
          </form>
        </div>
      </div>

      <div v-if="showPriceEditor" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showPriceEditor = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-2xl shadow-xl max-h-[90vh] overflow-y-auto">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Precios — {{ pricePlan?.name }}</h3>

          <div class="grid grid-cols-3 gap-3 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mes (YYYY-MM)</label>
              <input v-model="priceForm.period" required pattern="\d{4}-\d{2}" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div class="col-span-2 flex items-end pb-1">
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" v-model="priceForm.manual" class="rounded" />
                Precio manual (sin fórmula por edades)
              </label>
            </div>
          </div>

          <div v-if="priceForm.manual" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio manual</label>
            <input v-model.number="priceForm.manual_price" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
          </div>

          <template v-else>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase">Precio por edad — Titular y Cónyuge</p>
            <div class="space-y-2 mb-3">
              <div v-for="(b, i) in priceForm.brackets" :key="i" class="flex items-center gap-3">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 w-24 whitespace-nowrap">{{ bracketRangeLabel(i) }}</label>
                <input v-model.number="b.price" type="number" step="0.01" placeholder="0.00" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
                <button v-if="priceForm.brackets.length > 1" type="button" @click="removeBracket(i)" class="text-gray-400 hover:text-red-500">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
            <div class="flex items-center gap-3 mb-4">
              <button type="button" @click="addBracket" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700">+ Agregar tramo de edad</button>
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 ml-auto">
                <input type="checkbox" v-model="priceForm.has_conyuge" class="rounded" />
                Incluye cónyuge
              </label>
            </div>

            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase">Hijos</p>
            <div class="flex gap-3 mb-3">
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 flex-1">Modo</label>
              <select v-model="priceForm.children_mode" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
                <option value="none">Sin hijos</option>
                <option value="flat">Monto fijo por hijo</option>
                <option value="age">Por edad del hijo</option>
              </select>
            </div>

            <div v-if="priceForm.children_mode === 'flat'" class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">1er hijo</label>
                <input v-model.number="priceForm.children_first" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Resto de hijos</label>
                <input v-model.number="priceForm.children_rest" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
            </div>

            <div v-if="priceForm.children_mode === 'age'" class="space-y-3 mb-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Hasta edad gratis</label>
                <input v-model.number="priceForm.children_free_until" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
              <div v-for="(tier, i) in priceForm.children_tiers" :key="i" class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ tier.max_age === null ? '21+ (resto)' : 'Hasta ' + tier.max_age }}</label>
                  <input v-model.number="tier.max_age" type="number" min="1" :disabled="i === priceForm.children_tiers.length - 1" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm disabled:opacity-50" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">1er hijo</label>
                  <input v-model.number="tier.first" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Resto</label>
                  <input v-model.number="tier.rest" type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
                </div>
              </div>
            </div>
          </template>

          <p v-if="priceError" class="text-red-500 text-xs mb-2">{{ priceError }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showPriceEditor = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
            <button @click="savePrice" :disabled="priceSaving" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">{{ priceSaving ? 'Guardando...' : 'Guardar precio' }}</button>
          </div>
        </div>
      </div>

      <div v-if="showIncrease" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showIncrease = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-lg shadow-xl">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">Aplicar Aumento</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Genera el precio del mes siguiente aplicando el porcentaje sobre el último precio de cada plan.</p>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Porcentaje (%)</label>
            <input v-model.number="increaseForm.percentage" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
          </div>

          <div class="mb-4">
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 mb-2">
              <input type="checkbox" v-model="increaseAll" class="rounded" />
              Aplicar a todos los planes
            </label>
            <div v-if="!increaseAll" class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
              <label v-for="p in plans" :key="p.id" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" :value="p.id" v-model="increaseForm.plan_ids" class="rounded" />
                {{ p.name }}
              </label>
            </div>
          </div>

          <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Mes generado: <span class="font-medium text-gray-700 dark:text-gray-300">{{ nextPeriodLabel }}</span>
          </p>

          <p v-if="increaseError" class="text-red-500 text-xs mb-2">{{ increaseError }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showIncrease = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
            <button @click="applyIncrease" :disabled="increaseSaving" class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">{{ increaseSaving ? 'Aplicando...' : 'Aplicar aumento' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import Loader from '../components/Loader.vue'

const auth = useAuthStore()
const toast = useToastStore()

const isAdmin = computed(() => auth.isAdmin)
const plans = ref([])
const search = ref('')
const loading = ref(true)
const expanded = ref(null)
let searchTimeout = null

const showPlanForm = ref(false)
const editingPlan = ref(null)
const planForm = ref({ name: '', description: '' })

const showPriceEditor = ref(false)
const pricePlan = ref(null)
const priceForm = ref({})
const priceError = ref('')
const priceSaving = ref(false)

const showIncrease = ref(false)
const increaseAll = ref(true)
const increaseForm = ref({ percentage: null, plan_ids: [] })
const increaseError = ref('')
const increaseSaving = ref(false)

const increases = ref([])
const increasesLoading = ref(true)

onMounted(() => {
  loadPlans()
  if (isAdmin.value) loadIncreases()
})

async function loadPlans() {
  loading.value = true
  try {
    const params = { per_page: 100 }
    if (search.value) params.search = search.value
    const { data } = await api.get('/plans', { params })
    plans.value = data.data || []
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function loadIncreases() {
  increasesLoading.value = true
  try {
    const { data } = await api.get('/plan-increases', { params: { per_page: 20 } })
    increases.value = data.data || []
  } catch (e) { console.error(e) }
  finally { increasesLoading.value = false }
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(loadPlans, 300)
}

function togglePlan(id) {
  expanded.value = expanded.value === id ? null : id
}

function openPlanForm(p) {
  editingPlan.value = p
  planForm.value = p ? { name: p.name, description: p.description || '' } : { name: '', description: '' }
  showPlanForm.value = true
}

async function savePlan() {
  try {
    if (editingPlan.value) {
      await api.put(`/plans/${editingPlan.value.id}`, planForm.value)
      toast.add('Plan actualizado', 'success')
    } else {
      await api.post('/plans', planForm.value)
      toast.add('Plan creado', 'success')
    }
    showPlanForm.value = false
    loadPlans()
  } catch (e) {
    toast.add('Error al guardar el plan', 'error')
    console.error(e)
  }
}

async function archivePlan(p) {
  if (!confirm(`¿Archivar el plan ${p.name}?`)) return
  try {
    await api.delete(`/plans/${p.id}`)
    toast.add('Plan archivado', 'success')
    loadPlans()
  } catch (e) {
    toast.add('Error al archivar', 'error')
  }
}

const DEFAULT_BRACKET_MAXES = [35, 40, 45, 50, 55, 60, null]

function bracketRangeLabel(i) {
  const brackets = priceForm.value.brackets
  const maxes = brackets.map(b => b.max_age)
  const prevMax = i === 0 ? null : maxes[i - 1]
  const max = maxes[i]
  if (prevMax === null || prevMax === undefined) {
    return max === null || max === undefined ? 'Todas las edades' : `0-${max}`
  }
  if (max === null || max === undefined) return `${prevMax + 1}+`
  return `${prevMax + 1}-${max}`
}

function adultsWithRanges(structure) {
  const adults = structure?.adults || []
  let prevMax = null
  return adults.map(b => {
    const range = prevMax === null
      ? (b.max_age === null ? 'todas' : `0-${b.max_age}`)
      : (b.max_age === null ? `${prevMax + 1}+` : `${prevMax + 1}-${b.max_age}`)
    prevMax = b.max_age
    return { range, price: b.price }
  })
}

function childrenLabel(structure) {
  const children = structure?.children || { mode: 'none' }
  if (structure?.manual) return '—'
  if (children.mode === 'none' || children.mode === '') return 'Sin hijos'
  if (children.mode === 'flat') return `1er hijo $${formatNumber(children.first)} · resto $${formatNumber(children.rest)}`
  if (children.mode === 'age') {
    const parts = [`Gratis hasta ${children.free_until} años`]
    for (const t of children.tiers || []) {
      const range = t.max_age === null ? `${children.free_until + 1}+ años` : `${children.free_until + 1}-${t.max_age} años`
      parts.push(`${range}: 1° $${formatNumber(t.first)} · resto $${formatNumber(t.rest)}`)
    }
    return parts.join(' · ')
  }
  return '—'
}

function openPriceEditor(p) {
  pricePlan.value = p
  priceError.value = ''
  const latest = p.prices?.[0]

  const adults = latest?.structure?.adults?.length
    ? latest.structure.adults.map(b => ({ max_age: b.max_age, price: b.price }))
    : DEFAULT_BRACKET_MAXES.map(max_age => ({ max_age, price: null }))

  const children = latest?.structure?.children || { mode: 'none' }
  priceForm.value = {
    period: latest?.period || '',
    manual: !!latest?.structure?.manual,
    manual_price: latest?.structure?.manual_price ?? null,
    brackets: adults,
    has_conyuge: latest?.structure?.has_conyuge !== false,
    children_mode: children.mode || 'none',
    children_first: children.first ?? null,
    children_rest: children.rest ?? null,
    children_free_until: children.free_until ?? 15,
    children_tiers: (children.tiers || [{ max_age: 21, first: null, rest: null }, { max_age: null, first: null, rest: null }]).map(t => ({ ...t })),
  }
  showPriceEditor.value = true
}

function addBracket() {
  priceForm.value.brackets.push({ max_age: null, price: null })
}

function removeBracket(i) {
  if (priceForm.value.brackets.length <= 1) return
  priceForm.value.brackets.splice(i, 1)
}

async function savePrice() {
  priceError.value = ''
  priceSaving.value = true
  try {
    let structure
    if (priceForm.value.manual) {
      structure = { manual: true, manual_price: priceForm.value.manual_price ?? 0 }
    } else {
      const adults = priceForm.value.brackets
        .map(b => ({ max_age: b.max_age ?? null, price: b.price }))
        .filter(b => b.price !== null && b.price !== '' && b.price !== undefined)
        .sort((a, b) => (a.max_age === null ? 1 : b.max_age === null ? -1 : a.max_age - b.max_age))

      let children = { mode: priceForm.value.children_mode }
      if (children.mode === 'flat') {
        children.first = priceForm.value.children_first ?? 0
        children.rest = priceForm.value.children_rest ?? 0
      } else if (children.mode === 'age') {
        children.free_until = priceForm.value.children_free_until ?? 15
        children.tiers = priceForm.value.children_tiers.map(t => ({
          max_age: t.max_age ?? null,
          first: t.first ?? 0,
          rest: t.rest ?? 0,
        }))
      }

      structure = {
        manual: false,
        has_conyuge: priceForm.value.has_conyuge,
        adults,
        children,
      }
    }

    await api.post(`/plans/${pricePlan.value.id}/prices`, {
      period: priceForm.value.period,
      structure,
    })
    toast.add('Precio guardado', 'success')
    showPriceEditor.value = false
    loadPlans()
  } catch (e) {
    priceError.value = e.response?.data?.message || 'Error al guardar el precio'
  } finally {
    priceSaving.value = false
  }
}

const nextPeriodLabel = computed(() => {
  const periods = plans.value.map(p => p.prices?.[0]?.period).filter(Boolean)
  if (!periods.length) return '—'
  const latest = periods.sort().pop()
  const [y, m] = latest.split('-').map(Number)
  const d = new Date(y, m - 1 + 1, 1)
  return `${String(d.getFullYear()).padStart(4, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}`
})

function openIncrease() {
  increaseForm.value = { percentage: null, plan_ids: [] }
  increaseAll.value = true
  increaseError.value = ''
  showIncrease.value = true
}

async function applyIncrease() {
  increaseError.value = ''
  increaseSaving.value = true
  try {
    const payload = { percentage: increaseForm.value.percentage }
    if (!increaseAll.value) payload.plan_ids = increaseForm.value.plan_ids
    const { data } = await api.post('/plans/increase', payload)
    toast.add(data.message || 'Aumento aplicado', 'success')
    showIncrease.value = false
    loadPlans()
    loadIncreases()
  } catch (e) {
    increaseError.value = e.response?.data?.message || 'Error al aplicar el aumento'
  } finally {
    increaseSaving.value = false
  }
}

function priceLabel(price) {
  const s = price.structure || {}
  if (s.manual) return s.manual_price ?? 0
  return s.adults?.[0]?.price ?? 0
}

function structureLabel(structure) {
  if (structure?.manual) return 'Manual'
  const adults = structure?.adults?.length || 0
  const children = structure?.children?.mode || 'none'
  const parts = [`${adults} tramos edad`]
  if (children === 'flat') parts.push('hijos monto fijo')
  else if (children === 'age') parts.push('hijos por edad')
  return parts.join(' · ')
}

function periodLabel(period) {
  if (!period) return '-'
  const [y, m] = period.split('-')
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
  return `${months[Number(m) - 1]} ${y}`
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(Number(n || 0).toFixed(2))
}
</script>
