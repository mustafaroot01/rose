<script setup lang="ts">
import type { Campaign } from './types'
import type { GovernorateOption } from '@/views/locations/types'

interface Props {
  modelValue: boolean
  item: Campaign | null
}
const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  'saved': [message: string]
}>()

const form = ref({
  title: '',
  description: '',
  governorate_id: undefined as number | undefined,
  goal_trees: 100,
  starts_at: '' as string | null,
  ends_at: '' as string | null,
  status: 'draft' as 'draft' | 'active' | 'ended',
  sponsor_name: '',
  sponsor_url: '',
})
const coverFile = ref<File | null>(null)
const coverPreview = ref<string | null>(null)

// الجهة الراعية (اختياري): شعار يُرفع، أو يُحذف صراحة عند التعديل
const sponsorLogoFile = ref<File | null>(null)
const sponsorLogoPreview = ref<string | null>(null)
const removeSponsorLogo = ref(false)
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const isEdit = computed(() => !!props.item)

const { data: govData } = await useApi<GovernorateOption[]>('/admin/governorates/options')
const governorates = computed(() => (govData.value ?? []).map(g => ({ title: g.name, value: g.id })))

const statusOptions = [
  { title: 'مسودّة', value: 'draft' },
  { title: 'نشطة', value: 'active' },
  { title: 'منتهية', value: 'ended' },
]

watch(() => props.modelValue, val => {
  if (!val)
    return
  errors.value = {}
  coverFile.value = null
  sponsorLogoFile.value = null
  removeSponsorLogo.value = false
  sponsorLogoPreview.value = props.item?.sponsor?.logo ?? null
  if (props.item) {
    coverPreview.value = props.item.cover
    form.value = {
      title: props.item.title,
      description: props.item.description ?? '',
      governorate_id: props.item.governorate?.id,
      goal_trees: props.item.goal_trees,
      starts_at: props.item.starts_at,
      ends_at: props.item.ends_at,
      status: props.item.status,
      sponsor_name: props.item.sponsor?.name ?? '',
      sponsor_url: props.item.sponsor?.url ?? '',
    }
  }
  else {
    coverPreview.value = null
    form.value = { title: '', description: '', governorate_id: undefined, goal_trees: 100, starts_at: '', ends_at: '', status: 'draft', sponsor_name: '', sponsor_url: '' }
  }
})

const onCoverPicked = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file)
    return
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
}

const onSponsorLogoPicked = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file)
    return
  sponsorLogoFile.value = file
  sponsorLogoPreview.value = URL.createObjectURL(file)
  removeSponsorLogo.value = false
}

const clearSponsorLogo = () => {
  sponsorLogoFile.value = null
  sponsorLogoPreview.value = null
  removeSponsorLogo.value = true
}

const close = () => emit('update:modelValue', false)

