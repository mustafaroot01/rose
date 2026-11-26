<script setup lang="ts">
interface Section { title: string; body: string }
interface AppContent {
  id: number
  key: string
  title: string
  sections: Section[]
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const loading = ref(true)
const saving = ref<string | null>(null)
const contents = ref<AppContent[]>([])
const tab = ref('privacy_policy')

const meta: Record<string, { label: string; icon: string; color: string }> = {
  privacy_policy: { label: 'سياسة الخصوصية', icon: 'tabler-shield-lock', color: 'primary' },
  about: { label: 'عن التطبيق', icon: 'tabler-info-circle', color: 'info' },
}

const load = async () => {
  loading.value = true
  try {
    const res = await $api<{ data: AppContent[] }>('/admin/contents')
    contents.value = res.data.map(c => ({ ...c, sections: Array.isArray(c.sections) ? c.sections : [] }))
    if (contents.value.length)
      tab.value = contents.value[0].key
  } catch {
    notify('تعذّر تحميل المحتوى', 'error')
  } finally {
    loading.value = false
  }
}

const current = computed(() => contents.value.find(c => c.key === tab.value))

const addSection = (c: AppContent) => c.sections.push({ title: '', body: '' })
const removeSection = (c: AppContent, i: number) => c.sections.splice(i, 1)
const moveSection = (c: AppContent, i: number, dir: number) => {
  const j = i + dir
  if (j < 0 || j >= c.sections.length) return
  const [s] = c.sections.splice(i, 1)
  c.sections.splice(j, 0, s)
}

const save = async (c: AppContent) => {
  if (!c.title.trim()) { notify('العنوان مطلوب', 'error'); return }
  if (!c.sections.length) { notify('أضف قسماً واحداً على الأقل', 'error'); return }
  for (const s of c.sections) {
    if (!s.title.trim() || !s.body.trim()) { notify('كل قسم يحتاج عنواناً ونصاً', 'error'); return }
  }
  saving.value = c.key
  try {
    const res = await $api<{ message: string }>(`/admin/contents/${c.key}`, {
      method: 'PUT',
      body: { title: c.title, sections: c.sections },
    })
    notify(res.message)
  } catch (e: any) {
    notify(e?.data?.message || 'تعذّر الحفظ', 'error')
  } finally {
    saving.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">
          محتوى التطبيق
        </h4>
        <span class="text-body-1 text-medium-emphasis">تعديل سياسة الخصوصية و«عن التطبيق» التي تظهر للمستخدمين داخل التطبيق</span>
      </div>
      <VBtn variant="tonal" prepend-icon="tabler-refresh" :loading="loading" @click="load">
        تحديث
      </VBtn>
    </div>

    <VCard v-if="loading" class="pa-12 text-center">
      <VProgressCircular indeterminate color="primary" />
    </VCard>

    <VCard v-else>
      <VTabs v-model="tab" grow>
        <VTab v-for="c in contents" :key="c.key" :value="c.key">
          <VIcon :icon="meta[c.key]?.icon ?? 'tabler-file'" start />
          {{ meta[c.key]?.label ?? c.title }}
        </VTab>
      </VTabs>
      <VDivider />

      <VWindow v-model="tab" class="pa-6">
        <VWindowItem v-for="c in contents" :key="c.key" :value="c.key">
          <VTextField
            v-model="c.title"
            label="عنوان الصفحة"
            variant="outlined"
            :prepend-inner-icon="meta[c.key]?.icon ?? 'tabler-file'"
            class="mb-6"
          />

          <div class="d-flex align-center justify-space-between mb-3">
            <span class="text-subtitle-1 font-weight-medium">الأقسام ({{ c.sections.length }})</span>
            <VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="addSection(c)">
              إضافة قسم
            </VBtn>
          </div>

          <VCard
            v-for="(s, i) in c.sections"
            :key="i"
            variant="outlined"
            class="mb-4"
          >
            <VCardText>
              <div class="d-flex align-center gap-2 mb-3">
                <VChip size="small" color="primary" label>
                  #{{ i + 1 }}
                </VChip>
                <VSpacer />
                <VBtn icon size="x-small" variant="text" :disabled="i === 0" @click="moveSection(c, i, -1)">
                  <VIcon icon="tabler-arrow-up" />
                </VBtn>
                <VBtn icon size="x-small" variant="text" :disabled="i === c.sections.length - 1" @click="moveSection(c, i, 1)">
                  <VIcon icon="tabler-arrow-down" />
                </VBtn>
                <VBtn icon size="x-small" variant="text" color="error" @click="removeSection(c, i)">
                  <VIcon icon="tabler-trash" />
                </VBtn>
              </div>
              <VTextField
                v-model="s.title"
                label="عنوان القسم"
                variant="outlined"
                density="comfortable"
                class="mb-3"
              />
              <VTextarea
                v-model="s.body"
                label="نص القسم"
                variant="outlined"
                rows="3"
                auto-grow
              />
            </VCardText>
          </VCard>

          <VAlert v-if="!c.sections.length" type="info" variant="tonal" class="mb-4">
            لا توجد أقسام — أضف قسماً للبدء.
          </VAlert>

          <div class="d-flex justify-end mt-4">
            <VBtn
              color="primary"
              prepend-icon="tabler-device-floppy"
              :loading="saving === c.key"
              @click="save(c)"
            >
              حفظ التغييرات
            </VBtn>
          </div>
        </VWindowItem>
      </VWindow>
    </VCard>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top">
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>
