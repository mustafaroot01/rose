<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import type { DistrictOption, Nahiya } from './types'

interface Props {
  modelValue: boolean
  item?: Nahiya | null
  districts: DistrictOption[]
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', message: string): void
}

const props = withDefaults(defineProps<Props>(), { item: null })
const emit = defineEmits<Emit>()

const refForm = ref<VForm>()
const loading = ref(false)

const isEdit = computed(() => !!props.item)

const form = ref({
  district_id: null as number | null,
  name: '',
  code: '',
  is_active: true,
})

const errors = ref<Record<string, string | undefined>>({
  district_id: undefined,
  name: undefined,
  code: undefined,
})

const districtItems = computed(() =>
  props.districts.map(d => ({
    title: d.governorate ? `${d.name} — ${d.governorate.name}` : d.name,
    value: d.id,
  })))

watch(() => props.modelValue, val => {
  if (!val)
    return

  errors.value = { district_id: undefined, name: undefined, code: undefined }
  refForm.value?.resetValidation()

  if (props.item) {
    form.value = {
      district_id: props.item.district_id,
      name: props.item.name,
      code: props.item.code ?? '',
      is_active: props.item.is_active,
    }
  }
  else {
    form.value = { district_id: null, name: '', code: '', is_active: true }
  }
})

const close = () => emit('update:modelValue', false)

const submit = async () => {
  const { valid } = await refForm.value!.validate()
  if (!valid)
    return

  loading.value = true
  errors.value = { district_id: undefined, name: undefined, code: undefined }

  try {
    const url = isEdit.value ? `/admin/nahiyas/${props.item!.id}` : '/admin/nahiyas'
    const res = await $api<{ message: string }>(url, {
      method: isEdit.value ? 'PUT' : 'POST',
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
    max-width="480"
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
              icon="tabler-map-pins"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ isEdit ? 'تعديل ناحية' : 'إضافة ناحية' }}</VCardTitle>
        <VCardSubtitle>أدخل بيانات الناحية</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="submit"
        >
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="form.district_id"
                label="القضاء"
                placeholder="اختر القضاء"
                :items="districtItems"
                :rules="[requiredValidator]"
                :error-messages="errors.district_id"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="اسم الناحية"
                placeholder="مثال: المنصور"
                :rules="[requiredValidator]"
                :error-messages="errors.name"
              />
            </VCol>

            <VCol cols="12">
              <AppTextField
                v-model="form.code"
                label="الرمز (اختياري)"
                :error-messages="errors.code"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                :label="form.is_active ? 'ظاهرة' : 'مخفية'"
                color="primary"
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
          {{ isEdit ? 'حفظ التعديلات' : 'إضافة' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
