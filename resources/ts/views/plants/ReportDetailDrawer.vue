<script setup lang="ts">
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { type PlantReport, reasonLabels, reportStatusMeta } from './plantTypes'

interface Props {
  modelValue: boolean
  report: PlantReport | null
}

interface Emit {
  (e: 'update:modelValue', value: boolean): void
  (e: 'action', report: PlantReport): void
  (e: 'changed', message: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emit>()

const changing = ref(false)

const quickStatuses = [
  { value: 'in_review', label: 'قيد المراجعة', color: 'warning', icon: 'tabler-clock' },
  { value: 'action_taken', label: 'تم اتخاذ إجراء', color: 'success', icon: 'tabler-circle-check' },
  { value: 'dismissed', label: 'رفض البلاغ', color: 'secondary', icon: 'tabler-x' },
]

const formatDateTime = (iso?: string | null) =>
  iso ? new Date(iso).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

const close = () => emit('update:modelValue', false)

const openImage = (url: string) => window.open(url, '_blank')

// 👉 تغيير سريع للحالة (بدون ملاحظة)
const setStatus = async (status: string) => {
  if (!props.report)
    return

  changing.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/plant-reports/${props.report.id}/status`, {
      method: 'PATCH',
      body: { status },
    })

    emit('changed', res.message)
  }
  finally {
    changing.value = false
  }
}

// 👉 المسار الزمني
const timeline = computed(() => {
  if (!props.report)
    return []

  const items = [
    { title: 'وصل البلاغ', subtitle: formatDateTime(props.report.created_at), color: 'info', icon: 'tabler-flag' },
  ]

  if (props.report.handler) {
    items.push({
      title: `عالجه: ${props.report.handler.name}`,
      subtitle: formatDateTime(props.report.updated_at),
      color: reportStatusMeta[props.report.status]?.color ?? 'primary',
      icon: 'tabler-user-check',
    })
  }

  return items
})
</script>

<template>
  <VNavigationDrawer
    :model-value="modelValue"
    temporary
    location="end"
    width="400"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <template v-if="report">
      <!-- الرأس -->
      <div class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center gap-2">
          <h6 class="text-h6">
            بلاغ #{{ report.id }}
          </h6>
          <VChip
            size="small"
            :color="reportStatusMeta[report.status].color"
            variant="tonal"
          >
            {{ reportStatusMeta[report.status].label }}
          </VChip>
        </div>
        <IconBtn @click="close">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </div>
      <VDivider />

      <PerfectScrollbar
        :options="{ wheelPropagation: false }"
        class="h-100"
      >
        <div class="pa-4 d-flex flex-column gap-4">
          <!-- النبتة -->
          <div>
            <div class="text-sm text-disabled mb-1">
              النبتة
            </div>
            <div class="d-flex align-center justify-space-between">
              <RouterLink
                v-if="report.plant"
                :to="{ name: 'plants-id', params: { id: report.plant.id } }"
                class="d-flex align-center gap-x-2 text-link font-weight-medium"
              >
                <VIcon
                  icon="tabler-plant"
                  size="20"
                />
                {{ report.plant.name }}
              </RouterLink>
              <span
                v-else
                class="text-disabled"
              >—</span>

              <VChip
                v-if="(report.plant?.reports_count ?? 0) > 1"
                size="x-small"
                color="error"
                variant="tonal"
              >
                {{ report.plant?.reports_count }} بلاغات
              </VChip>
            </div>
          </div>

          <!-- المُبلِّغ -->
          <div>
            <div class="text-sm text-disabled mb-1">
              المُبلِّغ
            </div>
            <RouterLink
              v-if="report.reporter"
              :to="{ name: 'users-id', params: { id: report.reporter.id } }"
              class="text-link font-weight-medium"
            >
              {{ report.reporter.name }}
            </RouterLink>
            <span
              v-else
              class="text-disabled"
            >—</span>
            <div
              v-if="report.reporter"
              class="text-sm text-disabled"
            >
              {{ report.reporter.phone }}
            </div>
          </div>

          <!-- السبب -->
          <div>
            <div class="text-sm text-disabled mb-1">
              السبب
            </div>
            <VChip
              size="small"
              color="warning"
              variant="tonal"
            >
              {{ reasonLabels[report.reason] ?? report.reason }}
            </VChip>
            <p
              v-if="report.reason_note"
              class="text-body-2 mt-2 mb-0"
            >
              {{ report.reason_note }}
            </p>
          </div>

          <!-- صورة الدليل -->
          <div v-if="report.image">
            <div class="text-sm text-disabled mb-1">
              صورة الدليل
            </div>
            <VImg
              :src="report.image"
              max-height="200"
              rounded="lg"
              cover
              class="cursor-pointer"
              @click="openImage(report.image!)"
            />
          </div>

          <!-- ملاحظة الإدارة -->
          <VAlert
            v-if="report.admin_note"
            variant="tonal"
            color="info"
            density="compact"
          >
            <div class="text-sm font-weight-medium mb-1">
              ملاحظة الإدارة
            </div>
            {{ report.admin_note }}
          </VAlert>

          <VDivider />

          <!-- المسار الزمني -->
          <div>
            <div class="text-sm text-disabled mb-3">
              المسار الزمني
            </div>
            <VTimeline
              side="end"
              align="start"
              truncate-line="both"
              density="compact"
              class="v-timeline--variant-outlined"
            >
              <VTimelineItem
                v-for="(t, i) in timeline"
                :key="i"
                :dot-color="t.color"
                size="x-small"
              >
                <div class="font-weight-medium">
                  {{ t.title }}
                </div>
                <div class="text-sm text-disabled">
                  {{ t.subtitle }}
                </div>
              </VTimelineItem>
            </VTimeline>
          </div>
        </div>
      </PerfectScrollbar>

      <!-- الإجراءات أسفل اللوحة -->
      <div class="pa-4">
        <VDivider class="mb-4" />
        <div class="text-sm text-disabled mb-2">
          تغيير سريع للحالة
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <VBtn
            v-for="s in quickStatuses.filter(q => q.value !== report!.status)"
            :key="s.value"
            size="small"
            variant="tonal"
            :color="s.color"
            :prepend-icon="s.icon"
            :loading="changing"
            @click="setStatus(s.value)"
          >
            {{ s.label }}
          </VBtn>
        </div>
        <VBtn
          block
          prepend-icon="tabler-checkup-list"
          @click="emit('action', report)"
        >
          اتخاذ إجراء مع ملاحظة
        </VBtn>
      </div>
    </template>
  </VNavigationDrawer>
</template>
