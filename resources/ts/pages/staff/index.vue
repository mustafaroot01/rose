<script setup lang="ts">
import UsersManager from '@/views/users/UsersManager.vue'
</script>

<template>
  <UsersManager
    title="المشرفون والإدارة"
    export-name="المشرفون"
    roles="admin,supervisor"
    show-role
    can-create
  />
</template>
