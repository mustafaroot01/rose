<script setup lang="ts">
import ReportActionDialog from '@/views/plants/ReportActionDialog.vue'
import ReportDetailDrawer from '@/views/plants/ReportDetailDrawer.vue'
import { type PlantReport, reasonLabels, reportStatusMeta } from '@/views/plants/plantTypes'

const searchQuery = ref('')
const selectedStatus = ref<string>()
const selectedReason = ref<string>()
const dateFrom = ref<string>()
const dateTo = ref<string>()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()
const selectedRows = ref<number[]>([])

const statusOptions = [
  { title: 'جديد', value: 'new' },
  { title: 'قيد المراجعة', value: 'in_review' },
  { title: 'تم اتخاذ إجراء', value: 'action_taken' },
  { title: 'مرفوض', value: 'dismissed' },
]

const reasonOptions = Object.entries(reasonLabels).map(([value, title]) => ({ title, value }))

const headers = [
  { title: 'النبتة', key: 'plant', sortable: false },
  { title: 'المُبلِّغ', key: 'reporter', sortable: false },
  { title: 'السبب', key: 'reason' },
  { title: 'الحالة', key: 'status' },
  { title: 'التاريخ', key: 'created_at' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

const { data, execute: fetchData } = await useApi<any>(createUrl('/admin/plant-reports', {
  query: { q: searchQuery, status: selectedStatus, reason: selectedReason, date_from: dateFrom, date_to: dateTo, itemsPerPage, page, sortBy, orderBy },
}))

const reports = computed<PlantReport[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)
const stats = computed(() => data.value?.stats ?? { total: 0, new: 0, in_review: 0, action_taken: 0 })

const widgets = computed(() => [
  { title: 'إجمالي البلاغات', value: stats.value.total, icon: 'tabler-flag', color: 'primary' },
  { title: 'جديدة', value: stats.value.new, icon: 'tabler-bell', color: 'info' },
  { title: 'قيد المراجعة', value: stats.value.in_review, icon: 'tabler-clock', color: 'warning' },
  { title: 'تم اتخاذ إجراء', value: stats.value.action_taken, icon: 'tabler-circle-check', color: 'success' },
])

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const formatDate = (iso: string) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const snackbar = ref({ show: false, text: '', color: 'success' })

const notify = (text: string, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

// 👉 لوحة التفاصيل
const isDetailVisible = ref(false)
const detailReport = ref<PlantReport | null>(null)

const openDetail = (item: PlantReport) => {
  detailReport.value = item
  isDetailVisible.value = true
}

// 👉 حوار اتخاذ إجراء (مع ملاحظة)
const isActionVisible = ref(false)
const activeReport = ref<PlantReport | null>(null)

const openAction = (item: PlantReport) => {
  activeReport.value = item
  isActionVisible.value = true
}

const onSaved = (message: string) => {
  notify(message)
  fetchData()
}

const onDetailChanged = (message: string) => {
  notify(message)
  isDetailVisible.value = false
  fetchData()
}

// 👉 تغيير سريع لحالة بلاغ واحد
const quickStatus = async (item: PlantReport, status: string) => {
  try {
    const res = await $api<{ message: string }>(`/admin/plant-reports/${item.id}/status`, { method: 'PATCH', body: { status } })

    notify(res.message)
    fetchData()
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر تنفيذ العملية.', 'error')
  }
}

// 👉 حذف مفرد
const deleteTarget = ref<PlantReport | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)

const confirmDelete = (item: PlantReport) => {
  deleteTarget.value = item
  isDeleteVisible.value = true
}

const doDelete = async () => {
  if (!deleteTarget.value)
    return

  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plant-reports/${deleteTarget.value.id}`, { method: 'DELETE' })

    notify(res.message)
    isDeleteVisible.value = false
    fetchData()
  }
  finally {
    deleting.value = false
  }
}

// 👉 إجراءات جماعية
const bulkLoading = ref(false)
const isBulkDeleteVisible = ref(false)

const bulkStatus = async (status: string) => {
  bulkLoading.value = true
  try {
    const res = await $api<{ message: string }>('/admin/plant-reports/bulk/status', { method: 'PATCH', body: { ids: selectedRows.value, status } })

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
    const res = await $api<{ message: string }>('/admin/plant-reports/bulk', { method: 'DELETE', body: { ids: selectedRows.value } })

    notify(res.message)
    selectedRows.value = []
    isBulkDeleteVisible.value = false
    fetchData()
  }
  finally {
    bulkLoading.value = false
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
        <VCardTitle>استلام البلاغات</VCardTitle>
      </VCardItem>

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="ابحث باسم النبتة أو المُبلِّغ"
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
              v-model="selectedStatus"
              placeholder="كل الحالات"
              :items="statusOptions"
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
              v-model="selectedReason"
              placeholder="كل الأسباب"
              :items="reasonOptions"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="dateFrom"
              type="date"
              label="من تاريخ"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="dateTo"
              type="date"
              label="إلى تاريخ"
              clearable
              clear-icon="tabler-x"
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
            color="warning"
            prepend-icon="tabler-clock"
            :loading="bulkLoading"
            @click="bulkStatus('in_review')"
          >
            قيد المراجعة
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="success"
            prepend-icon="tabler-circle-check"
            :loading="bulkLoading"
            @click="bulkStatus('action_taken')"
          >
            اتخاذ إجراء
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-x"
            :loading="bulkLoading"
            @click="bulkStatus('dismissed')"
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
        :items="reports"
        item-value="id"
        :items-length="total"
        :headers="headers"
        show-select
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.plant="{ item }">
          <div class="d-flex align-center gap-x-2">
            <RouterLink
              v-if="item.plant"
              :to="{ name: 'plants-id', params: { id: item.plant.id } }"
              class="d-flex align-center gap-x-2 text-link font-weight-medium"
            >
              <VIcon
                icon="tabler-plant"
                size="20"
              />
              {{ item.plant.name }}
            </RouterLink>
            <span
              v-else
              class="text-disabled"
            >—</span>
            <VChip
              v-if="(item.plant?.reports_count ?? 0) > 1"
              size="x-small"
              color="error"
              variant="tonal"
            >
              {{ item.plant?.reports_count }} بلاغات
            </VChip>
          </div>
        </template>

        <template #item.reporter="{ item }">
          <RouterLink
            v-if="item.reporter"
            :to="{ name: 'users-id', params: { id: item.reporter.id } }"
            class="d-flex flex-column text-link"
          >
            <span class="font-weight-medium">{{ item.reporter.name }}</span>
            <span class="text-sm text-disabled">{{ item.reporter.phone }}</span>
          </RouterLink>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.reason="{ item }">
          <div class="d-flex flex-column">
            <VChip
              size="small"
              color="warning"
              variant="tonal"
            >
              {{ reasonLabels[item.reason] }}
            </VChip>
            <span
              v-if="item.reason_note"
              class="text-sm text-disabled mt-1 text-truncate"
              style="max-inline-size: 220px;"
            >{{ item.reason_note }}</span>
          </div>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="reportStatusMeta[item.status].color"
            variant="tonal"
          >
            {{ reportStatusMeta[item.status].label }}
          </VChip>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center">
            <IconBtn
              v-if="item.status === 'new'"
              v-tooltip="'بدء المراجعة'"
              @click="quickStatus(item, 'in_review')"
            >
              <VIcon icon="tabler-clock" />
            </IconBtn>
            <IconBtn
              v-tooltip="'عرض التفاصيل'"
              @click="openDetail(item)"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>
            <IconBtn>
              <VIcon icon="tabler-dots-vertical" />
              <VMenu activator="parent">
                <VList density="compact">
                  <VListItem
                    prepend-icon="tabler-checkup-list"
                    title="اتخاذ إجراء"
                    @click="openAction(item)"
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
          </div>
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

    <ReportDetailDrawer
      v-model="isDetailVisible"
      :report="detailReport"
      @action="openAction"
      @changed="onDetailChanged"
    />

    <ReportActionDialog
      v-model="isActionVisible"
      :report="activeReport"
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
          <VCardTitle>حذف البلاغ</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف هذا البلاغ؟
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
          هل أنت متأكد من حذف <strong>{{ selectedRows.length }}</strong> بلاغ؟
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
