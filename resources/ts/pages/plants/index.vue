<script setup lang="ts">
import PlantDetailDialog from '@/views/plants/PlantDetailDialog.vue'
import { type Plant, plantStatusMeta } from '@/views/plants/plantTypes'
import type { GovernorateOption } from '@/views/locations/types'
import type { PlantCategoryOption } from '@/views/plants/types'
import { type ExportColumn, exportToCsv, exportToPdf } from '@/utils/exportTable'

const router = useRouter()
const openProfile = (id: number) => router.push({ name: 'plants-id', params: { id } })

const searchQuery = ref('')
const selectedCategory = ref<number>()
const selectedGovernorate = ref<number>()
const selectedStatus = ref<string>()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()
const selectedRows = ref<number[]>([])

const statusOptions = [
  { title: 'قيد المراجعة', value: 'pending' },
  { title: 'معتمدة', value: 'approved' },
  { title: 'مرفوضة', value: 'rejected' },
]

const headers = [
  { title: 'النبتة', key: 'name' },
  { title: 'صاحبها', key: 'owner', sortable: false },
  { title: 'الموقع', key: 'location', sortable: false },
  { title: 'العمر', key: 'planting_date' },
  { title: 'البلاغات', key: 'reports_count', sortable: false },
  { title: 'الإعجابات', key: 'likes_count', sortable: false },
  { title: 'الحالة', key: 'status' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const [{ data: catOptions }, { data: govOptions }, { data, execute: fetchData }] = await Promise.all([
  useApi<PlantCategoryOption[]>('/admin/plant-categories/options'),
  useApi<GovernorateOption[]>('/admin/governorates/options'),
  useApi<any>(createUrl('/admin/plants', {
    query: {
      q: searchQuery,
      category_id: selectedCategory,
      governorate_id: selectedGovernorate,
      status: selectedStatus,
      itemsPerPage,
      page,
      sortBy,
      orderBy,
    },
  })),
])

const categoryItems = computed(() => (catOptions.value ?? []).map(c => ({ title: c.name, value: c.id })))
const governorateItems = computed(() => (govOptions.value ?? []).map(g => ({ title: g.name, value: g.id })))

const plants = computed<Plant[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)
const stats = computed(() => data.value?.stats ?? { total: 0, approved: 0, pending: 0, rejected: 0 })

const widgets = computed(() => [
  { title: 'إجمالي النباتات', value: stats.value.total, icon: 'tabler-plant', color: 'primary' },
  { title: 'معتمدة', value: stats.value.approved, icon: 'tabler-circle-check', color: 'success' },
  { title: 'قيد المراجعة', value: stats.value.pending, icon: 'tabler-clock', color: 'warning' },
  { title: 'مرفوضة', value: stats.value.rejected, icon: 'tabler-circle-x', color: 'error' },
])

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const formatDate = (iso: string | null) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

// تفاصيل
const isDetailVisible = ref(false)
const detailPlantId = ref<number | null>(null)
const openDetail = (item: Plant) => { detailPlantId.value = item.id; isDetailVisible.value = true }
const onChanged = (message: string) => { notify(message); fetchData() }

// تغيير حالة سريع
const setStatus = async (item: Plant, status: 'approved' | 'rejected') => {
  const res = await $api<{ message: string }>(`/admin/plants/${item.id}/status`, { method: 'PATCH', body: { status } })
  notify(res.message)
  fetchData()
}

// حذف
const deleteTarget = ref<Plant | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)
const confirmDelete = (item: Plant) => { deleteTarget.value = item; isDeleteVisible.value = true }

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plants/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchData()
  }
  finally {
    deleting.value = false
  }
}

// عمليات جماعية
const bulkLoading = ref(false)
const isBulkDeleteVisible = ref(false)

const bulkStatus = async (status: 'approved' | 'rejected') => {
  bulkLoading.value = true
  try {
    const res = await $api<{ message: string }>('/admin/plants/bulk/status', { method: 'PATCH', body: { ids: selectedRows.value, status } })
    notify(res.message)
    selectedRows.value = []
    fetchData()
  }
  finally {
    bulkLoading.value = false
  }
}

const doBulkDelete = async () => {
  bulkLoading.value = true
  try {
    const res = await $api<{ message: string }>('/admin/plants/bulk', { method: 'DELETE', body: { ids: selectedRows.value } })
    notify(res.message)
    selectedRows.value = []
    isBulkDeleteVisible.value = false
    fetchData()
  }
  finally {
    bulkLoading.value = false
  }
}

// تصدير
const exportColumns: ExportColumn[] = [
  { title: 'النبتة', value: r => r.name },
  { title: 'النوع', value: r => r.type ?? '' },
  { title: 'الصنف', value: r => r.category?.name ?? '' },
  { title: 'صاحبها', value: r => r.owner?.name ?? '' },
  { title: 'المحافظة', value: r => r.governorate?.name ?? '' },
  { title: 'العمر', value: r => r.age_label ?? '' },
  { title: 'الحالة', value: r => plantStatusMeta[r.status]?.label ?? r.status },
]

