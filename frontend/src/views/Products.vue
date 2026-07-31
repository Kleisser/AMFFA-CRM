<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Productos</h1>
      <button @click="showForm = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
        + Nuevo Producto
      </button>
    </div>

    <Loader v-if="loading" />
    <div v-else class="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
      <div class="p-4 border-b border-gray-100 dark:border-gray-700">
        <input v-model="search" @input="debouncedSearch" type="text" placeholder="Buscar productos..."
          class="w-full max-w-md px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <table class="w-full">
        <thead class="bg-gray-50 dark:bg-gray-800">
          <tr>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Nombre</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">SKU</th>
            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Categoría</th>
            <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Precio</th>
            <th class="text-right px-4 py-3 text-xs font-medium text-gray-500 uppercase">Creado por</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <tr v-for="p in products" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-white">{{ p.name }}</td>
            <td class="px-4 py-3 text-sm text-gray-500">{{ p.sku || '-' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ p.category || '-' }}</td>
            <td class="px-4 py-3 text-sm text-right font-medium text-gray-800 dark:text-white">${{ formatNumber(p.price) }}</td>
            <td class="px-4 py-3 text-sm text-right text-gray-500">{{ p.created_by?.name || '-' }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="editProduct(p)" class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800">Editar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="!products.length" class="text-center py-12 text-gray-400">Sin productos</div>
    </div>

    <Teleport to="body">
      <div v-if="showForm" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="showForm = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 w-full max-w-lg shadow-xl">
          <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">{{ editing ? 'Editar' : 'Nuevo' }} Producto</h3>
          <form @submit.prevent="saveProduct" class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre</label>
              <input v-model="form.name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Precio</label>
                <input v-model.number="form.price" type="number" step="0.01" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SKU</label>
                <input v-model="form.sku" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
              <input v-model="form.category" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
              <textarea v-model="form.description" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="showForm = false" class="px-4 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300">Cancelar</button>
              <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">{{ editing ? 'Guardar' : 'Crear' }}</button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api/axios'
import { useToastStore } from '../stores/toast'
import Loader from '../components/Loader.vue'

const toast = useToastStore()
const products = ref([])
const search = ref('')
const showForm = ref(false)
const editing = ref(null)
const form = ref({ name: '', price: 0, sku: '', category: '', description: '' })
let searchTimeout = null
const loading = ref(true)

onMounted(() => loadProducts())

async function loadProducts() {
  loading.value = true
  try {
    const params = { per_page: 100 }
    if (search.value) params.search = search.value
    const { data } = await api.get('/products', { params })
    products.value = data.data || []
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(loadProducts, 300)
}

function editProduct(p) {
  editing.value = p
  form.value = { name: p.name, price: p.price, sku: p.sku || '', category: p.category || '', description: p.description || '' }
  showForm.value = true
}

async function saveProduct() {
  try {
    if (editing.value) {
      await api.put(`/products/${editing.value.id}`, form.value)
      toast.add('Producto actualizado', 'success')
    } else {
      await api.post('/products', form.value)
      toast.add('Producto creado', 'success')
    }
    showForm.value = false
    editing.value = null
    form.value = { name: '', price: 0, sku: '', category: '', description: '' }
    loadProducts()
  } catch (e) {
    toast.add('Error al guardar', 'error')
    console.error(e)
  }
}

function formatNumber(n) {
  return new Intl.NumberFormat('es-AR').format(Number(n || 0).toFixed(2))
}
</script>
