<script setup lang="ts">
import { useTheme } from 'vuetify'
import PlantsMap from '@/views/maps/PlantsMap.vue'
import type { PlantFeatureCollection } from '@/views/maps/types'
import { type PlantReport, reasonLabels, reportStatusMeta } from '@/views/plants/plantTypes'
import { hexToRgb } from '@layouts/utils'

interface StatsResponse {
  totals: { plants: number; users: number; reports: number; categories: number }
  plants_by_status: { approved: number; pending: number; rejected: number }
  reports_by_status: Record<string, number>
  users: { citizens: number; staff: number; active: number }
  plants_by_category: { name: string; color: string | null; count: number }[]
  plants_by_governorate: { name: string; count: number }[]
  plants_growth: { month: string; count: number }[]
  campaigns_performance: { id: number; title: string; goal: number; planted: number; progress: number; participants: number; status: string }[]
  recent_reports: PlantReport[]
}

const vuetifyTheme = useTheme()

const [{ data: statsData }, { data: mapData }] = await Promise.all([
  useApi<StatsResponse>('/admin/stats'),
  useApi<PlantFeatureCollection>('/admin/plants/map'),
])
const stats = computed(() => statsData.value)
const geojson = computed<PlantFeatureCollection>(() => mapData.value ?? { type: 'FeatureCollection', features: [] })

// 👉 بطاقات الأرقام
const kpis = computed(() => {
  const s = stats.value

  return [
    { label: 'إجمالي النباتات', value: s?.totals.plants ?? 0, hint: `معتمدة: ${s?.plants_by_status.approved ?? 0}`, icon: 'tabler-plant-2', color: 'success' },
    { label: 'المستخدمون', value: s?.totals.users ?? 0, hint: `مواطنون: ${s?.users.citizens ?? 0}`, icon: 'tabler-users', color: 'primary' },
    { label: 'البلاغات', value: s?.totals.reports ?? 0, hint: `جديدة: ${s?.reports_by_status.new ?? 0}`, icon: 'tabler-flag', color: 'warning' },
    { label: 'الأصناف', value: s?.totals.categories ?? 0, hint: 'أنواع النباتات', icon: 'tabler-leaf', color: 'info' },
  ]
})

const formatDate = (iso?: string | null) =>
  iso ? new Date(iso).toLocaleDateString('ar-EG', { day: 'numeric', month: 'short' }) : '—'

// ───────────────────────────── المخططات ─────────────────────────────

const labelColor = computed(() => `rgba(${hexToRgb(String(vuetifyTheme.current.value.colors['on-surface']))},0.7)`)

const borderColor = computed(() => {
  const v = vuetifyTheme.current.value.variables

  return `rgba(${hexToRgb(String(v['border-color']))},${v['border-opacity']})`
})

// 👉 نمو النباتات (مساحي)
const growthSeries = computed(() => [{ name: 'النباتات', data: (stats.value?.plants_growth ?? []).map(g => g.count) }])

const growthOptions = computed(() => {
  const theme = vuetifyTheme.current.value.colors
  const labels = (stats.value?.plants_growth ?? []).map(g => new Date(g.month).toLocaleDateString('ar-EG', { month: 'short' }))

  return {
    chart: { type: 'area', toolbar: { show: false }, parentHeightOffset: 0 },
    colors: [theme.primary],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 0.8, opacityFrom: 0.5, opacityTo: 0.05, stops: [0, 95] } },
    grid: { borderColor: borderColor.value, strokeDashArray: 6, padding: { top: 0, bottom: 0 } },
    xaxis: {
      categories: labels,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: labelColor.value } },
    },
    yaxis: { labels: { style: { colors: labelColor.value } } },
    tooltip: { theme: vuetifyTheme.current.value.dark ? 'dark' : 'light' },
  }
})

// 👉 حالات النباتات (دونت)
const statusSeries = computed(() => {
  const s = stats.value?.plants_by_status

  return [s?.approved ?? 0, s?.pending ?? 0, s?.rejected ?? 0]
})

