<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Pipeline Kanban</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Arrastrá contactos entre etapas</p>
      </div>
      <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
        <span>{{ contacts.length }} contactos</span>
        <span class="w-1 h-1 rounded-full bg-gray-300"></span>
        <span>{{ stages.length }} etapas</span>
      </div>
    </div>

    <Loader v-if="loading" />

    <div v-if="!loading && !stages.length" class="text-center py-20">
      <p class="text-gray-400 dark:text-gray-500 text-sm">No hay pipeline configurado</p>
      <p v-if="auth.isAdmin" class="text-xs text-gray-400 mt-1">Creá uno desde Pipelines</p>
    </div>

    <div v-if="!loading && stages.length" class="flex gap-4 overflow-x-auto pb-4" style="min-height: 70vh;">
      <div v-for="stage in stages" :key="stage.id" class="flex-shrink-0 w-72 bg-gray-100 dark:bg-gray-800 rounded-xl">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: stage.color }"></span>
              <h3 class="font-semibold text-sm text-gray-800 dark:text-white">{{ stage.name }}</h3>
            </div>
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-200 dark:bg-gray-700 px-2 py-0.5 rounded-full">
              {{ contactsByStage(stage.id).length }}
            </span>
          </div>
          <p class="text-xs text-gray-400 mt-1">${{ formatNumber(stageAmount(stage.id)) }}</p>
        </div>

        <div class="p-2 space-y-2 min-h-[200px]"
          @dragover.prevent="dragOver($event, stage.id)"
          @dragleave="dragLeave($event)"
          @drop="drop($event, stage.id)">
          <div v-for="contact in contactsByStage(stage.id)" :key="contact.id"
            :data-contact-id="contact.id"
            draggable="true"
            @dragstart="dragStart($event, contact, stage.id)"
            @dragend="dragEnd($event)"
            class="bg-white dark:bg-gray-900 rounded-lg p-3 shadow-sm border border-gray-200 dark:border-gray-600 cursor-grab active:cursor-grabbing hover:shadow-md transition-shadow"
            :class="{ 'opacity-50': dragging === contact.id }">
            <div class="flex items-start justify-between mb-1.5">
              <span class="text-sm font-medium text-gray-800 dark:text-white truncate flex-1">{{ contact.name }}</span>
              <span v-if="contact.lead_score" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full ml-2 flex-shrink-0"
                :class="scoreClass(contact.lead_score)">
                {{ contact.lead_score }}
              </span>
            </div>
            <div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
              <div v-if="contact.deal_value" class="font-medium text-gray-700 dark:text-gray-300">
                ${{ formatNumber(contact.deal_value) }}
              </div>
              <div v-if="contact.assigned_to" class="flex items-center gap-1">
                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                {{ contact.assigned_to?.name || contact.assigned_to_name || 'Sin asignar' }}
              </div>
              <div v-if="contact.company" class="truncate">{{ contact.company }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
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
const stages = ref([])
const contacts = ref([])
const loading = ref(true)
const dragging = ref(null)
const dragFromStage = ref(null)

function contactsByStage(stageId) {
  return contacts.value.filter(c => c.pipeline_stage_id === stageId)
}

function stageAmount(stageId) {
  return contacts.value
    .filter(c => c.pipeline_stage_id === stageId)
    .reduce((sum, c) => sum + (Number(c.deal_value) || 0), 0)
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(Number(n || 0).toFixed(0))
}

function scoreClass(score) {
  if (score >= 70) return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
  if (score >= 40) return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300'
  return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
}

function dragStart(e, contact, fromStage) {
  dragging.value = contact.id
  dragFromStage.value = fromStage
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', contact.id.toString())
}

function dragEnd() {
  dragging.value = null
  dragFromStage.value = null
}

function dragOver(e, stageId) {
  e.currentTarget.classList.add('bg-blue-50', 'dark:bg-blue-900/20')
}

function dragLeave(e) {
  e.currentTarget.classList.remove('bg-blue-50', 'dark:bg-blue-900/20')
}

async function drop(e, toStageId) {
  e.currentTarget.classList.remove('bg-blue-50', 'dark:bg-blue-900/20')
  const contactId = parseInt(e.dataTransfer.getData('text/plain'))
  if (!contactId || dragFromStage.value === toStageId) return

  try {
    await api.patch(`/contacts/${contactId}/stage`, { pipeline_stage_id: toStageId })
    const contact = contacts.value.find(c => c.id === contactId)
    if (contact) contact.pipeline_stage_id = toStageId
    toast.add('Contacto movido a ' + stages.value.find(s => s.id === toStageId)?.name, 'success')
  } catch (e) {
    toast.add('Error al mover contacto', 'error')
    console.error(e)
  }
}

onMounted(fetchData)

async function fetchData() {
  try {
    const [stagesRes, contactsRes] = await Promise.all([
      api.get('/pipelines'),
      api.get('/contacts', { params: { per_page: 200, archived: false } })
    ])
    const pipeline = stagesRes.data?.[0]
    stages.value = (pipeline?.stages || []).filter(Boolean)
    contacts.value = (contactsRes.data?.data || []).filter(Boolean)
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>
