<template>
  <div :class="{ dark: isDark }">
    <router-view />
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, onMounted, provide } from 'vue'
import ToastContainer from './components/ToastContainer.vue'

const isDark = ref(false)

onMounted(() => {
  isDark.value = localStorage.getItem('darkMode') === 'true'
})

function toggleDark() {
  isDark.value = !isDark.value
  localStorage.setItem('darkMode', isDark.value)
}

provide('isDark', isDark)
provide('toggleDark', toggleDark)
</script>
