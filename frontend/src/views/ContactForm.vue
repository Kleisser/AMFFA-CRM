<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">{{ isEdit ? 'Editar Contacto' : 'Nuevo Contacto' }}</h1>

    <form @submit.prevent="save" class="max-w-2xl bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 space-y-4">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
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
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor del Deal</label>
            <input v-model="form.deal_value" type="number" step="0.01" placeholder="0.00" class="w-full px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200">
          </div>
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

const canAssign = computed(() => auth.isAdmin || auth.isSupervisor)

const form = ref({
  name: '',
  email: '',
  phone: '',
  company: '',
  position: '',
  source: '',
  notes: '',
  address: '',
  pipeline_stage_id: null,
  deal_value: null,
  expected_close_date: null,
  assigned_to: auth.user?.id || null,
})

onMounted(async () => {
  try {
    const [stagesRes, usersRes] = await Promise.all([
      api.get('/pipelines'),
      api.get('/users'),
    ])
    stages.value = stagesRes.data.flatMap(p => p.stages)

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
        email: data.email,
        phone: data.phone,
        company: data.company,
        position: data.position,
        source: data.source || '',
        notes: data.notes || '',
        address: data.address || '',
        pipeline_stage_id: data.pipeline_stage_id,
        deal_value: data.deal_value,
        expected_close_date: data.expected_close_date?.split('T')[0] || null,
        assigned_to: data.assigned_to,
      }
    } catch (e) { console.error(e) }
  }
})

async function save() {
  error.value = ''
  loading.value = true
  try {
    if (isEdit) {
      await api.put(`/contacts/${route.params.id}`, form.value)
    } else {
      await api.post('/contacts', form.value)
    }
    router.push('/contacts')
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    loading.value = false
  }
}
</script>
