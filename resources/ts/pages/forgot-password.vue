<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

definePage({
  meta: {
    layout: 'blank',
    unauthenticatedOnly: true,
  },
})

const router = useRouter()

// 1 = إدخال الجوال | 2 = إدخال الرمز وكلمة السر الجديدة
const step = ref(1)
const loading = ref(false)
const isPasswordVisible = ref(false)

// خيارات زر الوضع (نهاري/ليلي/حسب النظام)
const themeSwitcherThemes = [
  { name: 'light', icon: 'tabler-sun-high' },
  { name: 'dark', icon: 'tabler-moon-stars' },
  { name: 'system', icon: 'tabler-device-desktop-analytics' },
]

const refStep1 = ref<VForm>()
const refStep2 = ref<VForm>()

const form = ref({
  phone: '',
  code: '',
  password: '',
})

const errors = ref<Record<string, string | undefined>>({
  phone: undefined,
  code: undefined,
  password: undefined,
})

// تحويل الأرقام العربية/الفارسية إلى إنجليزية (لاتينية)
const toLatinDigits = (s: string) =>
  s.replace(/[٠-٩]/g, d => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
    .replace(/[۰-۹]/g, d => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))

// حقل الجوال: أرقام إنجليزية فقط + طول رقم عراقي (بصفر 11 خانة / بدون صفر 10 خانات)
watch(() => form.value.phone, val => {
  let digits = toLatinDigits(val).replace(/\D/g, '')
  const maxLen = digits.startsWith('0') ? 11 : 10
  if (digits.length > maxLen)
    digits = digits.slice(0, maxLen)
  if (digits !== val)
    form.value.phone = digits
})

const iraqiPhoneValidator = (v: string) =>
  /^0?7\d{9}$/.test(v || '') || 'أدخل رقم عراقي صحيح (مثال: 07701234567 أو 7701234567)'

const blockNonDigit = (e: KeyboardEvent) => {
  if (e.key.length === 1 && !/[0-9]/.test(e.key))
    e.preventDefault()
}

const snackbar = ref({ show: false, text: '', color: 'success' })

const notify = (text: string, color = 'success') => {
  snackbar.value = { show: true, text, color }
}

// إرسال رمز الاستعادة
const sendCode = async () => {
  loading.value = true
  errors.value = { phone: undefined, code: undefined, password: undefined }
  try {
    await $api('/auth/forgot-password', {
      method: 'POST',
      body: { phone: form.value.phone },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
      },
    })
    notify('تم إرسال رمز الاستعادة إلى واتساب 📲')
    step.value = 2
  }
  catch (err) {
    console.error(err)
  }
  finally {
    loading.value = false
  }
}

// إعادة تعيين كلمة السر
const resetPassword = async () => {
  loading.value = true
  errors.value = { phone: undefined, code: undefined, password: undefined }
  try {
    await $api('/auth/reset-password', {
      method: 'POST',
      body: {
        phone: form.value.phone,
        code: form.value.code,
        password: form.value.password,
      },
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
      },
    })
    notify('تم تغيير كلمة السر بنجاح ✅')
    setTimeout(() => router.replace({ name: 'login' }), 1200)
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
      sendCode()
  })
}

const onSubmitStep2 = () => {
  refStep2.value?.validate().then(({ valid }) => {
    if (valid)
      resetPassword()
  })
}
</script>

