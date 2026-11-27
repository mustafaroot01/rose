<script setup lang="ts">
import { type Campaign, campaignStatusMeta } from '@/views/campaigns/types'
import { type Plant, plantStatusMeta } from '@/views/plants/plantTypes'

interface Paginated<T> { data: T[]; meta?: { total?: number } }
interface Participant { id: number, name: string, phone: string, plants_count?: number, created_at?: string }

const route = useRoute('campaigns-id')
const router = useRouter()
const campaignId = route.params.id

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const { data: cData, execute: refreshCampaign } = await useApi<{ data: Campaign }>(`/admin/campaigns/${campaignId}`)
const campaign = computed<Campaign | null>(() => cData.value?.data ?? null)

const tab = ref('participants')

// المشاركون
const pPage = ref(1)
const pPer = ref(10)
const { data: pData } = await useApi<Paginated<Participant>>(createUrl(`/admin/campaigns/${campaignId}/participants`, {
  query: { page: pPage, itemsPerPage: pPer },
}))
const participants = computed<Participant[]>(() => pData.value?.data ?? [])
const participantsTotal = computed<number>(() => pData.value?.meta?.total ?? 0)

// النباتات
const plPage = ref(1)
const plPer = ref(10)
const { data: plData } = await useApi<Paginated<Plant>>(createUrl(`/admin/campaigns/${campaignId}/plants`, {
  query: { page: plPage, itemsPerPage: plPer },
}))
const plants = computed<Plant[]>(() => plData.value?.data ?? [])
const plantsTotal = computed<number>(() => plData.value?.meta?.total ?? 0)

const pHeaders = [
  { title: 'المشارك', key: 'name' },
  { title: 'الجوال', key: 'phone', sortable: false },
  { title: 'مساهماته', key: 'plants_count' },
]
const plHeaders = [
  { title: 'النبتة', key: 'name' },
  { title: 'الصنف', key: 'category', sortable: false },
  { title: 'صاحبها', key: 'owner', sortable: false },
  { title: 'الحالة', key: 'status' },
]

