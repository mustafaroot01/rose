<script setup lang="ts">
// المدونة: منشورات (صورة + نص + رابط اختياري) تظهر في تبويب "المدونة" بالتطبيق.
interface Post {
  id: number
  title: string
  body: string | null
  image: string | null
  link_url: string | null
  link_label: string | null
  is_published: boolean
  published_at: string | null
  created_at: string | null
}

interface PostsResponse {
  data: Post[]
  total: number
  stats: { total: number, published: number, hidden: number }
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const search = ref('')
const status = ref<string | null>(null)
const page = ref(1)
const itemsPerPage = ref(10)

const { data, execute: fetchPosts } = await useApi<PostsResponse>(createUrl('/admin/posts', {
  query: { q: search, status, page, itemsPerPage },
}))

const posts = computed(() => data.value?.data ?? [])
const total = computed(() => data.value?.total ?? 0)

const widgets = computed(() => [
  { title: 'إجمالي المنشورات', value: data.value?.stats.total ?? 0, icon: 'tabler-news', color: 'primary' },
  { title: 'المنشورة', value: data.value?.stats.published ?? 0, icon: 'tabler-eye', color: 'success' },
  { title: 'المخفية', value: data.value?.stats.hidden ?? 0, icon: 'tabler-eye-off', color: 'secondary' },
])

const headers = [
  { title: 'الصورة', key: 'image', sortable: false, width: 110 },
  { title: 'العنوان', key: 'title', sortable: false },
  { title: 'الرابط', key: 'link_url', sortable: false },
  { title: 'تاريخ النشر', key: 'published_at', sortable: false, width: 150 },
  { title: 'الحالة', key: 'is_published', sortable: false, width: 110, align: 'center' as const },
  { title: 'إجراءات', key: 'actions', sortable: false, width: 120, align: 'center' as const },
]

const statusOptions = [
  { title: 'المنشورة', value: 'published' },
  { title: 'المخفية', value: 'hidden' },
]

const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString('ar-IQ', { year: 'numeric', month: 'short', day: 'numeric' }) : '—'

// ───────────────────────── النموذج (إضافة/تعديل) ─────────────────────────
const isDialogVisible = ref(false)
const editing = ref<Post | null>(null)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const form = ref({ title: '', body: '', link_url: '', link_label: '', is_published: true })
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const removeImage = ref(false)
const imageInput = ref<HTMLInputElement>()

const openCreate = () => {
  editing.value = null
  form.value = { title: '', body: '', link_url: '', link_label: '', is_published: true }
  imageFile.value = null
  imagePreview.value = null
  removeImage.value = false
  errors.value = {}
  isDialogVisible.value = true
}

const openEdit = (p: Post) => {
  editing.value = p
  form.value = {
    title: p.title,
    body: p.body ?? '',
    link_url: p.link_url ?? '',
    link_label: p.link_label ?? '',
    is_published: p.is_published,
  }
  imageFile.value = null
  imagePreview.value = p.image
  removeImage.value = false
  errors.value = {}
  isDialogVisible.value = true
}

const onImagePicked = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file)
    return
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
  removeImage.value = false
}

const clearImage = () => {
  imageFile.value = null
  imagePreview.value = null
  removeImage.value = true
}

const save = async () => {
  saving.value = true
  errors.value = {}
  try {
    const body = new FormData()
    body.append('title', form.value.title)
    body.append('body', form.value.body ?? '')
    body.append('link_url', form.value.link_url ?? '')
    body.append('link_label', form.value.link_label ?? '')
    body.append('is_published', form.value.is_published ? '1' : '0')
    if (imageFile.value)
      body.append('image', imageFile.value)
    else if (removeImage.value)
      body.append('remove_image', '1')

    const url = editing.value ? `/admin/posts/${editing.value.id}` : '/admin/posts'
    if (editing.value)
      body.append('_method', 'PUT')

    const res = await $api<{ message: string }>(url, { method: 'POST', body })
    notify(res.message)
    isDialogVisible.value = false
    fetchPosts()
  }
  catch (err: any) {
    errors.value = err?.data?.errors ?? {}
    if (!err?.data?.errors)
      notify(err?.data?.message ?? 'تعذّر الحفظ.', 'error')
  }
  finally {
    saving.value = false
  }
}

const togglePublished = async (p: Post) => {
  const body = new FormData()
  body.append('_method', 'PUT')
  body.append('is_published', p.is_published ? '0' : '1')
  const res = await $api<{ message: string }>(`/admin/posts/${p.id}`, { method: 'POST', body })
  notify(res.message)
  fetchPosts()
}

