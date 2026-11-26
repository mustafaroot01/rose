<script setup lang="ts">
import CampaignDialog from '@/views/campaigns/CampaignDialog.vue'
import { type Campaign, campaignStatusMeta } from '@/views/campaigns/types'

const router = useRouter()
const openProfile = (id: number) => router.push({ name: 'campaigns-id', params: { id } })

const searchQuery = ref('')
const selectedStatus = ref<string>()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()

const statusOptions = [
  { title: 'مسودّة', value: 'draft' },
  { title: 'نشطة', value: 'active' },
  { title: 'منتهية', value: 'ended' },
]

const headers = [
  { title: 'الحملة', key: 'title' },
  { title: 'المنطقة', key: 'governorate', sortable: false },
  { title: 'الإنجاز', key: 'progress', sortable: false },
  { title: 'المشاركون', key: 'participants_count', sortable: false },
  { title: 'الحالة', key: 'status' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const { data, execute: fetchData } = await useApi<any>(createUrl('/admin/campaigns', {
  query: { q: searchQuery, status: selectedStatus, itemsPerPage, page, sortBy, orderBy },
}))

const campaigns = computed<Campaign[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)
const stats = computed(() => data.value?.stats ?? { total: 0, active: 0, draft: 0, ended: 0 })

const widgets = computed(() => [
  { title: 'إجمالي الحملات', value: stats.value.total, icon: 'tabler-flag', color: 'primary' },
  { title: 'النشطة', value: stats.value.active, icon: 'tabler-flame', color: 'success' },
  { title: 'المسودّات', value: stats.value.draft, icon: 'tabler-pencil', color: 'warning' },
  { title: 'المنتهية', value: stats.value.ended, icon: 'tabler-flag-check', color: 'secondary' },
])

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const isDialogVisible = ref(false)
const editingItem = ref<Campaign | null>(null)
const openAdd = () => { editingItem.value = null; isDialogVisible.value = true }
const openEdit = (item: Campaign) => { editingItem.value = item; isDialogVisible.value = true }
const onSaved = (message: string) => { notify(message); fetchData() }

const deleteTarget = ref<Campaign | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/campaigns/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchData()
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <section>
    <VRow class="mb-6">
      <VCol
        v-for="(w, i) in widgets"
        :key="i"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1 text-high-emphasis mb-1">
                {{ w.title }}
              </div>
              <h4 class="text-h4">
                {{ w.value }}
              </h4>
            </div>
            <VAvatar
              :color="w.color"
              variant="tonal"
              rounded
              size="44"
            >
              <VIcon
                :icon="w.icon"
                size="26"
              />
            </VAvatar>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardItem class="pb-4">
        <VCardTitle>حملات التشجير</VCardTitle>
        <template #append>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openAdd"
          >
            حملة جديدة
          </VBtn>
        </template>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="ابحث باسم الحملة"
              prepend-inner-icon="tabler-search"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="الحالة"
              :items="statusOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="campaigns"
        item-value="id"
        :items-length="total"
        :headers="headers"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.title="{ item }">
          <div class="d-flex align-center gap-x-3 py-2">
            <VAvatar
              size="42"
              rounded="lg"
              variant="tonal"
              color="primary"
            >
              <VImg
                v-if="item.cover"
                :src="item.cover"
              />
              <VIcon
                v-else
                icon="tabler-trees"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-high-emphasis cursor-pointer text-primary" @click="openProfile(item.id)">{{ item.title }}</span>
              <span class="text-sm text-disabled">الهدف: {{ item.goal_trees }} شجرة</span>
            </div>
          </div>
        </template>

        <template #item.governorate="{ item }">
          <span v-if="item.governorate">{{ item.governorate.name }}</span>
          <span
            v-else
            class="text-disabled"
          >كل المناطق</span>
        </template>

        <template #item.progress="{ item }">
          <div style="min-inline-size: 120px;">
            <div class="d-flex justify-space-between text-sm mb-1">
              <span>{{ item.trees_count }}/{{ item.goal_trees }}</span>
              <span class="font-weight-medium">{{ item.progress }}%</span>
            </div>
            <VProgressLinear
              :model-value="item.progress"
              color="success"
              height="6"
              rounded
            />
          </div>
        </template>

        <template #item.participants_count="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="info"
          >
            <VIcon
              start
              icon="tabler-users"
              size="14"
            />
            {{ item.participants_count ?? 0 }}
          </VChip>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="campaignStatusMeta[item.status].color"
          >
            <VIcon
              start
              :icon="campaignStatusMeta[item.status].icon"
              size="14"
            />
            {{ campaignStatusMeta[item.status].label }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-eye"
                  title="فتح الملف"
                  @click="openProfile(item.id)"
                />
                <VListItem
                  prepend-icon="tabler-edit"
                  title="تعديل"
                  @click="openEdit(item)"
                />
                <VDivider />
                <VListItem
                  prepend-icon="tabler-trash"
                  title="حذف"
                  class="text-error"
                  @click="deleteTarget = item; isDeleteVisible = true"
                />
              </VList>
            </VMenu>
          </IconBtn>
        </template>

        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="total"
          />
        </template>
      </VDataTableServer>
    </VCard>

    <CampaignDialog
      v-model="isDialogVisible"
      :item="editingItem"
      @saved="onSaved"
    />

    <VDialog
      v-model="isDeleteVisible"
      max-width="420"
    >
      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="error"
              variant="tonal"
              rounded
              size="42"
            >
              <VIcon
                icon="tabler-trash"
                size="24"
              />
            </VAvatar>
          </template>
          <VCardTitle>حذف الحملة</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف <strong>{{ deleteTarget?.title }}</strong>؟ سيتم فكّ ارتباط نباتاتها بالحملة.
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 pt-0">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDeleteVisible = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            :loading="deleting"
            @click="doDelete"
          >
            حذف
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

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