<template>
  <div class="login-page">
    <!-- 👉 طبقات الخلفية الجغرافية -->
    <div class="login-bg">
      <div class="login-bg__grid" />
      <span class="login-bg__glow login-bg__glow--1" />
      <span class="login-bg__glow login-bg__glow--2" />
      <VIcon
        icon="tabler-map-pin-filled"
        class="login-bg__pin login-bg__pin--1"
      />
      <VIcon
        icon="tabler-map-pin-filled"
        class="login-bg__pin login-bg__pin--2"
      />
      <VIcon
        icon="tabler-plant-2"
        class="login-bg__pin login-bg__pin--3"
      />
    </div>

    <!-- 👉 شعار العلامة أعلى الصفحة -->
    <RouterLink
      to="/"
      class="login-brand d-flex align-center gap-x-2 text-decoration-none"
    >
      <div class="login-brand__mark">
        <VNodeRenderer :nodes="themeConfig.app.logo" />
      </div>
      <span class="login-brand__title">{{ themeConfig.app.title }}</span>
    </RouterLink>

    <!-- 👉 زر الوضع الليلي/النهاري (مربوط بثيم الداشبورد) -->
    <ThemeSwitcher
      :themes="themeSwitcherThemes"
      class="login-theme-toggle"
    />

    <!-- 👉 البطاقة -->
    <div class="login-card">
      <div class="login-card__head">
        <div class="login-card__badge">
          <VIcon
            :icon="step === 1 ? 'tabler-lock-question' : 'tabler-key'"
            size="28"
          />
        </div>

        <!-- مؤشّر الخطوات -->
        <div class="login-steps mb-4">
          <div
            class="login-steps__dot"
            :class="{ 'login-steps__dot--active': step >= 1 }"
          />
          <div
            class="login-steps__bar"
            :class="{ 'login-steps__bar--active': step >= 2 }"
          />
          <div
            class="login-steps__dot"
            :class="{ 'login-steps__dot--active': step >= 2 }"
          />
        </div>

        <h4 class="login-card__title">
          {{ step === 1 ? 'نسيت كلمة السر؟' : 'إعادة تعيين كلمة السر' }}
        </h4>
        <p class="login-card__subtitle">
          {{ step === 1
            ? 'أدخل رقم جوالك المسجّل ونرسل لك رمز استعادة عبر واتساب'
            : 'أدخل الرمز الذي وصلك على واتساب وكلمة السر الجديدة' }}
        </p>
      </div>

      <!-- الخطوة 1: إدخال الجوال -->
      <VForm
        v-if="step === 1"
        ref="refStep1"
        class="login-form"
        @submit.prevent="onSubmitStep1"
      >
        <div class="login-field">
          <label class="login-field__label">رقم الجوال</label>
          <AppTextField
            v-model="form.phone"
            autofocus
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

        <VBtn
          block
          size="large"
          type="submit"
          class="login-form__submit"
          :loading="loading"
        >
          إرسال رمز الاستعادة
          <VIcon
            end
            icon="tabler-brand-whatsapp"
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
          <span>العودة لتسجيل الدخول</span>
        </RouterLink>
      </VForm>

      <!-- الخطوة 2: الرمز + كلمة السر الجديدة -->
      <VForm
        v-else
        ref="refStep2"
        class="login-form"
        @submit.prevent="onSubmitStep2"
      >
        <div class="login-field">
          <label class="login-field__label text-center">رمز الاستعادة (4 أرقام)</label>
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

        <div class="login-field">
          <label class="login-field__label">كلمة السر الجديدة</label>
          <AppTextField
            v-model="form.password"
            placeholder="أدخل كلمة السر الجديدة"
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
          تغيير كلمة السر
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
          <span>تغيير رقم الجوال</span>
        </a>
      </VForm>

      <p class="login-card__footer">
        Smart Geospatial Platform for Community-Based Afforestation Management
      </p>
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

/* 👉 الخلفية */
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

    &--1 {
      inset-block-start: -120px;
      inset-inline-end: -80px;
      inline-size: 420px;
      block-size: 420px;
      background: rgba(255, 255, 255, 22%);
    }

    &--2 {
      inset-block-end: -160px;
      inset-inline-start: -120px;
      inline-size: 460px;
      block-size: 460px;
      background: rgba(16, 185, 129, 45%);
    }
  }

  &__pin {
    position: absolute;
    color: rgba(255, 255, 255, 20%) !important;

    &--1 { inset-block-start: 16%; inset-inline-start: 12%; font-size: 42px; }
    &--2 { inset-block-end: 18%; inset-inline-end: 14%; font-size: 30px; }
    &--3 { inset-block-start: 24%; inset-inline-end: 22%; font-size: 36px; }
  }
}

