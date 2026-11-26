<script setup lang="ts">
import type { AppUser } from '@/views/users/types'
import { type Plant, plantStatusMeta } from '@/views/plants/plantTypes'

interface PaginatedResponse<T> {
  data: T[]
  meta?: { total?: number }
}

const route = useRoute('citizens-id')
const router = useRouter()
const citizenId = route.params.id

// 👉 بيانات المواطن
const { data: userData, execute: refreshUser } = await useApi<{ data: AppUser }>(`/admin/citizens/${citizenId}`)
const user = computed<AppUser | null>(() => userData.value?.data ?? null)

// 👉 نباتاته — مرقّمة
const itemsPerPage = ref(10)
const page = ref(1)

const { data: plantsData } = await useApi<PaginatedResponse<Plant>>(createUrl(`/admin/citizens/${citizenId}/plants`, {
  query: { itemsPerPage, page },
}))

const plants = computed<Plant[]>(() => plantsData.value?.data ?? [])
const plantsTotal = computed<number>(() => plantsData.value?.meta?.total ?? 0)

const formatDate = (iso?: string | null) =>
  iso ? new Date(iso).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

const headers = [
  { title: 'النبتة', key: 'name' },
  { title: 'الصنف', key: 'category', sortable: false },
  { title: 'المحافظة', key: 'governorate', sortable: false },
  { title: 'الحالة', key: 'status' },
  { title: 'العمر', key: 'age_label', sortable: false },
]

const details = computed(() => {
  if (!user.value)
    return []

  return [
    { icon: 'tabler-phone', label: 'الجوال', value: user.value.phone },
    { icon: 'tabler-mail', label: 'البريد', value: user.value.email || '—' },
    { icon: 'tabler-category-2', label: 'نوع الحساب', value: user.value.account_type?.name || '—' },
    { icon: 'tabler-map-pin', label: 'المحافظة', value: user.value.governorate?.name || '—' },
    { icon: 'tabler-map-2', label: 'القضاء', value: user.value.district?.name || '—' },
  ]
})