// ───────────────────────── الحذف ─────────────────────────
const deleteTarget = ref<Post | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/posts/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchPosts()
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
        <VCardTitle>المدونة</VCardTitle>
        <VCardSubtitle>منشورات تظهر في تبويب "المدونة" داخل التطبيق — صورة ونص ورابط اختياري يفتح خارج التطبيق</VCardSubtitle>
        <template #append>
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            منشور جديد
          </VBtn>
        </template>
      </VCardItem>

      <VCardText class="d-flex flex-wrap gap-4">
        <div style="inline-size: 18rem;">
          <AppTextField
            v-model="search"
            placeholder="ابحث بالعنوان"
            prepend-inner-icon="tabler-search"
            clearable
          />
        </div>
        <div style="inline-size: 12rem;">
          <AppSelect
            v-model="status"
            placeholder="كل الحالات"
            :items="statusOptions"
            clearable
          />
        </div>
      </VCardText>

      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="posts"
        :items-length="total"
        no-data-text="لا توجد منشورات بعد"
      >
        <template #item.image="{ item }">
          <VImg
            v-if="item.image"
            :src="item.image"
            width="88"
            height="56"
            cover
            class="rounded my-2"
          />
          <VAvatar
            v-else
            variant="tonal"
            color="secondary"
            rounded
            size="56"
            class="my-2"
          >
            <VIcon icon="tabler-photo-off" />
          </VAvatar>
        </template>

        <template #item.title="{ item }">
          <div class="font-weight-medium text-high-emphasis">
            {{ item.title }}
          </div>
          <div
            v-if="item.body"
            class="text-caption text-medium-emphasis text-truncate"
            style="max-inline-size: 22rem;"
          >
            {{ item.body }}
          </div>
        </template>

        <template #item.link_url="{ item }">
          <a
            v-if="item.link_url"
            :href="item.link_url"
            target="_blank"
            rel="noopener"
            class="text-truncate d-inline-block"
            style="max-inline-size: 14rem;"
            dir="ltr"
          >{{ item.link_label || item.link_url }}</a>
          <span
            v-else
            class="text-disabled"
          >—</span>
        </template>

        <template #item.published_at="{ item }">
          {{ formatDate(item.published_at) }}
        </template>

        <template #item.is_published="{ item }">
          <VSwitch
            :model-value="item.is_published"
            density="compact"
            hide-details
            class="d-inline-flex"
            @update:model-value="togglePublished(item)"
          />
        </template>

        <template #item.actions="{ item }">
          <IconBtn @click="openEdit(item)">
            <VIcon icon="tabler-pencil" />
          </IconBtn>
          <IconBtn @click="deleteTarget = item; isDeleteVisible = true">
            <VIcon
              icon="tabler-trash"
              color="error"
            />
          </IconBtn>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- نموذج المنشور -->
    <VDialog
      v-model="isDialogVisible"
      max-width="640"
    >
      <VCard>
        <VCardItem>
          <VCardTitle>{{ editing ? 'تعديل المنشور' : 'منشور جديد' }}</VCardTitle>
        </VCardItem>
        <VDivider />
        <VCardText>
          <!-- الصورة -->
          <div
            class="post-image mb-4"
            @click="imageInput?.click()"
          >
            <VImg
              v-if="imagePreview"
              :src="imagePreview"
              :aspect-ratio="16 / 9"
              cover
              rounded="lg"
            />
            <div
              v-else
              class="post-image-placeholder"
            >
              <VIcon
                icon="tabler-photo-plus"
                size="32"
              />
              <span class="text-caption mt-1">اضغط لإضافة صورة (اختياري)</span>
            </div>
            <input
              ref="imageInput"
              type="file"
              accept="image/*"
              hidden
              @change="onImagePicked"
            >
          </div>
          <div
            v-if="imagePreview"
            class="d-flex justify-end mb-2"
          >
            <VBtn
              size="small"
              variant="text"
              color="error"
              prepend-icon="tabler-trash"
              @click="clearImage"
            >
              حذف الصورة
            </VBtn>
          </div>
          <div
            v-if="errors.image"
            class="text-error text-caption mb-2"
          >
            {{ errors.image[0] }}
          </div>

          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                label="العنوان"
                :error-messages="errors.title"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.body"
                label="النص"
                rows="5"
                auto-grow
                :error-messages="errors.body"
              />
            </VCol>
            <VCol
              cols="12"
              sm="7"
            >
              <AppTextField
                v-model="form.link_url"
                label="رابط خارجي (اختياري)"
                placeholder="https://"
                dir="ltr"
                :error-messages="errors.link_url"
              />
            </VCol>
            <VCol
              cols="12"
              sm="5"
            >
              <AppTextField
                v-model="form.link_label"
                label="نص زر الرابط"
                placeholder="اقرأ المزيد"
                :error-messages="errors.link_label"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_published"
                label="منشور (ظاهر في التطبيق)"
                hide-details
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 pt-0">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogVisible = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="save"
          >
            {{ editing ? 'حفظ' : 'نشر' }}
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
          <VCardTitle>حذف المنشور</VCardTitle>
        </VCardItem>
        <VCardText>
          سيُحذف المنشور «{{ deleteTarget?.title }}» نهائياً من التطبيق. لا يمكن التراجع عن هذا.
        </VCardText>
        <VCardText class="d-flex justify-end gap-3">
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
    >
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>

<style lang="scss" scoped>
.post-image {
  cursor: pointer;
}

.post-image-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  block-size: 160px;
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
}
</style>
