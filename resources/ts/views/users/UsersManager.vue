<script setup lang="ts">
import UserEditDialog from '@/views/users/UserEditDialog.vue'
import type { AccountTypeOption, AppUser } from '@/views/users/types'
import type { DistrictOption, GovernorateOption } from '@/views/locations/types'
import { type ExportColumn, exportToCsv, exportToPdf } from '@/utils/exportTable'

interface Props {
  title: string
  exportName: string
  role?: string
  roles?: string
  showRole?: boolean
  endpoint?: string
  profileRoute?: 'users-id' | 'citizens-id'
  canEdit?: boolean
  canCreate?: boolean
}
const props = withDefaults(defineProps<Props>(), {
  showRole: false,
  endpoint: '/admin/users',
  profileRoute: 'users-id',
  canEdit: true,
  canCreate: false,
})

const router = useRouter()
const openProfile = (id: number) => router.push({ name: props.profileRoute, params: { id } })

const searchQuery = ref('')
const selectedType = ref<number>()
const selectedStatus = ref<string>()
const selectedGovernorate = ref<number>()
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref<string>()
const orderBy = ref<string>()
const selectedRows = ref<number[]>([])

const statusOptions = [
  { title: 'مُفعّل', value: 'active' },
  { title: 'محظور', value: 'blocked' },
]

const roleMeta: Record<string, { label: string, color: string, icon: string }> = {
  admin: { label: 'مشرف عام', color: 'primary', icon: 'tabler-crown' },
  supervisor: { label: 'مشرف', color: 'warning', icon: 'tabler-shield-check' },
  citizen: { label: 'مواطن', color: 'info', icon: 'tabler-user' },
}

const headers = computed(() => [
  { title: 'المستخدم', key: 'name' },
  ...(props.showRole ? [{ title: 'الدور', key: 'role' }] : []),
  { title: 'نوع الحساب', key: 'account_type', sortable: false },
  { title: 'الموقع', key: 'location', sortable: false },
  { title: 'الحالة', key: 'status' },
  { title: 'الانضمام', key: 'created_at' },
  { title: 'إجراءات', key: 'actions', sortable: false },
])

const { data: typeOptionsData } = await useApi<AccountTypeOption[]>('/admin/account-types/options')
const { data: govOptionsData } = await useApi<GovernorateOption[]>('/admin/governorates/options')
const { data: distOptionsData } = await useApi<DistrictOption[]>('/admin/districts/options')

const accountTypes = computed<AccountTypeOption[]>(() => typeOptionsData.value ?? [])
const governorates = computed<GovernorateOption[]>(() => govOptionsData.value ?? [])
const districts = computed<DistrictOption[]>(() => distOptionsData.value ?? [])
const typeFilterItems = computed(() => accountTypes.value.map(t => ({ title: t.name, value: t.id })))
const governorateFilterItems = computed(() => governorates.value.map(g => ({ title: g.name, value: g.id })))

const { data, execute: fetchData } = await useApi<any>(createUrl(props.endpoint, {
  query: {
    q: searchQuery,
    role: props.role,
    roles: props.roles,
    account_type_id: selectedType,
    status: selectedStatus,
    governorate_id: selectedGovernorate,
    itemsPerPage,
    page,
    sortBy,
    orderBy,
  },
}))

const users = computed<AppUser[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)
const stats = computed(() => data.value?.stats ?? { total: 0, active: 0, blocked: 0, verified: 0 })

const widgets = computed(() => [
  { title: 'الإجمالي', value: stats.value.total, icon: 'tabler-users', color: 'primary' },
  { title: 'المُفعّلون', value: stats.value.active, icon: 'tabler-user-check', color: 'success' },
  { title: 'المحظورون', value: stats.value.blocked, icon: 'tabler-user-x', color: 'error' },
  { title: 'الموثّقون', value: stats.value.verified, icon: 'tabler-rosette-discount-check', color: 'info' },
])

const updateOptions = (options: any) => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

const formatDate = (iso: string) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const isEditVisible = ref(false)
const editingItem = ref<AppUser | null>(null)
const dialogCreate = ref(false)
const openEdit = (item: AppUser) => { dialogCreate.value = false; editingItem.value = item; isEditVisible.value = true }
const openCreate = () => { dialogCreate.value = true; editingItem.value = null; isEditVisible.value = true }
const onSaved = (message: string) => { notify(message); fetchData() }

const toggleStatus = async (item: AppUser) => {
  try {
    const res = await $api<{ message: string }>(`${props.endpoint}/${item.id}/toggle-status`, { method: 'PATCH' })
    notify(res.message)
    fetchData()
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر تنفيذ العملية.', 'error')
  }
}

