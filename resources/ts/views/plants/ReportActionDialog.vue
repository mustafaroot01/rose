<script setup lang="ts">
import { type PlantReport, reasonLabels } from './plantTypes'

interface Props {
  modelValue: boolean
  report: PlantReport | null
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', message: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const loading = ref(false)
const form = ref({ status: 'in_review', admin_note: '' })

const statusItems = [
  { title: 'جديد', value: 'new' },
  { title: 'قيد المراجعة', value: 'in_review' },
  { title: 'تم اتخاذ إجراء', value: 'action_taken' },
  { title: 'مرفوض', value: 'dismissed' },
]

watch(() => props.modelValue, val => {
  if (val && props.report)
    form.value = { status: props.report.status, admin_note: props.report.admin_note ?? '' }
})

const close = () => emit('update:modelValue', false)

const submit = async () => {
  if (!props.report)
    return
  loading.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plant-reports/${props.report.id}/status`, {
      method: 'PATCH',
      body: form.value,
    })
    emit('saved', res.message)
    close()
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="520"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="report">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="error"
            variant="tonal"
            rounded
            size="42"
          >
            <VIcon
              icon="tabler-flag"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>اتخاذ إجراء على البلاغ</VCardTitle>
        <VCardSubtitle>النبتة: {{ report.plant?.name }}</VCardSubtitle>
      </VCardItem>

      <VCardText>
        <VAlert
          variant="tonal"
          color="warning"
          density="compact"
          class="mb-4"
        >
          <div><strong>السبب:</strong> {{ reasonLabels[report.reason] }}</div>
          <div
            v-if="report.reason_note"
            class="text-sm mt-1"
          >
            {{ report.reason_note }}
          </div>
          <div class="text-sm text-disabled mt-1">
            المُبلِّغ: {{ report.reporter?.name }} — {{ report.reporter?.phone }}
          </div>
        </VAlert>

        <VRow>
          <VCol cols="12">
            <AppSelect
              v-model="form.status"
              label="حالة البلاغ"
              :items="statusItems"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.admin_note"
              label="ملاحظة / الإجراء المتخذ (اختياري)"
              rows="3"
              placeholder="مثال: تم إرسال فريق للسقي"
            />
          </VCol>
        </VRow>
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
          حفظ
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
