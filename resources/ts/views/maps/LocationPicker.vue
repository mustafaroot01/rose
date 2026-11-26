<script setup lang="ts">
import * as maplibregl from 'maplibre-gl'
import type { LatLng } from './types'
import { DEFAULT_ZOOM, IRAQ_CENTER, themeColor, useMapLibre } from '@/composables/useMapLibre'

/**
 * اختيار موقع على الخريطة (للاستخدام المستقبلي في حساب المواطن):
 * المواطن ينقر على الخريطة أو يسحب العلامة لتحديد موقع نبتته، فنُصدر الإحداثيات.
 * يُبنى على نفس أساس الخريطة المشترك useMapLibre.
 */
const props = defineProps<{
  modelValue?: LatLng | null
}>()

const emit = defineEmits<{
  'update:modelValue': [LatLng]
}>()

const mapContainer = ref<HTMLElement>()

const initialCenter: [number, number] = props.modelValue
  ? [props.modelValue.lng, props.modelValue.lat]
  : IRAQ_CENTER

const { map, isLoaded } = useMapLibre(mapContainer, {
  center: initialCenter,
  zoom: props.modelValue ? 14 : DEFAULT_ZOOM,
})

let marker: maplibregl.Marker | undefined

const placeMarker = (lng: number, lat: number, emitChange = true) => {
  const m = map.value
  if (!m)
    return

  if (!marker) {
    marker = new maplibregl.Marker({ draggable: true, color: themeColor('primary') })
      .setLngLat([lng, lat])
      .addTo(m)

    marker.on('dragend', () => {
      const pos = marker!.getLngLat()

      emit('update:modelValue', { lat: pos.lat, lng: pos.lng })
    })
  }
  else {
    marker.setLngLat([lng, lat])
  }

  if (emitChange)
    emit('update:modelValue', { lat, lng })
}

watch(isLoaded, loaded => {
  const m = map.value
  if (!loaded || !m)
    return

  if (props.modelValue)
    placeMarker(props.modelValue.lng, props.modelValue.lat, false)

  m.on('click', e => placeMarker(e.lngLat.lng, e.lngLat.lat))
})
</script>

<template>
  <div
    ref="mapContainer"
    class="location-picker"
  />
</template>

<style scoped>
.location-picker {
  overflow: hidden;
  block-size: 100%;
  min-block-size: 360px;
  border-radius: 6px;
}
</style>