const deleteTarget = ref<AppUser | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)
const confirmDelete = (item: AppUser) => { deleteTarget.value = item; isDeleteVisible.value = true }

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`${props.endpoint}/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchData()
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر الحذف.', 'error')
  }
  finally {
    deleting.value = false
  }
}

const bulkLoading = ref(false)
const isBulkDeleteVisible = ref(false)

const bulkStatus = async (status: 'active' | 'blocked') => {
  bulkLoading.value = true
  try {
    const res = await $api<{ message: string }>(`${props.endpoint}/bulk/status`, { method: 'PATCH', body: { ids: selectedRows.value, status } })
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
    const res = await $api<{ message: string }>(`${props.endpoint}/bulk`, { method: 'DELETE', body: { ids: selectedRows.value } })
    notify(res.message)
    selectedRows.value = []
    isBulkDeleteVisible.value = false
    fetchData()
  }
  finally {
    bulkLoading.value = false
  }
}

const exportColumns: ExportColumn[] = [
  { title: 'الاسم', value: r => r.name },
  { title: 'الجوال', value: r => r.phone },
  { title: 'الدور', value: r => roleMeta[r.role]?.label ?? r.role },
  { title: 'نوع الحساب', value: r => r.account_type?.name ?? '' },
  { title: 'المحافظة', value: r => r.governorate?.name ?? '' },
  { title: 'الحالة', value: r => (r.status === 'active' ? 'مُفعّل' : 'محظور') },
]

const doExport = async (format: 'csv' | 'pdf') => {
  const res = await $api<{ data: AppUser[] }>(props.endpoint, {
    query: { q: searchQuery.value, role: props.role, roles: props.roles, account_type_id: selectedType.value, status: selectedStatus.value, governorate_id: selectedGovernorate.value, itemsPerPage: -1 },
  })
  const rows = res.data ?? []
  if (format === 'csv')
    exportToCsv(props.exportName, exportColumns, rows)
  else
    exportToPdf(props.exportName, exportColumns, rows)
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
        <VCardTitle>{{ title }}</VCardTitle>
        <template #append>
          <div class="d-flex align-center gap-3 flex-wrap">
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
              v-if="canCreate"
              prepend-icon="tabler-plus"
              @click="openCreate"
            >
              إضافة
            </VBtn>
          </div>
        </template>
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
              placeholder="ابحث بالاسم أو الجوال"
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
              v-model="selectedType"
              placeholder="نوع الحساب"
              :items="typeFilterItems"
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
              :items="governorateFilterItems"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
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
            prepend-icon="tabler-user-check"
            :loading="bulkLoading"
            @click="bulkStatus('active')"
          >
            تفعيل
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            color="warning"
            prepend-icon="tabler-user-x"
            :loading="bulkLoading"
            @click="bulkStatus('blocked')"
          >
            حظر
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
        :items="users"
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
              size="38"
              :variant="!item.avatar ? 'tonal' : undefined"
              :color="!item.avatar ? (roleMeta[item.role]?.color || 'primary') : undefined"
            >
              <VImg
                v-if="item.avatar"
                :src="item.avatar"
              />
              <span v-else>{{ avatarText(item.name) }}</span>
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="font-weight-medium text-high-emphasis">{{ item.name }}</span>
              <span class="text-sm text-disabled">{{ item.phone }}</span>
            </div>
          </div>
        </template>

        <template #item.role="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VIcon
              :icon="roleMeta[item.role]?.icon || 'tabler-user'"
              :color="roleMeta[item.role]?.color || 'secondary'"
              size="20"
            />
            <span>{{ roleMeta[item.role]?.label || item.role }}</span>
          </div>
        </template>

        <template #item.account_type="{ item }">
          <VChip
            v-if="item.account_type"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ item.account_type.name }}
          </VChip>
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

        <template #item.status="{ item }">
          <div class="d-flex align-center gap-x-2">
            <VChip
              size="small"
              :color="item.status === 'active' ? 'success' : 'error'"
              variant="tonal"
            >
              {{ item.status === 'active' ? 'مُفعّل' : 'محظور' }}
            </VChip>
            <VIcon
              v-if="item.phone_verified"
              icon="tabler-rosette-discount-check"
              color="success"
              size="18"
            />
          </div>
        </template>

        <template #item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template #item.actions="{ item }">
          <IconBtn>
            <VIcon icon="tabler-dots-vertical" />
            <VMenu activator="parent">
              <VList density="compact">
                <VListItem
                  prepend-icon="tabler-user-circle"
                  title="عرض الملف"
                  @click="openProfile(item.id)"
                />
                <VListItem
                  v-if="canEdit"
                  prepend-icon="tabler-edit"
                  title="تعديل"
                  @click="openEdit(item)"
                />
                <VListItem
                  :prepend-icon="item.status === 'active' ? 'tabler-user-x' : 'tabler-user-check'"
                  :title="item.status === 'active' ? 'حظر' : 'تفعيل'"
                  @click="toggleStatus(item)"
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

    <UserEditDialog
      v-model="isEditVisible"
      :item="editingItem"
      :create="dialogCreate"
      :endpoint="endpoint"
      :account-types="accountTypes"
      :governorates="governorates"
      :districts="districts"
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
          <VCardTitle>حذف المستخدم</VCardTitle>
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
          هل أنت متأكد من حذف <strong>{{ selectedRows.length }}</strong> مستخدم؟
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
