export interface AccountType {
  id: number
  name: string
  icon: string | null
  is_active: boolean
  users_count?: number
  created_at: string
  updated_at: string
}

export interface AccountTypeOption {
  id: number
  name: string
  is_active: boolean
}

export interface NamedRef {
  id: number
  name: string
}

export interface AppUser {
  id: number
  name: string
  phone: string
  email: string | null
  role: 'admin' | 'supervisor' | 'citizen'
  account_type_id: number | null
  account_type?: { id: number, name: string, icon: string | null }
  avatar: string | null
  status: 'active' | 'blocked'
  phone_verified: boolean
  governorate_id: number | null
  district_id: number | null
  nahiya_id: number | null
  governorate?: NamedRef
  district?: NamedRef
  nahiya?: NamedRef
  last_login_at?: string | null
  plants_count?: number
  created_at: string
}
