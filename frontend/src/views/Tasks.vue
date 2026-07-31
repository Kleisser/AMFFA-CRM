<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Tareas</h1>
      <button @click="showForm = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">+ Nueva Tarea</button>
    </div>

    <div class="flex gap-2 mb-4">
      <button v-for="s in statuses" :key="s.key"
        @click="filterStatus = s.key"
        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
        :class="filterStatus === s.key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'">
        {{ s.label }}
      </button>
    </div>

    <Loader v-if="loading" />
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Título</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Contacto</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Prioridad</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Estado</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Vencimiento</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="task in tasks" :key="task.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ task.title }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ task.contact?.name || '-' }}</td>
            <td class="px-4 py-3">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="task.priority === 'high' ? 'bg-red-100 text-red-700' : task.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'">
                {{ task.priority }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ task.status }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ task.due_date ? formatDate(task.due_date) : '-' }}</td>
            <td class="px-4 py-3 text-right">
              <select :value="task.status" @change="updateStatus(task, $event.target.value)"
                class="text-xs border rounded px-2 py-1">
                <option value="pending">Pendiente</option>
                <option value="in_progress">En Progreso</option>
                <option value="completed">Completada</option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="!tasks.length" class="text-center py-12 text-gray-400">Sin tareas</div>
    </div>

    <div v-if="showForm" class="fixed inset-0 bg-black/30 z-50 flex items-center justify-center" @click.self="showForm = false">
      <div class="bg-white rounded-2xl p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Nueva Tarea</h2>
        <form @submit.prevent="createTask" class="space-y-3">
          <input v-model="newTask.title" placeholder="Título" required
            class="w-full px-3 py-2 border rounded-lg text-sm">
          <select v-model="newTask.priority" class="w-full px-3 py-2 border rounded-lg text-sm">
            <option value="low">Baja</option>
            <option value="medium">Media</option>
            <option value="high">Alta</option>
          </select>
          <select v-model="newTask.type" class="w-full px-3 py-2 border rounded-lg text-sm">
            <option value="follow_up">Seguimiento</option>
            <option value="call">Llamada</option>
            <option value="visit">Visita</option>
            <option value="email">Email</option>
            <option value="meeting">Reunión</option>
          </select>
          <input v-model="newTask.due_date" type="datetime-local" class="w-full px-3 py-2 border rounded-lg text-sm">
          <input v-model="newTask.assigned_to" placeholder="ID Usuario (opcional)" type="number" class="w-full px-3 py-2 border rounded-lg text-sm">
          <div class="flex gap-2 pt-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Crear</button>
            <button type="button" @click="showForm = false" class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../api/axios'
import Loader from '../components/Loader.vue'

const tasks = ref([])
const filterStatus = ref('all')
const showForm = ref(false)
const newTask = ref({ title: '', priority: 'medium', type: 'follow_up', due_date: '', assigned_to: '' })
const loading = ref(true)

const statuses = [
  { key: 'all', label: 'Todas' },
  { key: 'pending', label: 'Pendientes' },
  { key: 'in_progress', label: 'En Progreso' },
  { key: 'completed', label: 'Completadas' },
]

onMounted(() => loadTasks())
watch(filterStatus, () => loadTasks())

async function loadTasks() {
  loading.value = true
  try {
    const params = {}
    if (filterStatus.value !== 'all') params.status = filterStatus.value
    const { data } = await api.get('/tasks', { params })
    tasks.value = data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function createTask() {
  try {
    await api.post('/tasks', {
      title: newTask.value.title,
      priority: newTask.value.priority,
      type: newTask.value.type,
      due_date: newTask.value.due_date || null,
      assigned_to: newTask.value.assigned_to || (await api.get('/auth/user')).data.id,
    })
    showForm.value = false
    newTask.value = { title: '', priority: 'medium', type: 'follow_up', due_date: '', assigned_to: '' }
    loadTasks()
  } catch (e) { console.error(e) }
}

async function updateStatus(task, status) {
  try {
    await api.put(`/tasks/${task.id}`, { status })
    task.status = status
  } catch (e) { console.error(e) }
}

function formatDate(d) {
  return new Date(d).toLocaleDateString('es-AR', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>
