<script setup lang="ts">
import UsersManager from '@/views/users/UsersManager.vue'
</script>

<template>
  <UsersManager
    title="المواطنون"
    export-name="المواطنون"
    endpoint="/admin/citizens"
    profile-route="citizens-id"
    :can-edit="false"
  />
</template>
