<template>
  <div v-if="contact">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <router-link to="/contacts" class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300">&larr; Volver</router-link>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ contact.name }}</h1>
        <span v-if="contact.lead_score" class="text-xs font-bold px-2 py-0.5 rounded-full"
          :class="scoreClass(contact.lead_score)">Score: {{ contact.lead_score }}</span>
      </div>
      <div class="flex gap-2">
        <router-link :to="`/contacts/${contact.id}/edit`" class="px-3 py-1.5 border border-gray-200 dark:border-gray-600 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">Editar</router-link>
        <button @click="openConversation" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Enviar mensaje</button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Información</h2>
          <dl class="grid grid-cols-2 gap-4">
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">DNI</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.dni || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Email</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.email || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Teléfono</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.phone || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Empresa</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.company || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Cargo</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.position || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Fuente</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.source || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Zona</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.zone?.name || '-' }} <template v-if="contact.locality">({{ contact.locality.name }})</template></dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Dirección</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.address || '-' }}</dd></div>

          </dl>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Plan y Familia</h2>
          <dl class="grid grid-cols-2 gap-4 mb-4">
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Plan</dt><dd class="text-sm text-gray-800 dark:text-white">{{ contact.plan?.name || '-' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Cuota</dt><dd class="text-sm font-medium text-gray-800 dark:text-white">{{ contact.deal_value ? '$' + formatNumber(contact.deal_value) : '-' }}</dd></div>
          </dl>
          <div v-if="contact.family_members?.length" class="flex flex-wrap gap-2 mb-4">
            <span v-for="m in contact.family_members" :key="m.id"
              class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
              <span class="capitalize">{{ relationLabel(m.relation) }}</span>
              <span class="text-gray-400">·</span>
              {{ m.age }} años
            </span>
          </div>
          <p v-else class="text-xs text-gray-400 mb-4">Sin familia cargada</p>
          <div v-if="quoteBreakdown?.length" class="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-1">
            <div v-for="item in quoteBreakdown" :key="item.label" class="flex justify-between text-xs text-gray-600 dark:text-gray-300">
              <span>{{ item.label }}</span>
              <span class="font-medium text-gray-800 dark:text-white">${{ formatNumber(item.amount) }}</span>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Pipeline</h2>
          <div class="flex items-center gap-4">
            <span v-if="contact.pipeline_stage" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium"
              :style="{ backgroundColor: contact.pipeline_stage.color + '20', color: contact.pipeline_stage.color }">
              <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: contact.pipeline_stage.color }"></span>
              {{ contact.pipeline_stage.name }}
            </span>
            <span v-if="contact.deal_value" class="text-lg font-bold text-gray-800 dark:text-white">${{ formatNumber(contact.deal_value) }}</span>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Línea de Tiempo</h2>
          <div v-if="timeline.length" class="relative pl-6 before:absolute before:left-2 before:top-1 before:bottom-1 before:w-0.5 before:bg-gray-200 dark:before:bg-gray-700">
            <div v-for="item in timeline" :key="item.id" class="relative pb-5 last:pb-0">
              <div class="absolute -left-[18px] top-1 w-3 h-3 rounded-full border-2"
                :class="timelineDot(item.type)"></div>
              <div class="flex items-start gap-2">
                <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap mt-0.5">{{ formatTime(item.created_at) }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-800 dark:text-white">{{ item.title }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ item.description }}</p>
                  <div class="flex items-center gap-2 mt-1">
                    <span class="text-[10px] text-gray-400">{{ item.user }}</span>
                    <span v-if="item.status" class="text-[10px] px-1.5 py-0.5 rounded-full"
                      :class="timelineStatusClass(item.type, item.status)">
                      {{ item.status }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 py-4 text-center">Sin actividad registrada</p>
        </div>
      </div>

      <div class="space-y-6">
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Tareas</h2>
          <div v-for="task in contact.tasks" :key="task.id" class="flex items-start gap-2 mb-3">
            <div class="w-1.5 h-1.5 rounded-full mt-1.5"
              :class="task.priority === 'high' ? 'bg-red-500' : task.priority === 'medium' ? 'bg-yellow-500' : 'bg-green-500'">
            </div>
            <div>
              <p class="text-sm font-medium text-gray-800 dark:text-white">{{ task.title }}</p>
              <p class="text-xs text-gray-500">{{ task.status }}</p>
            </div>
          </div>
          <p v-if="!contact.tasks?.length" class="text-sm text-gray-400">Sin tareas</p>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Conversaciones</h2>
          <div v-for="conv in contact.conversations" :key="conv.id" class="text-sm text-gray-600 dark:text-gray-300 mb-2">
            <span class="capitalize">{{ conv.channel }}</span> - {{ conv.status }}
          </div>
          <p v-if="!contact.conversations?.length" class="text-sm text-gray-400">Sin conversaciones</p>
        </div>

        <div v-if="contact.products?.length" class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
          <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Productos</h2>
          <div v-for="item in contact.products" :key="item.id" class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-800 dark:text-white">{{ item.name || item.pivot?.product_name }}</span>
            <span class="text-sm font-medium text-gray-800 dark:text-white">x{{ item.pivot?.quantity || 1 }} - ${{ formatNumber(item.pivot?.price || 0) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div v-else class="text-center py-12 text-gray-400">Cargando...</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api/axios'

const route = useRoute()
const router = useRouter()
const contact = ref(null)
const timeline = ref([])
const quoteBreakdown = ref([])

onMounted(fetchContact)

async function fetchContact() {
  try {
    const [contactRes, timelineRes] = await Promise.all([
      api.get(`/contacts/${route.params.id}`),
      api.get(`/contacts/${route.params.id}/timeline`).catch(() => null)
    ])
    contact.value = contactRes.data
    timeline.value = timelineRes?.data || []
    await fetchQuoteBreakdown(contact.value)
  } catch (e) {
    console.error(e)
  }
}

async function fetchQuoteBreakdown(contact) {
  const family = contact?.family_members || []
  if (!contact?.plan_id || !family.length) return
  try {
    const { data } = await api.post('/plans/quote', {
      plan_id: contact.plan_id,
      titular_age: family.find(m => m.relation === 'titular')?.age ?? null,
      conyuge_age: family.find(m => m.relation === 'conyuge')?.age ?? null,
      child_ages: family.filter(m => m.relation === 'hijo').map(m => m.age),
    })
    quoteBreakdown.value = data.breakdown || []
  } catch (e) {
    console.error(e)
  }
}

function scoreClass(score) {
  if (score >= 70) return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
  if (score >= 40) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300'
  return 'bg-gray-100 text-gray-600'
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(n || 0)
}

function openConversation() {
  if (contact.value?.id) router.push(`/conversations?contact_id=${contact.value.id}`)
}

function formatTime(date) {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function relationLabel(relation) {
  const map = { titular: 'Titular', conyuge: 'Cónyuge', hijo: 'Hijo/a' }
  return map[relation] || relation
}

function timelineDot(type) {
  const map = {
    call: 'border-green-500 bg-green-100 dark:bg-green-900/50',
    task: 'border-blue-500 bg-blue-100 dark:bg-blue-900/50',
    note: 'border-yellow-500 bg-yellow-100 dark:bg-yellow-900/50',
    visit: 'border-purple-500 bg-purple-100 dark:bg-purple-900/50',
    message: 'border-cyan-500 bg-cyan-100 dark:bg-cyan-900/50',
    activity: 'border-gray-500 bg-gray-100 dark:bg-gray-800',
  }
  return map[type] || map.activity
}

function timelineStatusClass(type, status) {
  if (type === 'call') {
    return status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
  }
  if (type === 'task') {
    return status === 'completed' ? 'bg-green-100 text-green-700' : status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700'
  }
  return 'bg-gray-100 text-gray-600'
}
</script>