const acting = ref(false)
const setStatus = async (status: 'active' | 'ended' | 'draft') => {
  acting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/campaigns/${campaignId}/status`, { method: 'PATCH', body: { status } })
    notify(res.message)
    refreshCampaign()
  }
  finally {
    acting.value = false
  }
}

const formatDate = (iso?: string | null) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'
</script>

<template>
  <section v-if="campaign">
    <div class="d-flex align-center gap-2 mb-6">
      <IconBtn @click="router.back()">
        <VIcon icon="tabler-arrow-right" />
      </IconBtn>
      <h4 class="text-h4">
        ملف الحملة
      </h4>
    </div>

    <VRow>
      <!-- بطاقة الحملة -->
      <VCol
        cols="12"
        md="4"
      >
        <VCard>
          <VImg
            :src="campaign.cover || undefined"
            :aspect-ratio="16 / 9"
            cover
            class="bg-primary"
          >
            <template #placeholder>
              <div class="d-flex align-center justify-center fill-height">
                <VIcon
                  icon="tabler-trees"
                  size="48"
                  color="white"
                  style="opacity:.5;"
                />
              </div>
            </template>
          </VImg>
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-2">
              <h5 class="text-h5">
                {{ campaign.title }}
              </h5>
              <VChip
                size="small"
                variant="tonal"
                :color="campaignStatusMeta[campaign.status].color"
              >
                {{ campaignStatusMeta[campaign.status].label }}
              </VChip>
            </div>
            <p
              v-if="campaign.description"
              class="text-body-2 text-medium-emphasis"
            >
              {{ campaign.description }}
            </p>

            <!-- الجهة الراعية -->
            <div
              v-if="campaign.sponsor"
              class="d-flex align-center gap-3 mt-3 pa-3 rounded border"
            >
              <VAvatar
                v-if="campaign.sponsor.logo"
                :image="campaign.sponsor.logo"
                rounded
                size="44"
              />
              <div>
                <div class="text-caption text-disabled">
                  برعاية
                </div>
                <a
                  v-if="campaign.sponsor.url"
                  :href="campaign.sponsor.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-weight-medium"
                >{{ campaign.sponsor.name }}</a>
                <span
                  v-else
                  class="font-weight-medium"
                >{{ campaign.sponsor.name }}</span>
              </div>
            </div>

            <!-- شريط الإنجاز -->
            <div class="mt-4">
              <div class="d-flex justify-space-between mb-1">
                <span class="text-sm">الإنجاز</span>
                <span class="text-sm font-weight-medium">{{ campaign.trees_count }} / {{ campaign.goal_trees }} ({{ campaign.progress }}%)</span>
              </div>
              <VProgressLinear
                :model-value="campaign.progress"
                color="success"
                height="10"
                rounded
              />
            </div>

            <VList
              density="compact"
              class="mt-3"
            >
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-users"
                    size="20"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-sm text-disabled">
                  المشاركون
                </VListItemTitle>
                <VListItemSubtitle class="text-high-emphasis">
                  {{ participantsTotal }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-map-pin"
                    size="20"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-sm text-disabled">
                  المنطقة
                </VListItemTitle>
                <VListItemSubtitle class="text-high-emphasis">
                  {{ campaign.governorate?.name || 'كل المناطق' }}
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <template #prepend>
                  <VIcon
                    icon="tabler-calendar"
                    size="20"
                    class="me-2"
                  />
                </template>
                <VListItemTitle class="text-sm text-disabled">
                  الفترة
                </VListItemTitle>
                <VListItemSubtitle class="text-high-emphasis">
                  {{ formatDate(campaign.starts_at) }} — {{ formatDate(campaign.ends_at) }}
                </VListItemSubtitle>
              </VListItem>
            </VList>

            <div class="d-flex flex-wrap gap-2 mt-3">
              <VBtn
                v-if="campaign.status !== 'active'"
                size="small"
                color="success"
                prepend-icon="tabler-flame"
                :loading="acting"
                @click="setStatus('active')"
              >
                تفعيل
              </VBtn>
              <VBtn
                v-if="campaign.status === 'active'"
                size="small"
                variant="tonal"
                color="error"
                prepend-icon="tabler-flag-check"
                :loading="acting"
                @click="setStatus('ended')"
              >
                إنهاء
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- التبويبات -->
      <VCol
        cols="12"
        md="8"
      >
        <VCard>
          <VTabs v-model="tab">
            <VTab value="participants">
              <VIcon
                start
                icon="tabler-users"
              />
              المشاركون ({{ participantsTotal }})
            </VTab>
            <VTab value="plants">
              <VIcon
                start
                icon="tabler-plant"
              />
              النباتات ({{ plantsTotal }})
            </VTab>
          </VTabs>
          <VDivider />

          <VWindow v-model="tab">
            <!-- المشاركون -->
            <VWindowItem value="participants">
              <VDataTableServer
                v-model:items-per-page="pPer"
                v-model:page="pPage"
                :items="participants"
                item-value="id"
                :items-length="participantsTotal"
                :headers="pHeaders"
                class="text-no-wrap"
              >
                <template #item.name="{ item }">
                  <RouterLink
                    :to="{ name: 'citizens-id', params: { id: item.id } }"
                    class="text-link font-weight-medium"
                  >
                    {{ item.name }}
                  </RouterLink>
                </template>
                <template #item.plants_count="{ item }">
                  <VChip
                    size="small"
                    variant="tonal"
                    color="success"
                  >
                    {{ item.plants_count ?? 0 }}
                  </VChip>
                </template>
                <template #no-data>
                  <div class="text-center py-8 text-disabled">
                    لا يوجد مشاركون بعد.
                  </div>
                </template>
                <template #bottom>
                  <TablePagination
                    v-model:page="pPage"
                    :items-per-page="pPer"
                    :total-items="participantsTotal"
                  />
                </template>
              </VDataTableServer>
            </VWindowItem>

            <!-- النباتات -->
            <VWindowItem value="plants">
              <VDataTableServer
                v-model:items-per-page="plPer"
                v-model:page="plPage"
                :items="plants"
                item-value="id"
                :items-length="plantsTotal"
                :headers="plHeaders"
                class="text-no-wrap"
              >
                <template #item.name="{ item }">
                  <RouterLink
                    :to="{ name: 'plants-id', params: { id: item.id } }"
                    class="text-link font-weight-medium"
                  >
                    {{ item.name }}
                  </RouterLink>
                </template>
                <template #item.category="{ item }">
                  {{ item.category?.name ?? '—' }}
                </template>
                <template #item.owner="{ item }">
                  {{ item.owner?.name ?? '—' }}
                </template>
                <template #item.status="{ item }">
                  <VChip
                    size="small"
                    variant="tonal"
                    :color="plantStatusMeta[item.status].color"
                  >
                    {{ plantStatusMeta[item.status].label }}
                  </VChip>
                </template>
                <template #no-data>
                  <div class="text-center py-8 text-disabled">
                    لا توجد نباتات ضمن الحملة بعد.
                  </div>
                </template>
                <template #bottom>
                  <TablePagination
                    v-model:page="plPage"
                    :items-per-page="plPer"
                    :total-items="plantsTotal"
                  />
                </template>
              </VDataTableServer>
            </VWindowItem>
          </VWindow>
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
