<script setup lang="ts">
const shortcuts = [
  {
    icon: 'tabler-smart-home',
    title: 'shortcuts.dashboard',
    subtitle: 'shortcuts.dashboardSub',
    to: { name: 'dashboard' },
  },
  {
    icon: 'tabler-map-pin',
    title: 'shortcuts.map',
    subtitle: 'shortcuts.mapSub',
    to: { name: 'map' },
  },
  {
    icon: 'tabler-plant',
    title: 'shortcuts.plants',
    subtitle: 'shortcuts.plantsSub',
    to: { name: 'plants' },
  },
  {
    icon: 'tabler-flag',
    title: 'shortcuts.reports',
    subtitle: 'shortcuts.reportsSub',
    to: { name: 'plant-reports' },
  },
  {
    icon: 'tabler-trees',
    title: 'shortcuts.campaigns',
    subtitle: 'shortcuts.campaignsSub',
    to: { name: 'campaigns' },
  },
  {
    icon: 'tabler-users',
    title: 'shortcuts.citizens',
    subtitle: 'shortcuts.citizensSub',
    to: { name: 'citizens' },
  },
  {
    icon: 'tabler-shield-check',
    title: 'shortcuts.staff',
    subtitle: 'shortcuts.staffSub',
    to: { name: 'staff' },
  },
  {
    icon: 'tabler-settings',
    title: 'shortcuts.settings',
    subtitle: 'shortcuts.settingsSub',
    to: { name: 'settings' },
  },
]
</script>

<template>
  <Shortcuts :shortcuts="shortcuts" />
</template>
