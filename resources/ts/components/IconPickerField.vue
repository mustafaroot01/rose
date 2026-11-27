<script setup lang="ts">
import tablerIcons from '@/plugins/iconify/tabler-icons.json'

/**
 * حقل اختيار أيقونة من المجموعة المضمّنة في اللوحة فقط
 * (بدل كتابة اسم حر قد لا يكون مضمّناً فلا يظهر).
 * القائمة تأتي من tabler-icons.json — انظر scan-icons.mjs.
 */
interface Props {
  modelValue?: string | null
  label?: string
  errorMessages?: string | string[]
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  label: 'الأيقونة (اختياري)',
  errorMessages: undefined,
})

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const items = computed(() => {
  const list = tablerIcons.picker.map(name => `tabler-${name}`)

  // إن كانت القيمة الحالية خارج القائمة (بيانات قديمة) نُبقيها ظاهرة.
  if (props.modelValue && !list.includes(props.modelValue))
    list.unshift(props.modelValue)

  return list
})

const value = computed({
  get: () => props.modelValue ?? '',
  set: (v: string | null) => emit('update:modelValue', v ?? ''),
})
</script>

<template>
  <AppAutocomplete
    v-model="value"
    :items="items"
    :label="label"
    placeholder="ابحث بالإنجليزية: tree, leaf, flower…"
    :error-messages="errorMessages"
    clearable
  >
    <template #selection="{ item }">
      <div class="d-flex align-center gap-2">
        <VIcon
          :icon="item.raw"
          size="20"
        />
        <span class="text-body-2">{{ item.raw.replace('tabler-', '') }}</span>
      </div>
    </template>
    <template #item="{ props: itemProps, item }">
      <VListItem v-bind="itemProps">
        <template #prepend>
          <VIcon
            :icon="item.raw"
            size="22"
            class="me-2"
          />
        </template>
        <template #title>
          {{ item.raw.replace('tabler-', '') }}
        </template>
      </VListItem>
    </template>
  </AppAutocomplete>
</template>