const doExport = async (format: 'csv' | 'pdf') => {
  const res = await $api<{ data: Plant[] }>('/admin/plants', {
    query: { q: searchQuery.value, category_id: selectedCategory.value, governorate_id: selectedGovernorate.value, status: selectedStatus.value, itemsPerPage: -1 },
  })
  const rows = res.data ?? []
  if (format === 'csv')
    exportToCsv('النباتات', exportColumns, rows)
  else
    exportToPdf('قائمة النباتات', exportColumns, rows)
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
        <VCardTitle>إدارة النباتات</VCardTitle>
        <template #append>
          <VBtn
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-download"
            append-icon="tabler-chevron-down"
          >
            تصدير
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-file-type-csv"
                  title="Excel (CSV)"
                  @click="doExport('csv')"
                />
                <VListItem
                  prepend-icon="tabler-file-type-pdf"
                  title="PDF"
                  @click="doExport('pdf')"
                />
              </VList>
            </VMenu>
          </VBtn>
        </template>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="ابحث بالاسم أو النوع"
              prepend-inner-icon="tabler-search"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="selectedCategory"
              placeholder="الصنف"
              :items="categoryItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="selectedGovernorate"
              placeholder="المحافظة"
              :items="governorateItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
            md="2"
          >
            <AppSelect
              v-model="selectedStatus"
              placeholder="الحالة"
              :items="statusOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="2"
            md="1"
          >
            <AppSelect
              :model-value="itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: -1, title: 'الكل' },
              ]"
              @update:model-value="itemsPerPage = parseInt($event, 10)"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VExpandTransition>
        <div
          v-if="selectedRows.length"
          class="d-flex align-center flex-wrap gap-3 px-6 py-3"
          style="background: rgba(var(--v-theme-primary), 0.08);"
        >
          <span class="font-weight-medium text-primary">محدَّد: {{ selectedRows.length }}</span>
          <VSpacer />
          <VBtn
            size="small"
            variant="tonal"
            color="success"
            prepend-icon="tabler-check"
            :loading="bulkLoading"
            @click="bulkStatus('approved')"
          >
            اعتماد
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="error"
            prepend-icon="tabler-x"
            :loading="bulkLoading"
            @click="bulkStatus('rejected')"
          >
            رفض
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="error"
            prepend-icon="tabler-trash"
            @click="isBulkDeleteVisible = true"
          >
            حذف المحدد
          </VBtn>
        </div>
      </VExpandTransition>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        :items="plants"
        item-value="id"
        :items-length="total"
        :headers="headers"
        show-select
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3">
            <VAvatar
              size="34"
              variant="tonal"
              :color="item.category?.color || 'primary'"
            >
              <VIcon
                :icon="item.category?.icon || 'tabler-plant'"
                size="20"
              />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-high-emphasis">{{ item.name }}</span>
              <span class="text-sm text-disabled">{{ item.type || item.category?.name || '—' }}</span>
            </div>
          </div>
        </template>

        <template #item.owner="{ item }">
          <span v-if="item.owner">{{ item.owner.name }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.location="{ item }">
          <span v-if="item.governorate">{{ item.governorate.name }}<span
            v-if="item.district"
            class="text-disabled"
          > — {{ item.district.name }}</span></span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.planting_date="{ item }">
          <span v-if="item.age_label">{{ item.age_label }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.reports_count="{ item }">
          <VChip
            v-if="item.reports_count"
            size="small"
            color="error"
            variant="tonal"
          >
            {{ item.reports_count }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >0</span>
        </template>

        <template #item.likes_count="{ item }">
          <VChip
            v-if="item.likes_count"
            size="small"
            color="error"
            variant="tonal"
            prepend-icon="tabler-heart-filled"
          >
            {{ item.likes_count }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >0</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="plantStatusMeta[item.status].color"
            variant="tonal"
          >
            {{ plantStatusMeta[item.status].label }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-user-circle"
                  title="فتح الملف"
                  @click="openProfile(item.id)"
                />
                <VListItem
                  prepend-icon="tabler-eye"
                  title="عرض سريع"
                  @click="openDetail(item)"
                />
                <VListItem
                  v-if="item.status !== 'approved'"
                  prepend-icon="tabler-check"
                  title="اعتماد"
                  @click="setStatus(item, 'approved')"
                />
                <VListItem
                  v-if="item.status !== 'rejected'"
                  prepend-icon="tabler-x"
                  title="رفض"
                  @click="setStatus(item, 'rejected')"
                />
                <VDivider />
                <VListItem
                  prepend-icon="tabler-trash"
                  title="حذف"
                  class="text-error"
                  @click="confirmDelete(item)"
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

    <PlantDetailDialog
      v-model="isDetailVisible"
      :plant-id="detailPlantId"
      @changed="onChanged"
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
          <VCardTitle>حذف النبتة</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف <strong>{{ deleteTarget?.name }}</strong>؟
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

    <VDialog
      v-model="isBulkDeleteVisible"
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
          <VCardTitle>حذف المحدد</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف <strong>{{ selectedRows.length }}</strong> نبتة؟
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 pt-0">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isBulkDeleteVisible = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            :loading="bulkLoading"
            @click="doBulkDelete"
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
