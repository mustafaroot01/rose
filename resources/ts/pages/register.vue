<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

interface AccountTypeOption { id: number, name: string, icon: string | null }

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const router = useRouter()
const ability = useAbility()

const themeSwitcherThemes = [
  { name: 'light', icon: 'tabler-sun-high' },
  { name: 'dark', icon: 'tabler-moon-stars' },
  { name: 'system', icon: 'tabler-device-desktop-analytics' },
]

// 1 = بيانات التسجيل | 2 = تأكيد رمز واتساب
const step = ref(1)
const loading = ref(false)
const isPasswordVisible = ref(false)

const refStep1 = ref<VForm>()
const refStep2 = ref<VForm>()

const form = ref({
  name: '',
  phone: '',
  password: '',
  account_type_id: null as number | null,
  governorate_id: null as number | null,
  district_id: null as number | null,
  code: '',
})

const errors = ref<Record<string, string | undefined>>({})

// 👉 قوائم عامّة (أنواع الحسابات + المحافظات + الأقضية)
const { data: typesData } = await useApi<AccountTypeOption[]>('/account-types/active')
const { data: govData } = await useApi<{ id: number, name: string }[]>('/governorates/active')
const { data: distData } = await useApi<{ id: number, name: string, governorate_id: number }[]>('/districts/active')

const accountTypeItems = computed(() =>
  (typesData.value ?? []).map(t => ({ title: t.name, value: t.id })))

const governorateItems = computed(() =>
  (govData.value ?? []).map(g => ({ title: g.name, value: g.id })))

const districtItems = computed(() =>
  (distData.value ?? [])
    .filter(d => !form.value.governorate_id || d.governorate_id === form.value.governorate_id)
    .map(d => ({ title: d.name, value: d.id })))

// إعادة ضبط القضاء عند تغيير المحافظة
watch(() => form.value.governorate_id, () => { form.value.district_id = null })

// أرقام فقط + رقم عراقي
const toLatinDigits = (s: string) =>
  s.replace(/[٠-٩]/g, d => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/[۰-۹]/g, d => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))

watch(() => form.value.phone, val => {
  let digits = toLatinDigits(val).replace(/\D/g, '')
  const maxLen = digits.startsWith('0') ? 11 : 10
  if (digits.length > maxLen)
    digits = digits.slice(0, maxLen)
  if (digits !== val)
    form.value.phone = digits
})

const iraqiPhoneValidator = (v: string) =>
  /^0?7\d{9}$/.test(v || '') || 'أدخل رقم عراقي صحيح (مثال: 07701234567)'

const blockNonDigit = (e: KeyboardEvent) => {
  if (e.key.length === 1 && !/[0-9]/.test(e.key))
    e.preventDefault()
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

// الخطوة 1: إنشاء الحساب وإرسال OTP
const registerUser = async () => {
  loading.value = true
  errors.value = {}
  try {
    await $api('/auth/register', {
      method: 'POST',
      body: {
        name: form.value.name,
        phone: form.value.phone,
        password: form.value.password,
        account_type_id: form.value.account_type_id,
        governorate_id: form.value.governorate_id,
        district_id: form.value.district_id,
      },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
      },
    })
    notify('تم إرسال رمز التحقق إلى واتساب 📲')
    step.value = 2
  }
  catch (err) {
    console.error(err)
  }
  finally {
    loading.value = false
  }
}

// الخطوة 2: التحقق من الرمز وتفعيل الحساب
const verifyOtp = async () => {
  loading.value = true
  errors.value = {}
  try {
    const res = await $api<any>('/auth/verify-otp', {
      method: 'POST',
      body: { phone: form.value.phone, code: form.value.code },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
      },
    })

    const { accessToken, userData, userAbilityRules } = res

    useCookie('userAbilityRules').value = userAbilityRules
    ability.update(userAbilityRules)
    useCookie('userData').value = userData
    useCookie('accessToken').value = accessToken

    await nextTick(() => router.replace('/'))
  }
  catch (err) {
    console.error(err)
  }
  finally {
    loading.value = false
  }
}

const onSubmitStep1 = () => {
  refStep1.value?.validate().then(({ valid }) => {
    if (valid)
      registerUser()
  })
}

const onSubmitStep2 = () => {
  refStep2.value?.validate().then(({ valid }) => {
    if (valid)
      verifyOtp()
  })
}
</script>

