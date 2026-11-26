<script setup lang="ts">
const tab = ref('account')

const userData = useCookie<Record<string, any> | null>('userData')
const user = ref({ ...userData.value })

const saving = ref(false)
const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const avatarFile = ref<File | null>(null)
const avatarPreview = ref<string | null>(null)

const onAvatarChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]

  if (file) {
    avatarFile.value = file
    avatarPreview.value = URL.createObjectURL(file)
  }
}

const saveProfile = async () => {
  saving.value = true
  try {
    const formData = new FormData()

    if (user.value.name)
      formData.append('name', user.value.name)
    if (user.value.email)
      formData.append('email', user.value.email)
    if (avatarFile.value)
      formData.append('avatar', avatarFile.value)

    const res = await $api<{ message: string; data: any }>('/profile', { method: 'POST', body: formData })

    notify(res.message)

    if (res.data) {
      userData.value = { ...userData.value, ...res.data }
      user.value = { ...res.data }
    }
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر الحفظ.', 'error')
  }
  finally {
    saving.value = false
  }
}

// تغيير كلمة المرور
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' })
const savingPassword = ref(false)

const savePassword = async () => {
  savingPassword.value = true
  try {
    const res = await $api<{ message: string }>('/profile/password', { method: 'PUT', body: passwordForm.value })

    notify(res.message)
    passwordForm.value = { current_password: '', password: '', password_confirmation: '' }
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر تغيير كلمة المرور.', 'error')
  }
  finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <section>
    <h4 class="text-h4 mb-6">
      الإعدادات
    </h4>

    <VTabs v-model="tab" class="mb-6">
      <VTab value="account">
        <VIcon start icon="tabler-user" />
        الحساب
      </VTab>
      <VTab value="security">
        <VIcon start icon="tabler-lock" />
        الأمان
      </VTab>
    </VTabs>

    <VWindow v-model="tab">
      <VWindowItem value="account">
        <VCard>
          <VCardItem>
            <VCardTitle>المعلومات الشخصية</VCardTitle>
          </VCardItem>
          <VCardText>
            <VRow>
              <VCol cols="12" class="d-flex align-center gap-4">
                <VAvatar
                  size="80"
                  :image="avatarPreview ?? user.avatar"
                  color="primary"
                  variant="tonal"
                >
                  <VIcon v-if="!avatarPreview && !user.avatar" icon="tabler-user" size="40" />
                </VAvatar>
                <div>
                  <VBtn
                    variant="tonal"
                    size="small"
                    tag="label"
                    class="cursor-pointer"
                  >
                    تغيير الصورة
                    <input
                      type="file"
                      accept="image/*"
                      hidden
                      @change="onAvatarChange"
                    >
                  </VBtn>
                </div>
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="user.name"
                  label="الاسم"
                  prepend-inner-icon="tabler-user"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="user.email"
                  label="البريد الإلكتروني"
                  type="email"
                  prepend-inner-icon="tabler-mail"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="user.phone"
                  label="رقم الهاتف"
                  prepend-inner-icon="tabler-phone"
                  disabled
                  hint="لا يمكن تغيير رقم الهاتف"
                  persistent-hint
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="user.role"
                  label="الدور"
                  prepend-inner-icon="tabler-shield"
                  disabled
                />
              </VCol>
              <VCol cols="12" class="d-flex justify-end">
                <VBtn
                  color="primary"
                  :loading="saving"
                  @click="saveProfile"
                >
                  حفظ التغييرات
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>

      <VWindowItem value="security">
        <VCard>
          <VCardItem>
            <VCardTitle>تغيير كلمة المرور</VCardTitle>
          </VCardItem>
          <VCardText>
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="passwordForm.current_password"
                  label="كلمة المرور الحالية"
                  type="password"
                  prepend-inner-icon="tabler-lock"
                />
              </VCol>
              <VCol cols="12" md="6" />
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="passwordForm.password"
                  label="كلمة المرور الجديدة"
                  type="password"
                  prepend-inner-icon="tabler-key"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="passwordForm.password_confirmation"
                  label="تأكيد كلمة المرور"
                  type="password"
                  prepend-inner-icon="tabler-key"
                />
              </VCol>
              <VCol cols="12" class="d-flex justify-end">
                <VBtn
                  color="primary"
                  :loading="savingPassword"
                  @click="savePassword"
                >
                  تغيير كلمة المرور
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VWindowItem>
    </VWindow>

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
