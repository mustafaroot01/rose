<script setup lang="ts">
import { VForm } from 'vuetify/components/VForm'
import { type Role, coreRoles, permissionLabels } from './roleTypes'

interface Props {
  modelValue: boolean
  role?: Role | null
  allPermissions: string[]
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved', message: string): void
}

const props = withDefaults(defineProps<Props>(), { role: null })
const emit = defineEmits<Emit>()

const refForm = ref<VForm>()
const loading = ref(false)

const isEdit = computed(() => !!props.role)
const isAdminRole = computed(() => props.role?.name === 'admin')
const isCore = computed(() => !!props.role && coreRoles.includes(props.role.name))

const form = ref({ name: '', permissions: [] as string[] })
const errors = ref<Record<string, string | undefined>>({})

watch(() => props.modelValue, val => {
  if (!val)
    return
  errors.value = {}
  refForm.value?.resetValidation()
  if (props.role)
    form.value = { name: props.role.name, permissions: [...props.role.permissions] }
  else
    form.value = { name: '', permissions: [] }
})

const toggle = (perm: string) => {
  if (isAdminRole.value)
    return
  const i = form.value.permissions.indexOf(perm)
  if (i === -1)
    form.value.permissions.push(perm)
  else
    form.value.permissions.splice(i, 1)
}

const close = () => emit('update:modelValue', false)

const submit = async () => {
  const { valid } = await refForm.value!.validate()
  if (!valid)
    return
  loading.value = true
  errors.value = {}
  try {
    const url = isEdit.value ? `/admin/roles/${props.role!.id}` : '/admin/roles'
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
    // الأخطاء تظهر
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="540"
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
              icon="tabler-shield-cog"
              size="24"
            />
          </VAvatar>
        </template>
        <VCardTitle>{{ isEdit ? 'تعديل الدور وصلاحياته' : 'إضافة دور' }}</VCardTitle>
      </VCardItem>

      <VCardText>
        <VForm
          ref="refForm"
          @submit.prevent="submit"
        >
          <AppTextField
            v-model="form.name"
            label="اسم الدور"
            :readonly="isCore"
            :hint="isCore ? 'دور أساسي — الاسم غير قابل للتعديل' : ''"
            persistent-hint
            :rules="[requiredValidator]"
            :error-messages="errors.name"
          />

          <VAlert
            v-if="isAdminRole"
            type="info"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            دور "المشرف العام" يملك كل الصلاحيات دائماً.
          </VAlert>

          <div class="mt-4">
            <div class="text-body-1 font-weight-medium mb-2">
              الصلاحيات
            </div>
            <VList class="border rounded">
              <VListItem
                v-for="perm in allPermissions"
                :key="perm"
                @click="toggle(perm)"
              >
                <template #prepend>
                  <VCheckbox
                    :model-value="isAdminRole || form.permissions.includes(perm)"
                    :disabled="isAdminRole"
                    hide-details
                    @click.stop="toggle(perm)"
                  />
                </template>
                <VListItemTitle>{{ permissionLabels[perm] || perm }}</VListItemTitle>
              </VListItem>
            </VList>
          </div>
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
          {{ isEdit ? 'حفظ' : 'إضافة' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
