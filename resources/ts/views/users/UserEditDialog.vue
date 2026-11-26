<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import type { AccountTypeOption, AppUser } from './types'
import type { DistrictOption, GovernorateOption } from '@/views/locations/types'

interface Props {
  modelValue: boolean
  item?: AppUser | null
  create?: boolean
  endpoint?: string
  accountTypes: AccountTypeOption[]
  governorates: GovernorateOption[]
  districts: DistrictOption[]
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', message: string): void
}

const props = withDefaults(defineProps<Props>(), { item: null, create: false, endpoint: '/admin/users' })
const emit = defineEmits<Emit>()

const refForm = ref<VForm>()
const loading = ref(false)

const emptyForm = () => ({
  name: '',
  phone: '',
  email: '',
  password: '',
  role: 'supervisor' as 'admin' | 'supervisor' | 'citizen',
  account_type_id: null as number | null,
  status: 'active' as 'active' | 'blocked',
  governorate_id: null as number | null,
  district_id: null as number | null,
})

const form = ref(emptyForm())

const errors = ref<Record<string, string | undefined>>({})

const allRoleItems = [
  { title: 'مشرف عام', value: 'admin' },
  { title: 'مشرف', value: 'supervisor' },
  { title: 'مواطن', value: 'citizen' },
]

// عند الإضافة (مشرف جديد) نقتصر على المشرف العام والمشرف فقط
const roleItems = computed(() => props.create ? allRoleItems.filter(r => r.value !== 'citizen') : allRoleItems)
const statusItems = [
  { title: 'مُفعّل', value: 'active' },
  { title: 'محظور', value: 'blocked' },
]

const accountTypeItems = computed(() => props.accountTypes.map(t => ({ title: t.name, value: t.id })))
const governorateItems = computed(() => props.governorates.map(g => ({ title: g.name, value: g.id })))
const districtItems = computed(() => props.districts
  .filter(d => !form.value.governorate_id || d.governorate_id === form.value.governorate_id)
  .map(d => ({ title: d.name, value: d.id })))

// إعادة ضبط القضاء عند تغيير المحافظة
watch(() => form.value.governorate_id, (val, old) => {
  if (old !== undefined && val !== old)
    form.value.district_id = null
})

watch(() => props.modelValue, val => {
  if (!val)
    return

  errors.value = {}
  refForm.value?.resetValidation()

  if (props.create || !props.item) {
    form.value = emptyForm()

    return
  }

  const u = props.item
  form.value = {
    name: u.name,
    phone: u.phone ?? '',
    email: u.email ?? '',
    password: '',
    role: u.role,
    account_type_id: u.account_type_id,
    status: u.status,
    governorate_id: u.governorate_id,
    district_id: u.district_id,
  }
})

const close = () => emit('update:modelValue', false)

const submit = async () => {
  const { valid } = await refForm.value!.validate()
  if (!valid)
    return
  if (!props.create && !props.item)
    return

  loading.value = true
  errors.value = {}

  try {
    const url = props.create ? props.endpoint : `${props.endpoint}/${props.item!.id}`
    const res = await $api<{ message: string }>(url, {
      method: props.create ? 'POST' : 'PUT',
      body: form.value,
      onResponseError({ response }) {
        errors.value = response._data?.errors ?? {}
      },
    })

    emit('saved', res.message)
    close()
  }
  catch {
    // تُعرض الأخطاء أسفل الحقول
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              :icon="create ? 'tabler-user-plus' : 'tabler-user-edit'"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ create ? 'إضافة مشرف' : 'تعديل المستخدم' }}</VCardTitle>
        <VCardSubtitle v-if="!create">
          {{ item?.phone }}
        </VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="submit"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.name"
                label="الاسم"
                :rules="[requiredValidator]"
                :error-messages="errors.name"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.email"
                label="البريد الإلكتروني (اختياري)"
                :error-messages="errors.email"
              />
            </VCol>

            <VCol
              v-if="create"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.phone"
                label="رقم الجوال"
                placeholder="07XXXXXXXXX"
                :rules="[requiredValidator]"
                :error-messages="errors.phone"
              />
            </VCol>
            <VCol
              v-if="create"
              cols="12"
              md="6"
            >
              <AppTextField
                v-model="form.password"
                label="كلمة المرور"
                type="password"
                :rules="[requiredValidator]"
                :error-messages="errors.password"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.role"
                label="الدور"
                :items="roleItems"
                :error-messages="errors.role"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.account_type_id"
                label="نوع الحساب"
                placeholder="اختر النوع"
                :items="accountTypeItems"
                clearable
                clear-icon="tabler-x"
                :error-messages="errors.account_type_id"
              />
            </VCol>

            <VCol cols="12">
              <AppSelect
                v-model="form.status"
                label="الحالة"
                :items="statusItems"
                :error-messages="errors.status"
              />
            </VCol>

            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.governorate_id"
                label="المحافظة"
                placeholder="—"
                :items="governorateItems"
                clearable
                clear-icon="tabler-x"
                :error-messages="errors.governorate_id"
              />
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <AppSelect
                v-model="form.district_id"
                label="القضاء"
                placeholder="—"
                :items="districtItems"
                clearable
                clear-icon="tabler-x"
                :error-messages="errors.district_id"
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardText class="d-flex justify-end gap-3 pt-0">
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          إلغاء
        </VBtn>
        <VBtn
          :loading="loading"
          @click="submit"
        >
          {{ create ? 'إضافة' : 'حفظ التعديلات' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
