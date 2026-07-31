<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Pipelines</h1>

    <Loader v-if="loading" />
    <div v-else class="grid grid-cols-1 gap-6">
      <div v-for="pipeline in pipelines" :key="pipeline.id" class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ pipeline.name }}</h2>
        <div class="flex gap-3 overflow-x-auto pb-2">
          <div v-for="stage in pipeline.stages" :key="stage.id" class="flex-shrink-0 w-48">
            <div class="p-3 rounded-lg" :style="{ backgroundColor: stage.color + '15' }">
              <div class="flex items-center gap-2 mb-2">
                <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: stage.color }"></span>
                <span class="text-sm font-medium" :style="{ color: stage.color }">{{ stage.name }}</span>
              </div>
              <p class="text-xs text-gray-500">Orden: {{ stage.order }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api/axios'
import Loader from '../components/Loader.vue'

const pipelines = ref([])
const loading = ref(true)

onMounted(async () => {
  loading.value = true
  try {
    const { data } = await api.get('/pipelines')
    pipelines.value = data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
})
</script>