<template>
  <div class="login-page">
    <div class="login-bg">
      <div class="login-bg__grid" />
      <span class="login-bg__glow login-bg__glow--1" />
      <span class="login-bg__glow login-bg__glow--2" />
      <VIcon
        icon="tabler-map-pin-filled"
        class="login-bg__pin login-bg__pin--1"
      />
      <VIcon
        icon="tabler-plant-2"
        class="login-bg__pin login-bg__pin--3"
      />
    </div>

    <RouterLink
      to="/"
      class="login-brand d-flex align-center gap-x-2 text-decoration-none"
    >
      <div class="login-brand__mark">
        <VNodeRenderer :nodes="themeConfig.app.logo" />
      </div>
      <span class="login-brand__title">{{ themeConfig.app.title }}</span>
    </RouterLink>

    <ThemeSwitcher
      :themes="themeSwitcherThemes"
      class="login-theme-toggle"
    />

    <div class="login-card">
      <div class="login-card__head">
        <div class="login-card__badge">
          <VIcon
            :icon="step === 1 ? 'tabler-user-plus' : 'tabler-brand-whatsapp'"
            size="28"
          />
        </div>
        <h4 class="login-card__title">
          {{ step === 1 ? 'إنشاء حساب جديد' : 'تأكيد رقم الجوال' }}
        </h4>
        <p class="login-card__subtitle">
          {{ step === 1 ? 'سجّل بياناتك للانضمام إلى المنصة' : 'أدخل الرمز الذي وصلك على واتساب' }}
        </p>
      </div>

      <!-- الخطوة 1 -->
      <VForm
        v-if="step === 1"
        ref="refStep1"
        class="login-form"
        @submit.prevent="onSubmitStep1"
      >
        <div class="login-field">
          <label class="login-field__label">الاسم الكامل</label>
          <AppTextField
            v-model="form.name"
            autofocus
            placeholder="أدخل اسمك"
            prepend-inner-icon="tabler-user"
            :rules="[requiredValidator]"
            :error-messages="errors.name"
          />
        </div>

        <div class="login-field">
          <label class="login-field__label">رقم الجوال</label>
          <AppTextField
            v-model="form.phone"
            type="tel"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="11"
            dir="ltr"
            name="iraqi-phone"
            autocomplete="off"
            placeholder="07701234567"
            prepend-inner-icon="tabler-phone"
            :rules="[requiredValidator, iraqiPhoneValidator]"
            :error-messages="errors.phone"
            @keypress="blockNonDigit"
          />
        </div>

        <div class="login-field">
          <label class="login-field__label">نوع الحساب</label>
          <AppSelect
            v-model="form.account_type_id"
            placeholder="اختر نوع الحساب"
            prepend-inner-icon="tabler-category"
            :items="accountTypeItems"
            :rules="[requiredValidator]"
            :error-messages="errors.account_type_id"
          />
        </div>

        <div class="login-field">
          <label class="login-field__label">المحافظة</label>
          <AppSelect
            v-model="form.governorate_id"
            placeholder="اختر المحافظة"
            prepend-inner-icon="tabler-building-estate"
            :items="governorateItems"
            :rules="[requiredValidator]"
            :error-messages="errors.governorate_id"
          />
        </div>

        <div class="login-field">
          <label class="login-field__label">القضاء</label>
          <AppSelect
            v-model="form.district_id"
            placeholder="اختر القضاء"
            prepend-inner-icon="tabler-map-pin"
            :items="districtItems"
            :rules="[requiredValidator]"
            :error-messages="errors.district_id"
          />
        </div>

        <div class="login-field">
          <label class="login-field__label">كلمة السر</label>
          <AppTextField
            v-model="form.password"
            placeholder="أدخل كلمة السر"
            autocomplete="new-password"
            prepend-inner-icon="tabler-lock"
            :type="isPasswordVisible ? 'text' : 'password'"
            :rules="[requiredValidator]"
            :error-messages="errors.password"
            :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
            @click:append-inner="isPasswordVisible = !isPasswordVisible"
          />
        </div>

        <VBtn
          block
          size="large"
          type="submit"
          class="login-form__submit"
          :loading="loading"
        >
          إنشاء الحساب
          <VIcon
            end
            icon="tabler-arrow-left"
            class="flip-in-rtl"
          />
        </VBtn>

        <RouterLink
          class="login-form__link"
          :to="{ name: 'login' }"
        >
          <VIcon
            icon="tabler-chevron-right"
            size="18"
            class="flip-in-rtl"
          />
          <span>عندك حساب؟ سجّل الدخول</span>
        </RouterLink>
      </VForm>

      <!-- الخطوة 2 -->
      <VForm
        v-else
        ref="refStep2"
        class="login-form"
        @submit.prevent="onSubmitStep2"
      >
        <div class="login-field">
          <label class="login-field__label text-center">رمز التحقق (4 أرقام)</label>
          <VOtpInput
            v-model="form.code"
            :length="4"
            type="number"
            dir="ltr"
            class="otp-field pa-0"
          />
          <p
            v-if="errors.code"
            class="text-error text-center text-sm mt-2 mb-0"
          >
            {{ errors.code }}
          </p>
        </div>

        <VBtn
          block
          size="large"
          type="submit"
          class="login-form__submit"
          :loading="loading"
        >
          تأكيد وتفعيل الحساب
          <VIcon
            end
            icon="tabler-check"
          />
        </VBtn>

        <a
          class="login-form__link"
          href="#"
          @click.prevent="step = 1"
        >
          <VIcon
            icon="tabler-chevron-right"
            size="18"
            class="flip-in-rtl"
          />
          <span>تعديل البيانات</span>
        </a>
      </VForm>
    </div>
  </div>

  <VSnackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    location="top"
    :timeout="3000"
  >
    {{ snackbar.text }}
  </VSnackbar>
