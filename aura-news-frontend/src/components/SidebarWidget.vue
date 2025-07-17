<script setup>
defineProps({ title: String });
</script>
<template>
  <section class="mb-8">
    <h3 class="text-xl font-bold text-gray-900 pb-2 mb-4 border-b-2 border-brand-DEFAULT">
      {{ title }}
    </h3>
    <div>
      <slot></slot>
    </div>
  </section>
</template>