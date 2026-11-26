<script setup lang="ts">
interface Banner {
  id: number
  url: string
  title: string | null
  link: string | null
  is_active: boolean
  sort_order: number
  created_at?: string
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const { data, execute: fetchBanners } = await useApi<{ data: Banner[] }>('/admin/banners')
const banners = computed<Banner[]>(() => data.value?.data ?? [])

const widgets = computed(() => [
  { title: 'إجمالي البنرات', value: banners.value.length, icon: 'tabler-slideshow', color: 'primary' },
  { title: 'الظاهرة', value: banners.value.filter(b => b.is_active).length, icon: 'tabler-eye', color: 'success' },
  { title: 'المخفية', value: banners.value.filter(b => !b.is_active).length, icon: 'tabler-eye-off', color: 'secondary' },
])

const headers = [
  { title: 'الترتيب', key: 'sort_order', width: 90, align: 'center' as const },
  { title: 'المعاينة', key: 'url', sortable: false, width: 130 },
  { title: 'العنوان', key: 'title', sortable: false },
  { title: 'الرابط', key: 'link', sortable: false },
  { title: 'الحالة', key: 'is_active', width: 120, align: 'center' as const },
  { title: 'إجراءات', key: 'actions', sortable: false, width: 120, align: 'center' as const },
]

// ───────────────────────── الرفع ─────────────────────────
const fileInput = ref<HTMLInputElement>()
const uploading = ref(false)
const pick = () => fileInput.value?.click()

const onPicked = async (e: Event) => {
  const files = (e.target as HTMLInputElement).files
  if (!files?.length)
    return

  const form = new FormData()
  for (const f of Array.from(files).slice(0, 10))
    form.append('images[]', f)

  uploading.value = true
  try {
    const res = await $api<{ message: string }>('/admin/banners', { method: 'POST', body: form })
    notify(res.message)
    fetchBanners()
  }
  catch (err: any) {
    notify(err?.data?.message ?? 'تعذّر رفع البنرات.', 'error')
  }
  finally {
    uploading.value = false
    if (fileInput.value)
      fileInput.value.value = ''
  }
}

const toggle = async (b: Banner) => {
  const res = await $api<{ message: string }>(`/admin/banners/${b.id}/toggle`, { method: 'PATCH' })
  notify(res.message)
  fetchBanners()
}

// ───────────────────────── المعاينة / الحذف ─────────────────────────
const previewUrl = ref<string | null>(null)

const deleteTarget = ref<Banner | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/banners/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchBanners()
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <section>
    <!-- بطاقات الإحصائيات -->
    <VRow class="mb-6">
      <VCol
        v-for="(w, i) in widgets"
        :key="i"
        cols="12"
        sm="4"
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
        <VCardTitle>بنرات الصفحة الرئيسية</VCardTitle>
        <VCardSubtitle>الصور التي تظهر كشرائح متحركة أعلى الصفحة الرئيسية في التطبيق</VCardSubtitle>
        <template #append>
          <VBtn
            color="primary"
            prepend-icon="tabler-photo-plus"
            :loading="uploading"
            @click="pick"
          >
            إضافة بنرات
          </VBtn>
          <input
            ref="fileInput"
            type="file"
            accept="image/*"
            multiple
            hidden
            @change="onPicked"
          >
        </template>
      </VCardItem>

      <VDivider />

      <VDataTable
        :items="banners"
        :headers="headers"
        item-value="id"
        :items-per-page="10"
        class="text-no-wrap"
      >
        <!-- الترتيب -->
        <template #item.sort_order="{ item }">
          <VChip
            size="small"
            variant="tonal"
            color="primary"
          >
            {{ item.sort_order }}
          </VChip>
        </template>

        <!-- المعاينة -->
        <template #item.url="{ item }">
          <VImg
            :src="item.url"
            width="104"
            height="58"
            cover
            rounded="lg"
            class="my-2 cursor-pointer banner-thumb"
            @click="previewUrl = item.url"
          />
        </template>

        <!-- العنوان -->
        <template #item.title="{ item }">
          <span v-if="item.title">{{ item.title }}</span>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- الرابط -->
        <template #item.link="{ item }">
          <a
            v-if="item.link"
            :href="item.link"
            target="_blank"
            class="text-primary text-truncate d-inline-block"
            style="max-inline-size: 180px;"
          >{{ item.link }}</a>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <!-- الحالة -->
        <template #item.is_active="{ item }">
          <VChip
            size="small"
            variant="tonal"
            :color="item.is_active ? 'success' : 'secondary'"
          >
            <VIcon
              start
              :icon="item.is_active ? 'tabler-eye' : 'tabler-eye-off'"
              size="14"
            />
            {{ item.is_active ? 'ظاهر' : 'مخفي' }}
          </VChip>
        </template>

        <!-- إجراءات -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <IconBtn
              size="small"
              @click="toggle(item)"
            >
              <VIcon :icon="item.is_active ? 'tabler-eye-off' : 'tabler-eye'" />
              <VTooltip activator="parent">
                {{ item.is_active ? 'إخفاء' : 'إظهار' }}
              </VTooltip>
            </IconBtn>
            <IconBtn
              size="small"
              color="error"
              @click="deleteTarget = item; isDeleteVisible = true"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">
                حذف
              </VTooltip>
            </IconBtn>
          </div>
        </template>

        <!-- لا توجد بيانات -->
        <template #no-data>
          <div class="d-flex flex-column align-center text-center py-12">
            <VIcon
              icon="tabler-photo-off"
              size="56"
              class="text-disabled mb-3"
            />
            <div class="text-body-1 mb-1">
              لا توجد بنرات بعد
            </div>
            <div class="text-caption text-disabled mb-4">
              أضف صورًا لتظهر كسلايدر أعلى الصفحة الرئيسية في التطبيق
            </div>
            <VBtn
              color="primary"
              variant="tonal"
              prepend-icon="tabler-photo-plus"
              @click="pick"
            >
              إضافة أول بنر
            </VBtn>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- معاينة مكبّرة -->
    <VDialog
      :model-value="!!previewUrl"
      max-width="760"
      @update:model-value="previewUrl = null"
    >
      <VCard>
        <VImg :src="previewUrl || ''" />
        <VCardText class="d-flex justify-end">
          <VBtn
            variant="tonal"
            @click="previewUrl = null"
          >
            إغلاق
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <!-- تأكيد الحذف -->
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
          <VCardTitle>حذف البنر</VCardTitle>
        </VCardItem>
        <VCardText>هل أنت متأكد من حذف هذا البنر؟</VCardText>
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

<style lang="scss" scoped>
.banner-thumb {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  transition: transform .15s ease;

  &:hover {
    transform: scale(1.04);
  }
}

:deep(.v-data-table__td) {
  block-size: 76px;
}
</style>
