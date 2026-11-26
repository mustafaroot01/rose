<script setup lang="ts">
interface ActivityLog {
  id: number
  user: { id: number; name: string; role: string } | null
  action: string
  subject_type: string | null
  subject_id: number | null
  description: string
  meta: Record<string, any> | null
  ip_address: string | null
  created_at: string
}

const searchQuery = ref('')
const selectedAction = ref<string>()
const itemsPerPage = ref(15)
const page = ref(1)

const actionOptions = [
  { title: 'إضافة', value: 'create' },
  { title: 'تعديل', value: 'update' },
  { title: 'حذف', value: 'delete' },
  { title: 'تغيير حالة', value: 'status_change' },
  { title: 'تسجيل دخول', value: 'login' },
]

const actionMeta: Record<string, { label: string; color: string; icon: string }> = {
  create: { label: 'إضافة', color: 'success', icon: 'tabler-plus' },
  update: { label: 'تعديل', color: 'info', icon: 'tabler-pencil' },
  delete: { label: 'حذف', color: 'error', icon: 'tabler-trash' },
  status_change: { label: 'تغيير حالة', color: 'warning', icon: 'tabler-toggle-right' },
  login: { label: 'تسجيل دخول', color: 'primary', icon: 'tabler-login' },
}

const headers = [
  { title: 'المستخدم', key: 'user', sortable: false },
  { title: 'العملية', key: 'action', sortable: false },
  { title: 'التفاصيل', key: 'description', sortable: false },
  { title: 'العنوان (IP)', key: 'ip_address', sortable: false },
  { title: 'التاريخ', key: 'created_at', sortable: false },
]

const { data } = await useApi<any>(createUrl('/admin/activity-log', {
  query: { q: searchQuery, action: selectedAction, itemsPerPage, page },
}))

const logs = computed<ActivityLog[]>(() => data.value?.data ?? [])
const total = computed<number>(() => data.value?.meta?.total ?? 0)

const formatDate = (iso: string) =>
  new Date(iso).toLocaleDateString('ar-EG', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })

const roleMeta: Record<string, { label: string; color: string }> = {
  admin: { label: 'مدير', color: 'primary' },
  supervisor: { label: 'مشرف', color: 'warning' },
  citizen: { label: 'مواطن', color: 'info' },
}
</script>

<template>
  <section>
    <VCard>
      <VCardItem>
        <VCardTitle>سجل العمليات</VCardTitle>
        <VCardSubtitle>جميع إجراءات المشرفين والإداريين</VCardSubtitle>
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
              placeholder="ابحث بالاسم أو التفاصيل"
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
              v-model="selectedAction"
              placeholder="كل العمليات"
              :items="actionOptions"
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
        :items="logs"
        item-value="id"
        :items-length="total"
        :headers="headers"
        class="text-no-wrap"
      >
        <template #item.user="{ item }">
          <div
            v-if="item.user"
            class="d-flex align-center gap-x-2"
          >
            <VAvatar
              size="32"
              :color="roleMeta[item.user.role]?.color ?? 'secondary'"
              variant="tonal"
            >
              <span class="text-sm font-weight-medium">{{ item.user.name.charAt(0) }}</span>
            </VAvatar>
            <div>
              <RouterLink
                :to="{ name: 'users-id', params: { id: item.user.id } }"
                class="font-weight-medium text-link"
              >
                {{ item.user.name }}
              </RouterLink>
              <div class="text-xs text-disabled">
                {{ roleMeta[item.user.role]?.label ?? item.user.role }}
              </div>
            </div>
          </div>
          <span
            v-else
            class="text-disabled"
          >النظام</span>
        </template>

        <template #item.action="{ item }">
          <VChip
            size="small"
            :color="actionMeta[item.action]?.color ?? 'secondary'"
            variant="tonal"
          >
            <VIcon
              start
              size="16"
              :icon="actionMeta[item.action]?.icon ?? 'tabler-activity'"
            />
            {{ actionMeta[item.action]?.label ?? item.action }}
          </VChip>
        </template>

        <template #item.description="{ item }">
          <span class="text-truncate d-inline-block" style="max-inline-size: 400px;">
            {{ item.description }}
          </span>
        </template>

        <template #item.ip_address="{ item }">
          <span class="text-disabled text-sm">{{ item.ip_address ?? '—' }}</span>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-sm">{{ formatDate(item.created_at) }}</span>
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
  </section>
</template>