const save = async () => {
  saving.value = true
  errors.value = {}
  try {
    const body = new FormData()
    body.append('title', form.value.title)
    body.append('description', form.value.description ?? '')
    if (form.value.governorate_id)
      body.append('governorate_id', String(form.value.governorate_id))
    body.append('goal_trees', String(form.value.goal_trees ?? 0))
    if (form.value.starts_at)
      body.append('starts_at', form.value.starts_at)
    if (form.value.ends_at)
      body.append('ends_at', form.value.ends_at)
    body.append('status', form.value.status)
    if (coverFile.value)
      body.append('cover', coverFile.value)

    // نرسلها دائماً: الفارغ يمسح القيمة السابقة
    body.append('sponsor_name', form.value.sponsor_name ?? '')
    body.append('sponsor_url', form.value.sponsor_url ?? '')
    if (sponsorLogoFile.value)
      body.append('sponsor_logo', sponsorLogoFile.value)
    else if (removeSponsorLogo.value)
      body.append('remove_sponsor_logo', '1')

    const url = isEdit.value ? `/admin/campaigns/${props.item!.id}` : '/admin/campaigns'
    if (isEdit.value)
      body.append('_method', 'PUT')

    const res = await $api<{ message: string }>(url, { method: 'POST', body })
    emit('saved', res.message)
    close()
  }
  catch (err: any) {
    errors.value = err?.data?.errors ?? {}
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="600"
    @update:model-value="close"
  >
    <VCard>
      <VCardItem>
        <VCardTitle>{{ isEdit ? 'تعديل الحملة' : 'حملة تشجير جديدة' }}</VCardTitle>
      </VCardItem>
      <VDivider />
      <VCardText>
        <!-- الغلاف -->
        <div
          class="cover-picker mb-4"
          @click="($refs.coverInput as HTMLInputElement).click()"
        >
          <VImg
            v-if="coverPreview"
            :src="coverPreview"
            :aspect-ratio="16 / 7"
            cover
            rounded="lg"
          />
          <div
            v-else
            class="cover-placeholder"
          >
            <VIcon
              icon="tabler-photo-plus"
              size="32"
            />
            <span class="text-caption mt-1">اضغط لإضافة صورة الغلاف</span>
          </div>
          <input
            ref="coverInput"
            type="file"
            accept="image/*"
            hidden
            @change="onCoverPicked"
          >
        </div>

        <VRow>
          <VCol cols="12">
            <AppTextField
              v-model="form.title"
              label="عنوان الحملة"
              :error-messages="errors.title"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              label="الوصف"
              rows="3"
              :error-messages="errors.description"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.governorate_id"
              label="المحافظة المستهدفة"
              :items="governorates"
              clearable
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model.number="form.goal_trees"
              type="number"
              label="هدف عدد الأشجار"
              :error-messages="errors.goal_trees"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.starts_at"
              label="تاريخ البداية"
              :config="{ dateFormat: 'Y-m-d' }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.ends_at"
              label="تاريخ النهاية"
              :config="{ dateFormat: 'Y-m-d' }"
              :error-messages="errors.ends_at"
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.status"
              label="الحالة"
              :items="statusOptions"
            />
          </VCol>

          <!-- الجهة الراعية (اختياري) — تظهر في صفحة الحملة بالتطبيق وفي شهادة المشاركة -->
          <VCol cols="12">
            <VDivider class="mb-4" />
            <div class="text-body-1 font-weight-medium mb-3">
              الجهة الراعية (اختياري)
            </div>
            <div class="d-flex align-center gap-4">
              <div
                class="sponsor-logo"
                @click="($refs.sponsorInput as HTMLInputElement).click()"
              >
                <VImg
                  v-if="sponsorLogoPreview"
                  :src="sponsorLogoPreview"
                  contain
                  width="72"
                  height="72"
                />
                <VIcon
                  v-else
                  icon="tabler-photo-plus"
                  size="26"
                />
                <input
                  ref="sponsorInput"
                  type="file"
                  accept="image/*"
                  hidden
                  @change="onSponsorLogoPicked"
                >
              </div>
              <div class="d-flex flex-column gap-1">
                <span class="text-caption">شعار الراعي — اضغط على المربع لاختياره</span>
                <VBtn
                  v-if="sponsorLogoPreview"
                  size="small"
                  variant="text"
                  color="error"
                  prepend-icon="tabler-trash"
                  @click="clearSponsorLogo"
                >
                  حذف الشعار
                </VBtn>
              </div>
            </div>
            <div
              v-if="errors.sponsor_logo"
              class="text-error text-caption mt-1"
            >
              {{ errors.sponsor_logo[0] }}
            </div>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.sponsor_name"
              label="اسم الجهة الراعية"
              :error-messages="errors.sponsor_name"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.sponsor_url"
              label="رابط الراعي (اختياري)"
              placeholder="https://"
              dir="ltr"
              :error-messages="errors.sponsor_url"
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
          color="primary"
          :loading="saving"
          @click="save"
        >
          {{ isEdit ? 'حفظ' : 'إنشاء' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.cover-picker {
  cursor: pointer;
}

.sponsor-logo {
  display: flex;
  overflow: hidden;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  block-size: 76px;
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
  cursor: pointer;
  inline-size: 76px;
}

.cover-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  block-size: 150px;
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  color: rgba(var(--v-theme-on-surface), var(--v-disabled-opacity));
}
</style>
