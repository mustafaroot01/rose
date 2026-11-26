<script setup lang="ts">
import { type Plant, activityMeta, plantStatusMeta, reasonLabels, reportStatusMeta } from './plantTypes'

interface Props {
  modelValue: boolean
  plantId: number | null
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'changed', message: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const plant = ref<Plant | null>(null)
const loading = ref(false)
const acting = ref(false)

const fetchPlant = async () => {
  if (!props.plantId)
    return
  loading.value = true
  try {
    const res = await $api<{ data: Plant }>(`/admin/plants/${props.plantId}`)
    plant.value = res.data
  }
  finally {
    loading.value = false
  }
}

watch(() => props.modelValue, val => {
  if (val) {
    plant.value = null
    fetchPlant()
  }
})

const close = () => emit('update:modelValue', false)

const setStatus = async (status: 'approved' | 'rejected') => {
  if (!plant.value)
    return
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plant.value.id}/status`, {
      method: 'PATCH',
      body: { status },
    })
    emit('changed', res.message)
    await fetchPlant()
  }
  finally {
    acting.value = false
  }
}

const water = async () => {
  if (!plant.value)
    return
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plant.value.id}/water`, { method: 'POST' })
    emit('changed', res.message)
    await fetchPlant()
  }
  finally {
    acting.value = false
  }
}

const mapUrl = computed(() =>
  plant.value ? `https://www.openstreetmap.org/?mlat=${plant.value.latitude}&mlon=${plant.value.longitude}#map=16/${plant.value.latitude}/${plant.value.longitude}` : '#')

