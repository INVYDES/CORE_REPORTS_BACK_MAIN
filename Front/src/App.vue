<script setup lang="ts">

</script>

<template>
  <router-view />
</template>

<style>
body {
  margin: 0;
  font-family: sans-serif;
  background-color: #f4f4f9;
}

@media print {
  body {
    background-color: white !important;
    margin: 0;
    padding: 0;
  }
  .page-container-app-sidebar,
  .page-container-app-header,
  .sidebar,
  .app-header,
  .Vue-Toastification__container {
    display: none !important;
  }
}
</style>