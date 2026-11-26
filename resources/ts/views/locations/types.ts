export interface District {
  id: number
  governorate_id: number
  governorate?: Governorate
  name: string
  code: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Governorate {
  id: number
  name: string
  code: string | null
  is_active: boolean
  districts_count?: number
  districts?: District[]
  created_at: string
  updated_at: string
}

export interface GovernorateOption {
  id: number
  name: string
  is_active: boolean
}

export interface Nahiya {
  id: number
  district_id: number
  district?: District
  name: string
  code: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface DistrictOption {
  id: number
  name: string
  governorate_id: number
  governorate?: { id: number, name: string }
  is_active: boolean
}