const stats = computed(() => {
  if (!user.value)
    return []

  return [
    { icon: 'tabler-plant-2', color: 'success', label: 'النباتات المضافة', value: user.value.plants_count ?? 0 },
    {
      icon: user.value.phone_verified ? 'tabler-rosette-discount-check' : 'tabler-alert-circle',
      color: user.value.phone_verified ? 'info' : 'warning',
      label: 'توثيق الجوال',
      value: user.value.phone_verified ? 'موثّق' : 'غير موثّق',
    },
    { icon: 'tabler-calendar-plus', color: 'primary', label: 'تاريخ الانضمام', value: formatDate(user.value.created_at) },
    {
      icon: 'tabler-login-2',
      color: 'secondary',
      label: 'آخر تسجيل دخول',
      value: user.value.last_login_at ? formatDate(user.value.last_login_at) : 'لم يدخل بعد',
    },
  ]
})

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const toggling = ref(false)
const toggleStatus = async () => {
  if (!user.value)
    return
  toggling.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/citizens/${user.value.id}/toggle-status`, { method: 'PATCH' })
    notify(res.message)
    refreshUser()
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر تنفيذ العملية.', 'error')
  }
  finally {
    toggling.value = false
  }
}

const isDeleteVisible = ref(false)
const deleting = ref(false)
const doDelete = async () => {
  if (!user.value)
    return
  deleting.value = true
  try {
    await $api<{ message: string }>(`/admin/citizens/${user.value.id}`, { method: 'DELETE' })
    isDeleteVisible.value = false
    router.push({ name: 'citizens' })
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر الحذف.', 'error')
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <section v-if="user">
    <div class="d-flex align-center gap-2 mb-6">
      <IconBtn @click="router.back()">
        <VIcon icon="tabler-arrow-right" />
      </IconBtn>
      <h4 class="text-h4">
        ملف المواطن
      </h4>
    </div>

    <VCard class="mb-6">
      <div class="user-profile-cover" />
      <VCardText class="d-flex flex-column flex-sm-row align-center text-center text-sm-start gap-4 pt-0">
        <VAvatar
          size="108"
          class="user-profile-avatar"
          :variant="!user.avatar ? 'tonal' : undefined"
          :color="!user.avatar ? 'info' : undefined"
        >
          <VImg
            v-if="user.avatar"
            :src="user.avatar"
          />
          <span
            v-else
            class="text-h3"
          >{{ avatarText(user.name) }}</span>
        </VAvatar>

        <div class="flex-grow-1">
          <h4 class="text-h4 mb-1">
            {{ user.name }}
          </h4>
          <div class="d-flex flex-wrap justify-center justify-sm-start gap-2">
            <VChip
              size="small"
              color="info"
              variant="tonal"
            >
              <VIcon
                start
                icon="tabler-user"
                size="14"
              />
              مواطن
            </VChip>
            <VChip
              size="small"
              :color="user.status === 'active' ? 'success' : 'error'"
              variant="tonal"
            >
              <VIcon
                start
                :icon="user.status === 'active' ? 'tabler-circle-check' : 'tabler-ban'"
                size="14"
              />
              {{ user.status === 'active' ? 'مُفعّل' : 'محظور' }}
            </VChip>
            <VChip
              size="small"
              :color="user.phone_verified ? 'info' : 'warning'"
              variant="tonal"
            >
              <VIcon
                start
                :icon="user.phone_verified ? 'tabler-rosette-discount-check' : 'tabler-alert-circle'"
                size="14"
              />
              {{ user.phone_verified ? 'جوال موثّق' : 'غير موثّق' }}
            </VChip>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-center gap-2">
          <VBtn
            variant="tonal"
            :color="user.status === 'active' ? 'warning' : 'success'"
            :prepend-icon="user.status === 'active' ? 'tabler-user-x' : 'tabler-user-check'"
            :loading="toggling"
            @click="toggleStatus"
          >
            {{ user.status === 'active' ? 'حظر' : 'تفعيل' }}
          </VBtn>
          <IconBtn
            variant="tonal"
            color="error"
            @click="isDeleteVisible = true"
          >
            <VIcon icon="tabler-trash" />
          </IconBtn>
        </div>
      </VCardText>
    </VCard>

    <VRow class="mb-6">
      <VCol
        v-for="(s, i) in stats"
        :key="i"
        cols="6"
        md="3"
      >
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="s.color"
              variant="tonal"
              rounded
              size="44"
            >
              <VIcon
                :icon="s.icon"
                size="26"
              />
            </VAvatar>
            <div class="text-truncate">
              <div class="text-body-2 text-disabled">
                {{ s.label }}
              </div>
              <h6 class="text-h6 text-truncate">
                {{ s.value }}
              </h6>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>معلومات الاتصال والموقع</VCardTitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow>
          <VCol
            v-for="(d, i) in details"
            :key="i"
            cols="12"
            sm="6"
            md="4"
            lg="2"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar
                variant="tonal"
                color="primary"
                rounded
                size="38"
              >
                <VIcon
                  :icon="d.icon"
                  size="22"
                />
              </VAvatar>
              <div class="text-truncate">
                <div class="text-sm text-disabled">
                  {{ d.label }}
                </div>
                <div class="text-high-emphasis font-weight-medium text-truncate">
                  {{ d.value }}
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardItem>
        <VCardTitle>نباتاته ({{ plantsTotal }})</VCardTitle>
      </VCardItem>
      <VDivider />
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items="plants"
        item-value="id"
        :items-length="plantsTotal"
        :headers="headers"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <RouterLink
            :to="{ name: 'plants-id', params: { id: item.id } }"
            class="d-flex align-center gap-x-3 text-link font-weight-medium"
          >
            <VAvatar
              size="30"
              variant="tonal"
              :color="item.category?.color || 'primary'"
            >
              <VIcon
                :icon="item.category?.icon || 'tabler-plant'"
                size="18"
              />
            </VAvatar>
            {{ item.name }}
          </RouterLink>
        </template>

        <template #item.category="{ item }">
          {{ item.category?.name ?? '—' }}
        </template>

        <template #item.governorate="{ item }">
          {{ item.governorate?.name ?? '—' }}
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

        <template #item.age_label="{ item }">
          {{ item.age_label ?? '—' }}
        </template>

        <template #no-data>
          <div class="d-flex flex-column align-center text-center py-8">
            <VIcon
              icon="tabler-plant-off"
              size="40"
              class="text-disabled mb-2"
            />
            <span class="text-disabled">لا توجد نباتات مضافة لهذا المواطن.</span>
          </div>
        </template>

        <template #bottom>
          <TablePagination
            v-model:page="page"
            :items-per-page="itemsPerPage"
            :total-items="plantsTotal"
          />
        </template>
      </VDataTableServer>
    </VCard>

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
          <VCardTitle>حذف المواطن</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف <strong>{{ user.name }}</strong>؟
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

  <VCard
    v-else
    class="text-center py-12"
  >
    <VCardText>
      <VAvatar
        size="72"
        color="error"
        variant="tonal"
        rounded
      >
        <VIcon
          icon="tabler-user-off"
          size="40"
        />
      </VAvatar>
      <h5 class="text-h5 mt-4 mb-1">
        المواطن غير موجود
      </h5>
      <p class="text-body-1 text-disabled mb-4">
        تعذّر العثور على هذا المواطن، ربما تم حذفه.
      </p>
      <VBtn
        prepend-icon="tabler-arrow-right"
        @click="router.push({ name: 'citizens' })"
      >
        رجوع للمواطنين
      </VBtn>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.user-profile-cover {
  background: linear-gradient(135deg, rgba(var(--v-theme-info), 90%), rgba(var(--v-theme-info), 55%));
  block-size: 125px;
}

.user-profile-avatar {
  border: 5px solid rgb(var(--v-theme-surface));
  background-color: rgb(var(--v-theme-surface));
  margin-block-start: -62px;
}
</style>
