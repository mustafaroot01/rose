<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import type { PlantCategory } from './types'

interface Props {
  modelValue: boolean
  item?: PlantCategory | null
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
  name: '',
  icon: '',
  color: '#2E7D32',
  is_active: true,
})

const errors = ref<Record<string, string | undefined>>({ name: undefined, icon: undefined, color: undefined })

watch(() => props.modelValue, val => {
  if (!val)
    return

  errors.value = { name: undefined, icon: undefined, color: undefined }
  refForm.value?.resetValidation()

  if (props.item)
    form.value = { name: props.item.name, icon: props.item.icon ?? '', color: props.item.color ?? '#2E7D32', is_active: props.item.is_active }
  else
    form.value = { name: '', icon: '', color: '#2E7D32', is_active: true }
})

const close = () => emit('update:modelValue', false)

const submit = async () => {
  const { valid } = await refForm.value!.validate()
  if (!valid)
    return

  loading.value = true
  errors.value = { name: undefined, icon: undefined, color: undefined }

  try {
    const url = isEdit.value ? `/admin/plant-categories/${props.item!.id}` : '/admin/plant-categories'
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
            :color="form.color || 'primary'"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              :icon="form.icon || 'tabler-leaf'"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ isEdit ? 'تعديل صنف' : 'إضافة صنف نبات' }}</VCardTitle>
        <VCardSubtitle>مثال: شجرة، نخلة، زهور، شجيرة</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="submit"
        >
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="اسم الصنف"
                placeholder="مثال: نخلة"
                autofocus
                :rules="[requiredValidator]"
                :error-messages="errors.name"
              />
            </VCol>

            <VCol
              cols="12"
              sm="7"
            >
              <AppTextField
                v-model="form.icon"
                label="الأيقونة (اختياري)"
                placeholder="tabler-tree"
                :error-messages="errors.icon"
              />
            </VCol>

            <VCol
              cols="12"
              sm="5"
            >
              <label class="text-body-2 font-weight-medium d-block mb-1">اللون</label>
              <div class="d-flex align-center gap-2">
                <VMenu :close-on-content-click="false">
                  <template #activator="{ props: activatorProps }">
                    <VBtn
                      v-bind="activatorProps"
                      :color="form.color"
                      variant="flat"
                      size="small"
                      style="min-inline-size: 42px; block-size: 38px;"
                    />
                  </template>
                  <VColorPicker
                    v-model="form.color"
                    mode="hex"
                  />
                </VMenu>
                <AppTextField
                  v-model="form.color"
                  placeholder="#2E7D32"
                  :error-messages="errors.color"
                />
              </div>
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                :label="form.is_active ? 'مُفعّل' : 'مخفي'"
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
