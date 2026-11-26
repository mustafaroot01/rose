import type { FeatureCollection, Point } from 'geojson'

// خصائص النبتة على الخريطة (مختصرة — تأتي من GeoJSON الخفيف)
export interface PlantMapProperties {
  id: number
  name: string
  status: 'pending' | 'approved' | 'rejected'
  category: string | null
  color: string | null
  icon: string | null
}

export type PlantFeatureCollection = FeatureCollection<Point, PlantMapProperties>

export interface LatLng {
  lat: number
  lng: number
}