</template>

<style lang="scss">
.login-page {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 100dvh;
  padding: 1.5rem;
  overflow: hidden;
}

.login-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background: linear-gradient(135deg, #10b981 0%, rgb(var(--v-theme-primary)) 38%, #047857 72%, #064e3b 100%);

  &__grid {
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(255, 255, 255, 7%) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 7%) 1px, transparent 1px);
    background-size: 46px 46px;
    mask-image: radial-gradient(ellipse at center, #000 35%, transparent 78%);
  }

  &__glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);

    &--1 { inset-block-start: -120px; inset-inline-end: -80px; inline-size: 420px; block-size: 420px; background: rgba(255, 255, 255, 22%); }
    &--2 { inset-block-end: -160px; inset-inline-start: -120px; inline-size: 460px; block-size: 460px; background: rgba(16, 185, 129, 45%); }
  }

  &__pin {
    position: absolute;
    color: rgba(255, 255, 255, 20%) !important;

    &--1 { inset-block-start: 16%; inset-inline-start: 12%; font-size: 42px; }
    &--3 { inset-block-start: 24%; inset-inline-end: 22%; font-size: 36px; }
  }
}

.login-brand {
  position: absolute;
  z-index: 2;
  inset-block-start: 1.75rem;
  inset-inline-start: 2rem;
  color: #fff;

  &__mark {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 20%);
    line-height: 0;
  }

  &__title { font-size: 1.125rem; font-weight: 700; }
}

.login-theme-toggle {
  position: absolute;
  z-index: 2;
  inset-block-start: 1.75rem;
  inset-inline-end: 2rem;
  color: #fff !important;
  background: rgba(255, 255, 255, 14%) !important;
  backdrop-filter: blur(6px);

  .v-icon { color: #fff !important; }
}

.login-card {
  position: relative;
  z-index: 1;
  inline-size: 100%;
  max-inline-size: 440px;
  padding: 2.75rem 2.25rem 2rem;
  border: 1px solid rgba(255, 255, 255, 12%);
  border-radius: 24px;
  background: rgba(var(--v-theme-surface), 0.86);
  backdrop-filter: blur(22px);
  box-shadow: 0 32px 80px -24px rgba(6, 40, 30, 55%);

  &__head { margin-block-end: 2rem; text-align: center; }

  &__badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    inline-size: 64px;
    block-size: 64px;
    margin-block-end: 1.25rem;
    border-radius: 50%;
    background: rgba(var(--v-theme-primary), 0.14);
    color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 8px rgba(var(--v-theme-primary), 0.06);
  }

  &__title { font-size: 1.625rem; font-weight: 700; line-height: 1.2; margin-block-end: 0.5rem; }
  &__subtitle { max-inline-size: 22rem; margin: 0 auto; font-size: 0.9375rem; line-height: 1.6; color: rgba(var(--v-theme-on-surface), 0.6); }
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;

  &__submit {
    margin-block-start: 0.25rem;
    font-size: 1rem !important;
    font-weight: 700;
    letter-spacing: 0;
    background: linear-gradient(135deg, #10b981 0%, rgb(var(--v-theme-primary)) 100%) !important;
    box-shadow: 0 10px 24px -8px rgba(var(--v-theme-primary), 0.6) !important;
  }

  &__link {
    display: flex;
    gap: 0.25rem;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgb(var(--v-theme-primary));
    text-decoration: none;
  }
}

.login-field {
  &__label {
    display: block;
    margin-block-end: 0.4rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.85);
  }

  .v-field { border-radius: 12px; background: rgba(var(--v-theme-on-surface), 0.03); }

  input:-webkit-autofill,
  input:-webkit-autofill:hover,
  input:-webkit-autofill:focus {
    caret-color: rgb(var(--v-theme-on-surface));
    transition: background-color 9999s ease-in-out 0s;
    -webkit-text-fill-color: rgb(var(--v-theme-on-surface));
    -webkit-box-shadow: 0 0 0 1000px rgb(var(--v-theme-surface)) inset;
  }
}

.otp-field {
  .v-otp-input__content { gap: 0.75rem; justify-content: center; max-inline-size: 300px; margin-inline: auto; padding: 0; }
  .v-otp-input__field { inline-size: 60px; block-size: 60px; font-size: 1.5rem; font-weight: 700; border-radius: 12px; }
}

@media (max-width: 600px) {
  .login-brand { inset-block-start: 1.25rem; inset-inline-start: 1.25rem; }
  .login-theme-toggle { inset-block-start: 1.25rem; inset-inline-end: 1.25rem; }
  .login-card { padding-inline: 1.5rem; }
}
</style>