const formatDateTime = (iso: string) =>
  iso ? new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="760"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>تفاصيل النبتة</VCardTitle>
        <template #append>
          <IconBtn @click="close">
            <VIcon icon="tabler-x" />
          </IconBtn>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText style="max-block-size: 72vh;">
        <div
          v-if="loading"
          class="d-flex justify-center py-10"
        >
          <VProgressCircular indeterminate color="primary" />
        </div>

        <template v-else-if="plant">
          <VRow>
            <!-- الصورة + الموقع -->
            <VCol
              cols="12"
              md="5"
            >
              <VImg
                :src="plant.image || '/images/misc/plant-placeholder.png'"
                :aspect-ratio="4 / 3"
                cover
                rounded
                class="bg-surface-variant"
              >
                <template #error>
                  <div class="d-flex align-center justify-center fill-height bg-primary" style="opacity:.08;" />
                </template>
              </VImg>

              <VBtn
                block
                variant="tonal"
                color="primary"
                prepend-icon="tabler-map-pin"
                class="mt-3"
                :href="mapUrl"
                target="_blank"
              >
                فتح الموقع على الخريطة
              </VBtn>
              <div class="text-caption text-disabled text-center mt-1">
                {{ plant.latitude }}, {{ plant.longitude }}
              </div>
            </VCol>

            <!-- البيانات -->
            <VCol
              cols="12"
              md="7"
            >
              <div class="d-flex align-center justify-space-between mb-2">
                <h5 class="text-h5">
                  {{ plant.name }}
                </h5>
                <VChip
                  :color="plantStatusMeta[plant.status].color"
                  size="small"
                >
                  {{ plantStatusMeta[plant.status].label }}
                </VChip>
              </div>

              <VList
                density="compact"
                class="card-list"
              >
                <VListItem v-if="plant.category">
                  <template #prepend>
                    <VIcon
                      :icon="plant.category.icon || 'tabler-leaf'"
                      :color="plant.category.color || 'primary'"
                      class="me-2"
                    />
                  </template>
                  <VListItemTitle>الصنف: <strong>{{ plant.category.name }}</strong></VListItemTitle>
                </VListItem>
                <VListItem v-if="plant.type">
                  <VListItemTitle>النوع: <strong>{{ plant.type }}</strong></VListItemTitle>
                </VListItem>
                <VListItem v-if="plant.age_label">
                  <VListItemTitle>العمر: <strong>{{ plant.age_label }}</strong> <span class="text-disabled">({{ plant.planting_date }})</span></VListItemTitle>
                </VListItem>
                <VListItem v-if="plant.owner">
                  <VListItemTitle>صاحبها: <strong>{{ plant.owner.name }}</strong> <span class="text-disabled">{{ plant.owner.phone }}</span></VListItemTitle>
                </VListItem>
                <VListItem v-if="plant.governorate">
                  <VListItemTitle>الموقع: {{ plant.governorate.name }}<span v-if="plant.district"> — {{ plant.district.name }}</span></VListItemTitle>
                </VListItem>
                <VListItem v-if="plant.description">
                  <VListItemTitle class="text-wrap">
                    {{ plant.description }}
                  </VListItemTitle>
                </VListItem>
              </VList>

              <!-- إجراءات -->
              <div class="d-flex flex-wrap gap-2 mt-4">
                <VBtn
                  v-if="plant.status !== 'approved'"
                  size="small"
                  color="success"
                  prepend-icon="tabler-check"
                  :loading="acting"
                  @click="setStatus('approved')"
                >
                  اعتماد
                </VBtn>
                <VBtn
                  v-if="plant.status !== 'rejected'"
                  size="small"
                  color="error"
                  variant="tonal"
                  prepend-icon="tabler-x"
                  :loading="acting"
                  @click="setStatus('rejected')"
                >
                  رفض
                </VBtn>
                <VBtn
                  size="small"
                  color="info"
                  variant="tonal"
                  prepend-icon="tabler-droplet"
                  :loading="acting"
                  @click="water"
                >
                  تسجيل سقي
                </VBtn>
              </div>
            </VCol>
          </VRow>

          <!-- البلاغات -->
          <template v-if="plant.reports?.length">
            <VDivider class="my-4" />
            <h6 class="text-h6 mb-2">
              البلاغات ({{ plant.reports.length }})
            </h6>
            <VAlert
              v-for="r in plant.reports"
              :key="r.id"
              :color="reportStatusMeta[r.status].color"
              variant="tonal"
              density="compact"
              class="mb-2"
            >
              <div class="d-flex flex-wrap align-center gap-2">
                <strong>{{ reasonLabels[r.reason] }}</strong>
                <VChip
                  size="x-small"
                  :color="reportStatusMeta[r.status].color"
                >
                  {{ reportStatusMeta[r.status].label }}
                </VChip>
                <span class="text-disabled text-sm">— {{ r.reporter?.name }}</span>
              </div>
              <div
                v-if="r.reason_note"
                class="text-sm mt-1"
              >
                {{ r.reason_note }}
              </div>
            </VAlert>
          </template>

          <!-- الهستوري -->
          <VDivider class="my-4" />
          <h6 class="text-h6 mb-3">
            سجلّ النبتة (الهستوري)
          </h6>
          <VTimeline
            v-if="plant.activities?.length"
            side="end"
            align="start"
            truncate-line="both"
            density="compact"
            class="v-timeline--variant-outlined"
          >
            <VTimelineItem
              v-for="a in plant.activities"
              :key="a.id"
              :dot-color="activityMeta[a.type]?.color || 'secondary'"
              :icon="activityMeta[a.type]?.icon || 'tabler-point'"
              size="x-small"
            >
              <div class="d-flex justify-space-between flex-wrap gap-2">
                <span class="font-weight-medium">{{ a.description || activityMeta[a.type]?.label || a.type }}</span>
                <span class="text-sm text-disabled">{{ formatDateTime(a.created_at) }}</span>
              </div>
              <div
                v-if="a.user"
                class="text-sm text-disabled"
              >
                بواسطة {{ a.user.name }}
              </div>
            </VTimelineItem>
          </VTimeline>
          <span
            v-else
            class="text-disabled"
          >لا يوجد سجل.</span>
        </template>
      </VCardText>
    </VCard>
  </VDialog>
</template>
