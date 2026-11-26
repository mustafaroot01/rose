<script setup lang="ts">
import { type Plant, type PlantActivity, activityMeta, plantStatusMeta } from '@/views/plants/plantTypes'

const route = useRoute('plants-id')
const router = useRouter()
const plantId = route.params.id

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

// 👉 تفاصيل النبتة
const { data: plantData, execute: fetchPlant } = await useApi<{ data: Plant }>(`/admin/plants/${plantId}`)
const plant = computed<Plant | null>(() => plantData.value?.data ?? null)

// 👉 داتا تيبل العمليات
const typeFilter = ref<string>()
const itemsPerPage = ref(10)
const page = ref(1)

const { data: actData, execute: fetchActivities } = await useApi<any>(createUrl(`/admin/plants/${plantId}/activities`, {
  query: { type: typeFilter, itemsPerPage, page },
}))

const activities = computed<PlantActivity[]>(() => actData.value?.data ?? [])
const activitiesTotal = computed<number>(() => actData.value?.meta?.total ?? 0)

const typeOptions = Object.entries(activityMeta).map(([value, m]) => ({ title: m.label, value }))

const headers = [
  { title: 'العملية', key: 'type' },
  { title: 'التفاصيل', key: 'description', sortable: false },
  { title: 'المنفّذ', key: 'user', sortable: false },
  { title: 'التاريخ والوقت', key: 'created_at' },
]

const acting = ref(false)

const refresh = () => { fetchPlant(); fetchActivities() }

const setStatus = async (status: 'approved' | 'rejected') => {
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plantId}/status`, { method: 'PATCH', body: { status } })
    notify(res.message)
    refresh()
  }
  finally {
    acting.value = false
  }
}

const water = async () => {
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plantId}/water`, { method: 'POST' })
    notify(res.message)
    refresh()
  }
  finally {
    acting.value = false
  }
}

const mapUrl = computed(() =>
  plant.value ? `https://www.openstreetmap.org/?mlat=${plant.value.latitude}&mlon=${plant.value.longitude}#map=16/${plant.value.latitude}/${plant.value.longitude}` : '#')

// ───────────────── معرض الصور (يُعرض كشرائح في التطبيق) ─────────────────
const galleryInput = ref<HTMLInputElement>()
const uploading = ref(false)

const pickImages = () => galleryInput.value?.click()

const onImagesPicked = async (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files?.length)
    return

  const form = new FormData()
  for (const f of Array.from(files).slice(0, 10))
    form.append('images[]', f)

  uploading.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plantId}/images`, { method: 'POST', body: form })
    notify(res.message)
    refresh()
  }
  catch (err: any) {
    notify(err?.data?.message ?? 'تعذّر رفع الصور.', 'error')
  }
  finally {
    uploading.value = false
    if (galleryInput.value)
      galleryInput.value.value = ''
  }
}

const deleteImage = async (imageId: number) => {
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${plantId}/images/${imageId}`, { method: 'DELETE' })
    notify(res.message)
    refresh()
  }
  catch (err: any) {
    notify(err?.data?.message ?? 'تعذّر حذف الصورة.', 'error')
  }
}

const formatDateTime = (iso: string) =>
  iso ? new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

const details = computed(() => {
  if (!plant.value)
    return []

  return [
    { icon: 'tabler-leaf', label: 'الصنف', value: plant.value.category?.name || '—' },
    { icon: 'tabler-plant', label: 'النوع', value: plant.value.type || '—' },
    { icon: 'tabler-hourglass', label: 'العمر', value: plant.value.age_label || '—' },
    { icon: 'tabler-calendar', label: 'تاريخ الزرع', value: plant.value.planting_date || '—' },
    { icon: 'tabler-user', label: 'صاحبها', value: plant.value.owner?.name || '—' },
    { icon: 'tabler-map-pin', label: 'المحافظة', value: plant.value.governorate?.name || '—' },
    { icon: 'tabler-map-2', label: 'القضاء', value: plant.value.district?.name || '—' },
    { icon: 'tabler-heart', label: 'الإعجابات', value: String(plant.value.likes_count ?? 0) },
    { icon: 'tabler-droplet', label: 'مرات الري', value: String(plant.value.waterings_count ?? 0) },
    // يظهر فقط عندما تكون النبتة ضمن حملة
    ...(plant.value.campaign
      ? [{ icon: 'tabler-trees', label: 'الحملة', value: plant.value.campaign.title }]
      : []),
  ]
})
</script>

