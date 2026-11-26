export interface PlantCategory {
  id: number
  name: string
  icon: string | null
  color: string | null
  is_active: boolean
  plants_count?: number
  created_at: string
  updated_at: string
}

export interface PlantCategoryOption {
  id: number
  name: string
  icon: string | null
  color: string | null
  is_active: boolean
}