const statusOptions = computed(() => {
  const theme = vuetifyTheme.current.value.colors
  const total = statusSeries.value.reduce((a, b) => a + b, 0)

  return {
    labels: ['معتمدة', 'قيد المراجعة', 'مرفوضة'],
    colors: [theme.success, theme.warning, theme.error],
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', labels: { colors: labelColor.value } },
    plotOptions: {
      pie: {
        donut: {
          size: '72%',
          labels: {
            show: true,
            value: { fontSize: '1.5rem', color: labelColor.value },
            name: { color: labelColor.value },
            total: { show: true, label: 'الإجمالي', fontSize: '0.9rem', color: labelColor.value, formatter: () => `${total}` },
          },
        },
      },
    },
  }
})

// 👉 النباتات حسب المحافظة (شريطي أفقي)
const govSeries = computed(() => [{ name: 'النباتات', data: (stats.value?.plants_by_governorate ?? []).map(g => g.count) }])

const govOptions = computed(() => {
  const theme = vuetifyTheme.current.value.colors

  return {
    chart: { type: 'bar', toolbar: { show: false }, parentHeightOffset: 0 },
    colors: [theme.info],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%', distributed: false } },
    dataLabels: { enabled: false },
    grid: { borderColor: borderColor.value, strokeDashArray: 6, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    xaxis: {
      categories: (stats.value?.plants_by_governorate ?? []).map(g => g.name),
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: { style: { colors: labelColor.value } },
    },
    yaxis: { labels: { style: { colors: labelColor.value } } },
    tooltip: { theme: vuetifyTheme.current.value.dark ? 'dark' : 'light' },
  }
})

// 👉 النباتات حسب الصنف (دونت)
const categorySeries = computed(() => (stats.value?.plants_by_category ?? []).map(c => c.count))

const categoryOptions = computed(() => {
  const theme = vuetifyTheme.current.value.colors
  const palette = [theme.primary, theme.success, theme.warning, theme.info, theme.error, theme.secondary]

  return {
    labels: (stats.value?.plants_by_category ?? []).map(c => c.name),
    colors: (stats.value?.plants_by_category ?? []).map((_, i) => palette[i % palette.length]),
    stroke: { width: 0 },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', labels: { colors: labelColor.value } },
    plotOptions: { pie: { donut: { size: '72%' } } },
  }
})
</script>

<template>
  <section v-if="stats">
    <h4 class="text-h4 mb-6">
      لوحة التحكم
    </h4>

    <!-- 👉 بطاقات الأرقام -->
    <VRow class="mb-2">
      <VCol
        v-for="(k, i) in kpis"
        :key="i"
        cols="12"
        sm="6"
        md="3"
      >
        <VCard class="h-100">
          <VCardText class="d-flex align-center gap-4">
            <VAvatar
              :color="k.color"
              variant="tonal"
              rounded
              size="52"
            >
              <VIcon
                :icon="k.icon"
                size="30"
              />
            </VAvatar>
            <div>
              <h4 class="text-h4">
                {{ k.value }}
              </h4>
              <div class="text-body-1 text-high-emphasis">
                {{ k.label }}
              </div>
              <div class="text-sm text-disabled">
                {{ k.hint }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 صف التحليلات العلوي -->
    <VRow class="mb-2">
      <VCol
        cols="12"
        md="8"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>نمو النباتات</VCardTitle>
            <VCardSubtitle>آخر 6 أشهر</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              type="area"
              :options="growthOptions"
              :series="growthSeries"
              :height="300"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="4"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>حالات النباتات</VCardTitle>
            <VCardSubtitle>التوزيع الحالي</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              type="donut"
              :options="statusOptions"
              :series="statusSeries"
              :height="300"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 صف المخططات الموزّعة -->
    <VRow class="mb-2">
      <VCol
        cols="12"
        md="6"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>النباتات حسب المحافظة</VCardTitle>
            <VCardSubtitle>أعلى 6 محافظات</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              type="bar"
              :options="govOptions"
              :series="govSeries"
              :height="300"
            />
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="6"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>النباتات حسب الصنف</VCardTitle>
            <VCardSubtitle>التوزيع حسب النوع</VCardSubtitle>
          </VCardItem>
          <VCardText>
            <VueApexCharts
              type="donut"
              :options="categoryOptions"
              :series="categorySeries"
              :height="300"
            />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- 👉 أداء الحملات -->
    <VCard v-if="(stats?.campaigns_performance?.length ?? 0) > 0" class="mb-6">
      <VCardItem>
        <VCardTitle>أداء الحملات</VCardTitle>
        <VCardSubtitle>الإنجاز مقابل الهدف لكل حملة</VCardSubtitle>
      </VCardItem>
      <VCardText>
        <div
          v-for="c in stats?.campaigns_performance ?? []"
          :key="c.id"
          class="mb-5"
        >
          <div class="d-flex align-center justify-space-between mb-1 flex-wrap gap-2">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-trees" size="18" color="success" />
              <span class="font-weight-medium">{{ c.title }}</span>
              <VChip
                size="x-small"
                :color="c.status === 'active' ? 'success' : c.status === 'ended' ? 'secondary' : 'warning'"
                label
              >
                {{ c.status === 'active' ? 'نشطة' : c.status === 'ended' ? 'منتهية' : 'مسودة' }}
              </VChip>
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ c.planted }} / {{ c.goal }} شجرة · {{ c.participants }} مشارك
            </div>
          </div>
          <div class="d-flex align-center gap-3">
            <VProgressLinear
              :model-value="c.progress"
              color="success"
              height="10"
              rounded
              class="flex-grow-1"
            />
            <span class="text-body-2 font-weight-bold" style="min-inline-size: 42px; text-align: end;">{{ c.progress }}%</span>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- 👉 الخريطة بعرض كامل -->
    <VCard class="mb-6">
      <VCardItem>
        <VCardTitle>خريطة النباتات</VCardTitle>
        <template #append>
          <VBtn
            variant="tonal"
            size="small"
            append-icon="tabler-arrow-left"
            :to="{ name: 'map' }"
          >
            توسيع
          </VBtn>
        </template>
      </VCardItem>
      <VCardText>
        <PlantsMap
          :data="geojson"
          height="420px"
        />
      </VCardText>
    </VCard>

    <!-- 👉 أحدث البلاغات (شبكة بطاقات) -->
    <VCard>
      <VCardItem>
        <VCardTitle>أحدث البلاغات</VCardTitle>
        <template #append>
          <VBtn
            variant="tonal"
            size="small"
            :to="{ name: 'plant-reports' }"
          >
            الكل
          </VBtn>
        </template>
      </VCardItem>
      <VDivider />
      <VCardText>
        <VRow v-if="stats.recent_reports.length">
          <VCol
            v-for="report in stats.recent_reports"
            :key="report.id"
            cols="12"
            sm="6"
            lg="4"
          >
            <VCard
              :to="report.plant?.id ? { name: 'plants-id', params: { id: report.plant.id } } : undefined"
              variant="outlined"
              class="h-100"
            >
              <VCardText class="d-flex align-center gap-3">
                <VAvatar
                  variant="tonal"
                  color="warning"
                  rounded
                  size="42"
                >
                  <VIcon
                    icon="tabler-flag"
                    size="22"
                  />
                </VAvatar>
                <div class="flex-grow-1 text-truncate">
                  <div class="font-weight-medium text-truncate">
                    {{ report.plant?.name ?? '—' }}
                  </div>
                  <div class="text-sm text-disabled">
                    {{ reasonLabels[report.reason] ?? report.reason }}
                  </div>
                </div>
                <div class="d-flex flex-column align-end gap-1">
                  <VChip
                    size="x-small"
                    :color="reportStatusMeta[report.status].color"
                    variant="tonal"
                  >
                    {{ reportStatusMeta[report.status].label }}
                  </VChip>
                  <span class="text-xs text-disabled">{{ formatDate(report.created_at) }}</span>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
        <div
          v-else
          class="text-center text-disabled py-6"
        >
          لا توجد بلاغات بعد
        </div>
      </VCardText>
    </VCard>
  </section>
</template>
