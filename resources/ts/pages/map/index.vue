<script setup lang="ts">
import PlantsMap from '@/views/maps/PlantsMap.vue'
import type { PlantFeatureCollection } from '@/views/maps/types'
import { plantStatusMeta } from '@/views/plants/plantTypes'

const statusFilter = ref<string>()
const categoryFilter = ref<number>()
const governorateFilter = ref<number>()

// 👉 نقاط النباتات (GeoJSON خفيف) — تُعاد تلقائيًا عند تغيّر الفلاتر
const { data } = await useApi<PlantFeatureCollection>(createUrl('/admin/plants/map', {
  query: {
    status: statusFilter,
    category_id: categoryFilter,
    governorate_id: governorateFilter,
  },
}))

const geojson = computed<PlantFeatureCollection>(() => data.value ?? { type: 'FeatureCollection', features: [] })
const total = computed(() => geojson.value.features.length)

// 👉 خيارات الفلاتر
const { data: catData } = await useApi<{ id: number; name: string }[]>('/admin/plant-categories/options')
const { data: govData } = await useApi<{ id: number; name: string }[]>('/admin/governorates/options')

const categoryItems = computed(() => (catData.value ?? []).map(c => ({ title: c.name, value: c.id })))
const governorateItems = computed(() => (govData.value ?? []).map(g => ({ title: g.name, value: g.id })))
const statusItems = Object.entries(plantStatusMeta).map(([value, m]) => ({ title: m.label, value }))
</script>

<template>
  <section>
    <div class="d-flex align-center gap-2 mb-6">
      <h4 class="text-h4">
        خريطة النباتات
      </h4>
      <VChip
        color="primary"
        variant="tonal"
        size="small"
      >
        {{ total }} نبتة
      </VChip>
    </div>

    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="statusFilter"
              placeholder="كل الحالات"
              :items="statusItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="categoryFilter"
              placeholder="كل الأصناف"
              :items="categoryItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="governorateFilter"
              placeholder="كل المحافظات"
              :items="governorateItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardText>
        <PlantsMap
          :data="geojson"
          auto-locate
        />
      </VCardText>
    </VCard>
  </section>
</template>
