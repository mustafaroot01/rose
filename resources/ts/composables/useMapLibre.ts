import type { Map as MapLibreMap, MapOptions } from 'maplibre-gl'
import * as maplibregl from 'maplibre-gl'
import type { Ref } from 'vue'
import { onBeforeUnmount, onMounted, ref, shallowRef } from 'vue'
import 'maplibre-gl/dist/maplibre-gl.css'

// 👉 الإعدادات الافتراضية — مركز العراق تقريبًا
export const IRAQ_CENTER: [number, number] = [43.9, 33.2]
export const DEFAULT_ZOOM = 5.5

// نمط خريطة متجهي مجاني مبني على OpenStreetMap — بدون توكن ولا تكلفة
const MAP_STYLE = 'https://tiles.openfreemap.org/styles/liberty'

// إضافة دعم الكتابة من اليمين لليسار (التسميات العربية)
const RTL_PLUGIN_URL = 'https://unpkg.com/@mapbox/mapbox-gl-rtl-text@0.3.0/dist/mapbox-gl-rtl-text.js'

let rtlRegistered = false

export type UseMapLibreOptions = Partial<MapOptions>

export interface UseMapLibreBehavior {
  /** إظهار زر تحديد موقعي (افتراضيًا: مُفعّل). */
  geolocate?: boolean
  /** محاولة تحديد موقع المستخدم تلقائيًا عند فتح الخريطة. */
  autoLocate?: boolean
}

/**
 * أساس مشترك لتهيئة خريطة MapLibre:
 * - نمط OpenFreeMap المجاني + دعم العربية (RTL)
 * - أدوات تنقّل + زر تحديد الموقع + تنظيف تلقائي عند إزالة المكوّن
 *
 * يُعاد استخدامه في: خريطة النباتات (إدارة)، ومستقبلًا اختيار الموقع (حساب المواطن).
 */
export function useMapLibre(
  container: Ref<HTMLElement | undefined>,
  options: UseMapLibreOptions = {},
  behavior: UseMapLibreBehavior = {},
) {
  const map = shallowRef<MapLibreMap>()
  const isLoaded = ref(false)

  onMounted(() => {
    if (!container.value)
      return

    // تُسجَّل مرّة واحدة للتطبيق كله
    if (!rtlRegistered) {
      rtlRegistered = true
      maplibregl.setRTLTextPlugin(RTL_PLUGIN_URL, true).catch(() => {})
    }

    const instance = new maplibregl.Map({
      container: container.value,
      style: MAP_STYLE,
      center: IRAQ_CENTER,
      zoom: DEFAULT_ZOOM,
      // openfreemap لا يوفّر خط "Open Sans" (404) بل "Noto Sans" — نعيد توجيه طلبات الخطوط
      transformRequest: url => {
        if (url.includes('/fonts/'))
          return { url: url.replace(/\/fonts\/[^/]+\//, '/fonts/Noto%20Sans%20Regular/') }

        return { url }
      },
      ...options,
    })

    instance.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-left')

    // زر تحديد الموقع (+ تحديد تلقائي عند الفتح إن طُلب)
    let geolocate: InstanceType<typeof maplibregl.GeolocateControl> | undefined
    if (behavior.geolocate !== false) {
      geolocate = new maplibregl.GeolocateControl({
        positionOptions: { enableHighAccuracy: true },
        trackUserLocation: true,
        showUserLocation: true,
      })
      instance.addControl(geolocate, 'top-left')
    }

    instance.on('load', () => {
      isLoaded.value = true

      // محاولة تحديد الموقع تلقائيًا (يتطلب إذن المستخدم)
      if (behavior.autoLocate && geolocate)
        geolocate.trigger()
    })

    map.value = instance
  })

  onBeforeUnmount(() => {
    map.value?.remove()
    map.value = undefined
  })

  return { map, isLoaded }
}

/** لون من متغيّرات ثيم Vuetify (مثل: success / warning / error / primary). */
export function themeColor(name: string): string {
  if (typeof window === 'undefined')
    return '#808080'

  const value = getComputedStyle(document.documentElement)
    .getPropertyValue(`--v-theme-${name}`)
    .trim()

  return value ? `rgb(${value})` : '#808080'
}
