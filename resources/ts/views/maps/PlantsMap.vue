<script setup lang="ts">
import type { GeoJSONSource, LngLatLike } from 'maplibre-gl'
import * as maplibregl from 'maplibre-gl'
import type { PlantFeatureCollection } from './types'
import { themeColor, useMapLibre } from '@/composables/useMapLibre'
import { plantStatusMeta } from '@/views/plants/plantTypes'

const props = withDefaults(defineProps<{
  data: PlantFeatureCollection
  autoLocate?: boolean
  height?: string
}>(), { height: '70vh' })

const router = useRouter()
const mapContainer = ref<HTMLElement>()
const { map, isLoaded } = useMapLibre(mapContainer, {}, { autoLocate: props.autoLocate })

const SOURCE = 'plants'
const emptyCollection: PlantFeatureCollection = { type: 'FeatureCollection', features: [] }
let popup: maplibregl.Popup | undefined

// 👉 إضافة المصدر والطبقات (مرّة واحدة بعد تحميل النمط)
const buildLayers = () => {
  const m = map.value
  if (!m || m.getSource(SOURCE))
    return

  m.addSource(SOURCE, {
    type: 'geojson',
    data: props.data ?? emptyCollection,
    cluster: true,
    clusterRadius: 50,
    clusterMaxZoom: 14,
  })

  // التجمّعات
  m.addLayer({
    id: 'clusters',
    type: 'circle',
    source: SOURCE,
    filter: ['has', 'point_count'],
    paint: {
      'circle-color': themeColor('primary'),
      'circle-opacity': 0.85,
      'circle-radius': ['step', ['get', 'point_count'], 16, 10, 22, 50, 30],
    },
  })
  m.addLayer({
    id: 'cluster-count',
    type: 'symbol',
    source: SOURCE,
    filter: ['has', 'point_count'],
    layout: {
      'text-field': ['get', 'point_count_abbreviated'],
      'text-size': 13,
    },
    paint: { 'text-color': '#ffffff' },
  })

  // النقاط المفردة — ملوّنة حسب حالة النبتة
  m.addLayer({
    id: 'plant-point',
    type: 'circle',
    source: SOURCE,
    filter: ['!', ['has', 'point_count']],
    paint: {
      'circle-color': [
        'match',
        ['get', 'status'],
        'approved',
        themeColor('success'),
        'pending',
        themeColor('warning'),
        'rejected',
        themeColor('error'),
        themeColor('primary'),
      ],
      'circle-radius': 7,
      'circle-stroke-width': 2,
      'circle-stroke-color': '#ffffff',
    },
  })

  // النقر على تجمّع → تكبير
  m.on('click', 'clusters', async e => {
    const features = m.queryRenderedFeatures(e.point, { layers: ['clusters'] })
    const clusterId = features[0]?.properties?.cluster_id
    if (clusterId == null)
      return

    const source = m.getSource(SOURCE) as GeoJSONSource
    const zoom = await source.getClusterExpansionZoom(clusterId)

    m.easeTo({ center: (features[0].geometry as any).coordinates, zoom })
  })

  // تلميح عند المرور على نبتة
  m.on('mouseenter', 'plant-point', e => {
    m.getCanvas().style.cursor = 'pointer'

    const feature = e.features?.[0]
    if (!feature)
      return

    const p = feature.properties as any
    const coords = (feature.geometry as any).coordinates.slice() as LngLatLike

    popup?.remove()
    popup = new maplibregl.Popup({ closeButton: false, offset: 12 })
      .setLngLat(coords)
      .setHTML(
        `<div style="font-weight:600">${p.name}</div>`
        + `<div style="font-size:12px;opacity:.7">${plantStatusMeta[p.status]?.label ?? ''}</div>`,
      )
      .addTo(m)
  })
  m.on('mouseleave', 'plant-point', () => {
    m.getCanvas().style.cursor = ''
    popup?.remove()
    popup = undefined
  })

  // النقر على نبتة → فتح صفحتها
  m.on('click', 'plant-point', e => {
    const id = e.features?.[0]?.properties?.id
    if (id)
      router.push({ name: 'plants-id', params: { id } })
  })
}

// 👉 تحديث البيانات عند تغيّر الفلاتر أو جاهزية الخريطة
watch(
  [isLoaded, () => props.data],
  () => {
    if (!isLoaded.value || !map.value)
      return

    buildLayers()

    const source = map.value.getSource(SOURCE) as GeoJSONSource | undefined

    source?.setData(props.data ?? emptyCollection)
  },
  { immediate: true },
)
</script>

<template>
  <div
    ref="mapContainer"
    class="plants-map"
    :style="{ minBlockSize: height }"
  />
</template>

<style scoped>
.plants-map {
  overflow: hidden;
  block-size: 100%;
  border-radius: 6px;
}
</style>
