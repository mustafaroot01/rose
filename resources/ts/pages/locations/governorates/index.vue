<script setup lang="ts">
import GovernorateDialog from '@/views/locations/GovernorateDialog.vue'
import type { Governorate } from '@/views/locations/types'
import { type ExportColumn, exportToCsv, exportToPdf } from '@/utils/exportTable'

// 👉 فلاتر وخيارات الجدول
const searchQuery = ref('')
const selectedStatus = ref<string>()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()
const expanded = ref<any[]>([])
const selectedRows = ref<any[]>([])

const statusOptions = [
  { title: 'ظاهرة', value: 'active' },
  { title: 'مخفية', value: 'hidden' },
]

const headers = [
  { title: 'المحافظة', key: 'name' },
  { title: 'الرمز', key: 'code', sortable: false },
  { title: 'عدد الأقضية', key: 'districts_count' },
  { title: 'الحالة', key: 'is_active' },
  { title: 'تاريخ الإضافة', key: 'created_at' },
  { title: 'إجراءات', key: 'actions', sortable: false },
]

// 👉 جلب البيانات
const { data, execute: fetchData } = await useApi<any>(createUrl('/admin/governorates', {
  query: {
    q: searchQuery,
    status: selectedStatus,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const governorates = computed<Governorate[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)
const stats = computed(() => data.value?.stats ?? { total: 0, active: 0, hidden: 0, districts_total: 0 })

const widgets = computed(() => [
  { title: 'إجمالي المحافظات', value: stats.value.total, icon: 'tabler-building-estate', color: 'primary' },
  { title: 'الظاهرة', value: stats.value.active, icon: 'tabler-eye', color: 'success' },
  { title: 'المخفية', value: stats.value.hidden, icon: 'tabler-eye-off', color: 'secondary' },
  { title: 'إجمالي الأقضية', value: stats.value.districts_total, icon: 'tabler-map-pin', color: 'info' },
])

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const formatDate = (iso: string) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

// 👉 Snackbar
const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

// 👉 نافذة الإضافة/التعديل
const isDialogVisible = ref(false)
const editingItem = ref<Governorate | null>(null)

const openAdd = () => { editingItem.value = null; isDialogVisible.value = true }
const openEdit = (item: Governorate) => { editingItem.value = item; isDialogVisible.value = true }
const onSaved = (message: string) => { notify(message); fetchData() }

// 👉 إخفاء/إظهار
const toggleVisibility = async (item: Governorate) => {
  const res = await $api<{ message: string }>(`/admin/governorates/${item.id}/toggle`, { method: 'PATCH' })
  notify(res.message)
  fetchData()
}

// 👉 حذف
const deleteTarget = ref<Governorate | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)

const confirmDelete = (item: Governorate) => { deleteTarget.value = item; isDeleteVisible.value = true }

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/governorates/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchData()
  }
  finally {
    deleting.value = false
  }
}

// 👉 عمليات جماعية
const isBulkDeleteVisible = ref(false)
const bulkLoading = ref(false)

const bulkToggle = async (isActive: boolean) => {
  bulkLoading.value = true
  try {
    const res = await $api<{ message: string }>('/admin/governorates/bulk/toggle', {
      method: 'PATCH',
      body: { ids: selectedRows.value, is_active: isActive },
    })
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
    const res = await $api<{ message: string }>('/admin/governorates/bulk', {
      method: 'DELETE',
      body: { ids: selectedRows.value },
    })
    notify(res.message)
    selectedRows.value = []
    isBulkDeleteVisible.value = false
    fetchData()
  }
  finally {
    bulkLoading.value = false
  }
}

// 👉 تصدير (يجلب كل الصفوف حسب الفلاتر الحالية)
const exportColumns: ExportColumn[] = [
  { title: 'المحافظة', value: r => r.name },
  { title: 'الرمز', value: r => r.code ?? '' },
  { title: 'عدد الأقضية', value: r => r.districts_count ?? 0 },
  { title: 'الحالة', value: r => (r.is_active ? 'ظاهرة' : 'مخفية') },
  { title: 'تاريخ الإضافة', value: r => formatDate(r.created_at) },
]

const doExport = async (format: 'csv' | 'pdf') => {
  const res = await $api<{ data: Governorate[] }>('/admin/governorates', {
    query: { q: searchQuery.value, status: selectedStatus.value, itemsPerPage: -1 },
  })
  const rows = res.data ?? []

  if (format === 'csv')
    exportToCsv('المحافظات', exportColumns, rows)
  else
    exportToPdf('قائمة المحافظات', exportColumns, rows)
}
</script>

<template>
  <section>
    <!-- 👉 بطاقات الإحصائيات -->
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
        <VCardTitle>المحافظات</VCardTitle>
        <template #append>
          <div class="d-flex gap-3">
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
            <VBtn
              prepend-icon="tabler-plus"
              @click="openAdd"
            >
              إضافة محافظة
            </VBtn>
          </div>
        </template>
      </VCardItem>

      <!-- 👉 الفلاتر -->
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="searchQuery"
              placeholder="ابحث بالاسم أو الرمز"
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
            md="2"
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

      <!-- 👉 شريط العمليات الجماعية -->
      <VExpandTransition>
        <div
          v-if="selectedRows.length"
          class="d-flex align-center flex-wrap gap-3 px-6 py-3 bg-var-theme-primary"
          style="background: rgba(var(--v-theme-primary), 0.08);"
        >
          <span class="font-weight-medium text-primary">
            محدَّد: {{ selectedRows.length }}
          </span>
          <VSpacer />
          <VBtn
            size="small"
            variant="tonal"
            color="success"
            prepend-icon="tabler-eye"
            :loading="bulkLoading"
            @click="bulkToggle(true)"
          >
            إظهار
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="secondary"
            prepend-icon="tabler-eye-off"
            :loading="bulkLoading"
            @click="bulkToggle(false)"
          >
            إخفاء
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

      <!-- 👉 الجدول -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:model-value="selectedRows"
        v-model:page="page"
        v-model:expanded="expanded"
        :items="governorates"
        item-value="id"
        :items-length="total"
        :headers="headers"
        show-select
        show-expand
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- المحافظة -->
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-x-3">
            <VAvatar
              size="34"
              variant="tonal"
              color="primary"
            >
              <VIcon
                icon="tabler-building-estate"
                size="20"
              />
            </VAvatar>
            <span class="font-weight-medium text-high-emphasis">{{ item.name }}</span>
          </div>
        </template>

        <!-- الرمز -->
        <template #item.code="{ item }">
          <span v-if="item.code">{{ item.code }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- عدد الأقضية -->
        <template #item.districts_count="{ item }">
          <VChip
            size="small"
            color="info"
            variant="tonal"
          >
            {{ item.districts_count ?? 0 }} قضاء
          </VChip>
        </template>

        <!-- الحالة -->
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            :color="item.is_active ? 'success' : 'secondary'"
            variant="tonal"
          >
            <VIcon
              start
              :icon="item.is_active ? 'tabler-eye' : 'tabler-eye-off'"
              size="16"
            />
            {{ item.is_active ? 'ظاهرة' : 'مخفية' }}
          </VChip>
        </template>

        <!-- التاريخ -->
        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <!-- الإجراءات -->
        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-edit"
                  title="تعديل"
                  @click="openEdit(item)"
                />
                <VListItem
                  :prepend-icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'"
                  :title="item.is_active ? 'إخفاء' : 'إظهار'"
                  @click="toggleVisibility(item)"
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

        <!-- صف موسّع: أقضية المحافظة -->
        <template #expanded-row="{ columns, item }">
          <tr class="v-data-table__tr">
            <td :colspan="columns.length">
              <div class="py-3">
                <div class="text-body-2 text-disabled mb-2">
                  أقضية {{ item.name }}
                </div>
                <div
                  v-if="item.districts?.length"
                  class="d-flex flex-wrap gap-2"
                >
                  <VChip
                    v-for="d in item.districts"
                    :key="d.id"
                    size="small"
                    variant="outlined"
                    :color="d.is_active ? 'primary' : 'secondary'"
                  >
                    <VIcon
                      start
                      icon="tabler-map-pin"
                      size="14"
                    />
                    {{ d.name }}
                  </VChip>
                </div>
                <span
                  v-else
                  class="text-disabled"
                >لا توجد أقضية مضافة.</span>
              </div>
            </td>
          </tr>
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

    <!-- 👉 نافذة الإضافة/التعديل -->
    <GovernorateDialog
      v-model="isDialogVisible"
      :item="editingItem"
      @saved="onSaved"
    />

    <!-- 👉 تأكيد الحذف -->
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
          <VCardTitle>حذف المحافظة</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف محافظة <strong>{{ deleteTarget?.name }}</strong>؟
          سيتم حذف أقضيتها أيضاً.
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

    <!-- 👉 تأكيد الحذف الجماعي -->
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
          هل أنت متأكد من حذف <strong>{{ selectedRows.length }}</strong> محافظة؟
          سيتم حذف أقضيتها أيضاً.
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
