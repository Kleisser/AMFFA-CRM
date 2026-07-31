<template>
  <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between mb-2">
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ title }}</span>
      <span v-if="change !== undefined" class="text-xs font-semibold text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/50 px-2 py-0.5 rounded-full">+{{ change }}</span>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-2xl font-bold text-gray-800 dark:text-white">{{ value }}</span>
    </div>
    <p v-if="subtitle" class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ subtitle }}</p>
  </div>
</template>

<script setup>
defineProps({ title: String, value: [String, Number], subtitle: String, change: [String, Number], color: String, icon: String })
</script>