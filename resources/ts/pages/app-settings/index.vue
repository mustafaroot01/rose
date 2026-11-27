<script setup lang="ts">
// إعدادات التطبيق: روابط التواصل الاجتماعي (تظهر في إعدادات التطبيق) + قفل التحديث الإجباري.
interface AppSettings {
  social_instagram: string
  social_facebook: string
  social_whatsapp: string
  social_tiktok: string
  force_update: boolean | string
  min_version_android: string
  min_version_ios: string
  update_message: string
  android_url: string
  ios_url: string
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const form = ref({
  social_instagram: '',
  social_facebook: '',
  social_whatsapp: '',
  social_tiktok: '',
  force_update: false,
  min_version_android: '',
  min_version_ios: '',
  update_message: '',
  android_url: '',
  ios_url: '',
})

const errors = ref<Record<string, string[]>>({})
const saving = ref(false)

const toBool = (v: unknown) => v === true || v === 1 || v === '1' || v === 'true'

const { data } = await useApi<{ data: AppSettings }>('/admin/app-settings')

const fill = (s?: AppSettings) => {
  if (!s)
    return
  form.value = {
    social_instagram: s.social_instagram ?? '',
    social_facebook: s.social_facebook ?? '',
    social_whatsapp: s.social_whatsapp ?? '',
    social_tiktok: s.social_tiktok ?? '',
    force_update: toBool(s.force_update),
    min_version_android: s.min_version_android ?? '',
    min_version_ios: s.min_version_ios ?? '',
    update_message: s.update_message ?? '',
    android_url: s.android_url ?? '',
    ios_url: s.ios_url ?? '',
  }
}

watch(() => data.value?.data, fill, { immediate: true })

const socials = [
  { key: 'social_instagram', label: 'إنستغرام', icon: 'tabler-brand-instagram', color: '#E1306C', placeholder: 'https://instagram.com/...' },
  { key: 'social_facebook', label: 'فيسبوك', icon: 'tabler-brand-facebook', color: '#1877F2', placeholder: 'https://facebook.com/...' },
  { key: 'social_whatsapp', label: 'واتساب', icon: 'tabler-brand-whatsapp', color: '#25D366', placeholder: '9647XXXXXXXXX أو https://wa.me/...' },
  { key: 'social_tiktok', label: 'تيك توك', icon: 'tabler-brand-tiktok', color: '#111111', placeholder: 'https://tiktok.com/@...' },
] as const

// كل منصة: أقل إصدار + رابط متجرها
const platforms = [
  { name: 'أندرويد', icon: 'tabler-brand-android', color: '#3DDC84', version: 'min_version_android', store: 'android_url', storeLabel: 'رابط Google Play', storeIcon: 'tabler-brand-google-play', storePlaceholder: 'https://play.google.com/store/apps/details?id=...' },
  { name: 'آيفون', icon: 'tabler-brand-apple', color: '#8E8E93', version: 'min_version_ios', store: 'ios_url', storeLabel: 'رابط App Store', storeIcon: 'tabler-brand-apple', storePlaceholder: 'https://apps.apple.com/app/...' },
] as const

const save = async () => {
  saving.value = true
  errors.value = {}
  try {
    const res = await $api<{ message: string, data: AppSettings }>('/admin/app-settings', {
      method: 'PUT',
      body: { ...form.value },
    })
    fill(res.data)
    notify(res.message)
  }
  catch (err: any) {
    errors.value = err?.data?.errors ?? {}
    notify(err?.data?.message ?? 'تعذّر الحفظ.', 'error')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <section>
    <VRow>
      <!-- روابط التواصل -->
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>روابط التواصل الاجتماعي</VCardTitle>
            <VCardSubtitle>تظهر في صفحة الإعدادات داخل التطبيق. اترك الحقل فارغاً لإخفائه.</VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-4">
            <div
              v-for="s in socials"
              :key="s.key"
              class="d-flex align-center gap-3"
            >
              <VAvatar
                rounded
                size="42"
                :style="{ backgroundColor: `${s.color}1A` }"
              >
                <VIcon
                  :icon="s.icon"
                  :color="s.color"
                  size="24"
                />
              </VAvatar>
              <AppTextField
                v-model="form[s.key]"
                :label="s.label"
                :placeholder="s.placeholder"
                dir="ltr"
                clearable
                class="flex-grow-1"
                :error-messages="errors[s.key]"
              />
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- قفل التحديث -->
      <VCol
        cols="12"
        md="6"
      >
        <VCard
          class="h-100"
          :class="{ 'border-error': form.force_update }"
          :variant="form.force_update ? 'outlined' : 'elevated'"
        >
          <VCardItem>
            <template #prepend>
              <VAvatar
                :color="form.force_update ? 'error' : 'secondary'"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  :icon="form.force_update ? 'tabler-lock' : 'tabler-lock-open'"
                  size="24"
                />
              </VAvatar>
            </template>
            <VCardTitle>قفل التحديث</VCardTitle>
            <VCardSubtitle>عند التفعيل، أي نسخة أقدم من الإصدار المحدّد تُقفل وتطلب من المستخدم التحديث من المتجر.</VCardSubtitle>
          </VCardItem>
          <VCardText class="d-flex flex-column gap-4">
            <VSwitch
              v-model="form.force_update"
              color="error"
              :label="form.force_update ? 'القفل مفعّل — النسخ القديمة لن تعمل' : 'القفل متوقف'"
              hide-details
            />
            <AppTextarea
              v-model="form.update_message"
              label="رسالة التحديث"
              placeholder="يتوفر إصدار جديد من التطبيق، حدّثه للمتابعة."
              rows="2"
              auto-grow
              :error-messages="errors.update_message"
            />

            <!-- لكل منصة أقل إصدار ورابط متجرها -->
            <div
              v-for="p in platforms"
              :key="p.version"
              class="platform-box"
            >
              <div class="d-flex align-center gap-2 mb-3">
                <VIcon
                  :icon="p.icon"
                  :color="p.color"
                  size="22"
                />
                <span class="text-body-1 font-weight-medium text-high-emphasis">{{ p.name }}</span>
                <VChip
                  v-if="form.force_update && form[p.version]"
                  size="x-small"
                  color="error"
                  variant="tonal"
                  class="ms-auto"
                >
                  مقفل لأقدم من {{ form[p.version] }}
                </VChip>
                <VChip
                  v-else
                  size="x-small"
                  variant="tonal"
                  class="ms-auto"
                >
                  بدون قفل
                </VChip>
              </div>
              <VRow>
                <VCol
                  cols="12"
                  sm="4"
                >
                  <AppTextField
                    v-model="form[p.version]"
                    label="أقل إصدار مسموح"
                    placeholder="1.0.1"
                    dir="ltr"
                    clearable
                    :error-messages="errors[p.version]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="8"
                >
                  <AppTextField
                    v-model="form[p.store]"
                    :label="p.storeLabel"
                    :placeholder="p.storePlaceholder"
                    dir="ltr"
                    :prepend-inner-icon="p.storeIcon"
                    :error-messages="errors[p.store]"
                  />
                </VCol>
              </VRow>
            </div>
            <div class="text-caption text-medium-emphasis">
              اترك أقل إصدار فارغاً لمنصة ما حتى لا تُقفل. رقم إصدار التطبيق الحالي يظهر بأسفل صفحة الإعدادات في التطبيق (مثل 1.0.0).
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div class="d-flex justify-end mt-6">
      <VBtn
        color="primary"
        prepend-icon="tabler-device-floppy"
        :loading="saving"
        @click="save"
      >
        حفظ الإعدادات
      </VBtn>
    </div>

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
.platform-box {
  padding: 14px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