/* 👉 زر الوضع الليلي/النهاري */
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

/* 👉 الشعار */
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

  &__title {
    font-size: 1.125rem;
    font-weight: 700;
  }
}

/* 👉 البطاقة */
.login-card {
  position: relative;
  z-index: 1;
  inline-size: 100%;
  max-inline-size: 430px;
  padding: 2.75rem 2.25rem 2rem;
  border: 1px solid rgba(255, 255, 255, 12%);
  border-radius: 24px;
  background: rgba(var(--v-theme-surface), 0.86);
  backdrop-filter: blur(22px);
  box-shadow: 0 32px 80px -24px rgba(6, 40, 30, 55%);

  &__head {
    margin-block-end: 2rem;
    text-align: center;
  }

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

  &__title {
    font-size: 1.625rem;
    font-weight: 700;
    line-height: 1.2;
    margin-block-end: 0.5rem;
  }

  &__subtitle {
    max-inline-size: 22rem;
    margin: 0 auto;
    font-size: 0.9375rem;
    line-height: 1.6;
    color: rgba(var(--v-theme-on-surface), 0.6);
  }

  &__footer {
    margin-block: 1.75rem 0;
    padding-block-start: 1.25rem;
    border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
    font-size: 0.6875rem;
    line-height: 1.7;
    text-align: center;
    overflow-wrap: anywhere;
    color: rgba(var(--v-theme-on-surface), 0.45);
  }
}

/* 👉 النموذج */
.login-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;

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

/* 👉 الحقول */
.login-field {
  &__label {
    display: block;
    margin-block-end: 0.4rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), 0.85);
  }

  .v-field {
    border-radius: 12px;
    background: rgba(var(--v-theme-on-surface), 0.03);
  }

  input:-webkit-autofill,
  input:-webkit-autofill:hover,
  input:-webkit-autofill:focus {
    caret-color: rgb(var(--v-theme-on-surface));
    transition: background-color 9999s ease-in-out 0s;
    -webkit-text-fill-color: rgb(var(--v-theme-on-surface));
    -webkit-box-shadow: 0 0 0 1000px rgb(var(--v-theme-surface)) inset;
  }
}

/* 👉 مؤشّر الخطوات */
.login-steps {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;

  &__dot {
    inline-size: 12px;
    block-size: 12px;
    border-radius: 50%;
    background: rgba(var(--v-theme-on-surface), 0.16);
    transition: background-color 0.25s ease;

    &--active { background: rgb(var(--v-theme-primary)); }
  }

  &__bar {
    inline-size: 48px;
    block-size: 3px;
    border-radius: 3px;
    background: rgba(var(--v-theme-on-surface), 0.16);
    transition: background-color 0.25s ease;

    &--active { background: rgb(var(--v-theme-primary)); }
  }
}

/* 👉 مربعات رمز التحقق */
.otp-field {
  .v-otp-input__content {
    gap: 0.75rem;
    justify-content: center;
    max-inline-size: 300px;
    margin-inline: auto;
    padding: 0;
  }

  .v-otp-input__field {
    inline-size: 60px;
    block-size: 60px;
    font-size: 1.5rem;
    font-weight: 700;
    border-radius: 12px;
  }
}

@media (max-width: 600px) {
  .login-brand {
    inset-block-start: 1.25rem;
    inset-inline-start: 1.25rem;
  }

  .login-theme-toggle {
    inset-block-start: 1.25rem;
    inset-inline-end: 1.25rem;
  }

  .login-card {
    padding-inline: 1.5rem;
  }
}
</style>
