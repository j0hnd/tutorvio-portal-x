<script setup lang="ts">
// Base Layout component
</script>

<template>
  <div class="layout">
    <header>
      <!-- Navigation goes here -->
      <h1>Tutorvio Portal</h1>
    </header>
    
    <main>
      <slot></slot>
    </main>

    <footer>
      <!-- Footer content goes here -->
    </footer>
  </div>
</template>

<style scoped>
.layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
main {
  flex: 1;
}
</style>