<template>
  <section v-if="plant">
    <div class="d-flex align-center gap-2 mb-6">
      <IconBtn @click="router.back()">
        <VIcon icon="tabler-arrow-right" />
      </IconBtn>
      <h4 class="text-h4">
        ملف النبتة
      </h4>
    </div>

    <VRow>
      <!-- 👉 بطاقة التفاصيل -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VImg
            :src="plant.image || undefined"
            :aspect-ratio="16 / 10"
            :cover="!plant.image"
            class="bg-grey-lighten-3"
          >
            <template #placeholder>
              <div class="d-flex align-center justify-center fill-height bg-primary">
                <VIcon
                  icon="tabler-plant-2"
                  size="48"
                  color="white"
                  style="opacity:.5;"
                />
              </div>
            </template>
          </VImg>

          <VCardText>
            <div class="d-flex align-center justify-space-between mb-3">
              <h5 class="text-h5">
                {{ plant.name }}
              </h5>
              <VChip
                size="small"
                :color="plantStatusMeta[plant.status].color"
                variant="tonal"
              >
                {{ plantStatusMeta[plant.status].label }}
              </VChip>
            </div>

            <VList
              density="compact"
              class="card-list"
            >
              <VListItem
                v-for="(d, i) in details"
                :key="i"
              >
                <template #prepend>
                  <VIcon
                    :icon="d.icon"
                    size="20"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-sm text-disabled">
                  {{ d.label }}
                </VListItemTitle>
                <VListItemSubtitle class="text-high-emphasis">
                  {{ d.value }}
                </VListItemSubtitle>
              </VListItem>
            </VList>

            <!-- 👉 معرض صور النبتة (يظهر كعرض شرائح في التطبيق) -->
            <VDivider class="my-4" />
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-sm font-weight-medium">
                معرض الصور
                <span class="text-disabled">({{ plant.gallery?.length ?? 0 }})</span>
              </span>
              <VBtn
                size="small"
                variant="tonal"
                color="primary"
                prepend-icon="tabler-photo-plus"
                :loading="uploading"
                @click="pickImages"
              >
                إضافة صور
              </VBtn>
            </div>
            <input
              ref="galleryInput"
              type="file"
              accept="image/*"
              multiple
              hidden
              @change="onImagesPicked"
            >
            <div
              v-if="plant.gallery?.length"
              class="d-flex flex-wrap gap-2"
            >
              <div
                v-for="img in plant.gallery"
                :key="img.id"
                class="plant-thumb"
              >
                <VImg
                  :src="img.url"
                  width="72"
                  height="72"
                  cover
                  rounded="lg"
                />
                <VBtn
                  icon
                  size="x-small"
                  color="error"
                  variant="flat"
                  class="plant-thumb__del"
                  @click="deleteImage(img.id)"
                >
                  <VIcon
                    icon="tabler-x"
                    size="14"
                  />
                </VBtn>
              </div>
            </div>
            <div
              v-else
              class="text-caption text-disabled"
            >
              لا توجد صور إضافية — أضف صورًا لتظهر كشرائح في التطبيق.
            </div>

            <VBtn
              block
              variant="tonal"
              color="primary"
              prepend-icon="tabler-map-pin"
              class="mt-4"
              :href="mapUrl"
              target="_blank"
            >
              فتح الموقع على الخريطة
            </VBtn>

            <div class="d-flex flex-wrap gap-2 mt-3">
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
                variant="tonal"
                color="error"
                prepend-icon="tabler-x"
                :loading="acting"
                @click="setStatus('rejected')"
              >
                رفض
              </VBtn>
              <VBtn
                size="small"
                variant="tonal"
                color="info"
                prepend-icon="tabler-droplet"
                :loading="acting"
                @click="water"
              >
                سقي
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- 👉 داتا تيبل العمليات -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VCardItem>
            <VCardTitle>سجلّ العمليات ({{ activitiesTotal }})</VCardTitle>
            <template #append>
              <div style="inline-size: 200px;">
                <AppSelect
                  v-model="typeFilter"
                  placeholder="كل العمليات"
                  :items="typeOptions"
                  clearable
                  clear-icon="tabler-x"
                  density="compact"
                />
              </div>
            </template>
          </VCardItem>
          <VDivider />
          <VDataTableServer
            v-model:items-per-page="itemsPerPage"
            v-model:page="page"
            :items="activities"
            item-value="id"
            :items-length="activitiesTotal"
            :headers="headers"
            class="text-no-wrap"
          >
            <template #item.type="{ item }">
              <VChip
                size="small"
                :color="activityMeta[item.type]?.color || 'secondary'"
                variant="tonal"
              >
                <VIcon
                  start
                  :icon="activityMeta[item.type]?.icon || 'tabler-point'"
                  size="14"
                />
                {{ activityMeta[item.type]?.label || item.type }}
              </VChip>
            </template>

            <template #item.description="{ item }">
              <span class="text-wrap">{{ item.description || '—' }}</span>
            </template>

            <template #item.user="{ item }">
              {{ item.user?.name ?? 'النظام' }}
            </template>

            <template #item.created_at="{ item }">
              {{ formatDateTime(item.created_at) }}
            </template>

            <template #bottom>
              <TablePagination
                v-model:page="page"
                :items-per-page="itemsPerPage"
                :total-items="activitiesTotal"
              />
            </template>
          </VDataTableServer>
        </VCard>
      </VCol>
    </VRow>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
      :timeout="3000"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>

<style lang="scss" scoped>
.plant-thumb {
  position: relative;

  &__del {
    position: absolute;
    inset-block-start: -6px;
    inset-inline-end: -6px;
  }
}
</style>
