<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
    <h2 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">{{ title }}</h2>
    <div class="h-64">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({ title: String })
</script>