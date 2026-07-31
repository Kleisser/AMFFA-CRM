<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Bandeja de Conversaciones</h1>

    <div class="flex gap-4 mb-4">
      <button v-for="channel in channels" :key="channel.key"
        @click="filterChannel = channel.key"
        class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
        :class="filterChannel === channel.key ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 border hover:bg-gray-50'">
        {{ channel.label }}
      </button>
    </div>

    <Loader v-if="loading" />
    <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="divide-y divide-gray-100">
        <div v-for="conv in conversations" :key="conv.id"
          @click="selectConversation(conv)"
          class="p-4 hover:bg-gray-50 cursor-pointer transition-colors"
          :class="selected?.id === conv.id ? 'bg-blue-50' : ''"
        >
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium text-gray-600">
                {{ conv.contact?.name?.charAt(0) }}
              </div>
              <div>
                <p class="text-sm font-medium text-gray-800">{{ conv.contact?.name }}</p>
                <p class="text-xs text-gray-500">{{ conv.subject || 'Sin asunto' }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-400">{{ conv.last_message_at ? timeAgo(conv.last_message_at) : '' }}</span>
              <span class="w-2 h-2 rounded-full"
                :class="conv.status === 'open' ? 'bg-green-500' : 'bg-gray-300'">
              </span>
            </div>
          </div>
          <div v-if="conv.messages?.[0]" class="mt-2 text-sm text-gray-500 truncate">
            {{ conv.messages[0].content }}
          </div>
        </div>
      </div>
      <div v-if="!conversations.length" class="text-center py-12 text-gray-400">Sin conversaciones</div>
    </div>

    <div v-if="selected" class="fixed inset-0 bg-black/30 z-50 flex items-end sm:items-center justify-center" @click.self="selected = null">
      <div class="bg-white w-full sm:max-w-lg sm:rounded-2xl rounded-t-2xl h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium">
              {{ selected.contact?.name?.charAt(0) }}
            </div>
            <div>
              <p class="text-sm font-medium">{{ selected.contact?.name }}</p>
              <p class="text-xs text-gray-500 capitalize">{{ selected.channel }}</p>
            </div>
          </div>
          <button @click="selected = null" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3">
          <div v-for="msg in messages" :key="msg.id"
            class="flex" :class="msg.direction === 'outgoing' ? 'justify-end' : 'justify-start'">
            <div class="max-w-[80%] px-3 py-2 rounded-xl text-sm"
              :class="msg.direction === 'outgoing' ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-gray-100 text-gray-800 rounded-bl-sm'">
              {{ msg.content }}
            </div>
          </div>
        </div>
        <div class="p-4 border-t">
          <form @submit.prevent="sendMessage" class="flex gap-2">
            <input v-model="newMessage" type="text" placeholder="Escribe un mensaje..."
              class="flex-1 px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" :disabled="!newMessage.trim()"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
              Enviar
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api/axios'
import Loader from '../components/Loader.vue'

const route = useRoute()
const conversations = ref([])
const selected = ref(null)
const messages = ref([])
const newMessage = ref('')
const filterChannel = ref('all')
const loading = ref(true)

const channels = [
  { key: 'all', label: 'Todas' },
  { key: 'whatsapp', label: 'WhatsApp' },
  { key: 'email', label: 'Email' },
  { key: 'facebook', label: 'Facebook' },
  { key: 'instagram', label: 'Instagram' },
]

onMounted(() => {
  loadConversations()
  if (route.query.contact_id) {
    // Auto-select or create conversation for this contact
  }
})

watch(filterChannel, () => loadConversations())

async function loadConversations() {
  loading.value = true
  try {
    const params = {}
    if (filterChannel.value !== 'all') params.channel = filterChannel.value
    const { data } = await api.get('/conversations', { params })
    conversations.value = data.data || data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function selectConversation(conv) {
  selected.value = conv
  try {
    const { data } = await api.get(`/conversations/${conv.id}`)
    messages.value = data.messages || []
  } catch (e) { console.error(e) }
}

async function sendMessage() {
  if (!newMessage.value.trim() || !selected.value) return
  try {
    const { data } = await api.post(`/conversations/${selected.value.id}/messages`, {
      content: newMessage.value.trim()
    })
    messages.value.push(data)
    newMessage.value = ''
    selected.value.last_message_at = new Date().toISOString()
  } catch (e) { console.error(e) }
}

function timeAgo(date) {
  const diff = Date.now() - new Date(date).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 60) return `${mins}m`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h`
  return `${Math.floor(hours / 24)}d`
}
</script>
