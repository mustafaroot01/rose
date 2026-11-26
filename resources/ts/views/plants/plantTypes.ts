export interface NamedRef { id: number, name: string }

export interface PlantActivity {
  id: number
  type: string
  description: string | null
  meta: Record<string, any> | null
  user?: NamedRef
  created_at: string
}

export interface PlantReport {
  id: number
  plant_id: number
  plant?: { id: number, name: string, type: string | null, reports_count?: number }
  reason: string
  reason_note: string | null
  image: string | null
  status: 'new' | 'in_review' | 'action_taken' | 'dismissed'
  admin_note: string | null
  reporter?: { id: number, name: string, phone: string }
  handler?: NamedRef
  created_at: string
  updated_at: string
}

export interface Plant {
  id: number
  name: string
  type: string | null
  description: string | null
  category?: { id: number, name: string, color: string | null, icon: string | null }
  campaign?: { id: number, title: string, slug: string, cover: string | null } | null
  owner?: { id: number, name: string, phone: string }
  latitude: number
  longitude: number
  governorate?: NamedRef
  district?: NamedRef
  planting_date: string | null
  age_label: string | null
  image: string | null
  /** كل الصور (الرئيسية + المعرض) كروابط كاملة — تُعرض كشرائح في التطبيق. */
  images?: string[]
  /** صور المعرض فقط مع معرّفاتها (للحذف من لوحة التحكم). */
  gallery?: { id: number, url: string }[]
  status: 'pending' | 'approved' | 'rejected'
  reports_count?: number
  waterings_count?: number
  likes_count?: number
  activities?: PlantActivity[]
  reports?: PlantReport[]
  created_at: string
}

// تسميات عربية مشتركة
export const reasonLabels: Record<string, string> = {
  withered: 'ذابلة',
  dead: 'ميتة',
  needs_water: 'تحتاج سقي',
  vandalism: 'تخريب/قطع',
  wrong_location: 'موقع خاطئ',
  other: 'أخرى',
}

export const reportStatusMeta: Record<string, { label: string, color: string }> = {
  new: { label: 'جديد', color: 'info' },
  in_review: { label: 'قيد المراجعة', color: 'warning' },
  action_taken: { label: 'تم اتخاذ إجراء', color: 'success' },
  dismissed: { label: 'مرفوض', color: 'secondary' },
}

export const plantStatusMeta: Record<string, { label: string, color: string }> = {
  pending: { label: 'قيد المراجعة', color: 'warning' },
  approved: { label: 'معتمدة', color: 'success' },
  rejected: { label: 'مرفوضة', color: 'error' },
}

export const activityMeta: Record<string, { label: string, icon: string, color: string }> = {
  planted: { label: 'زُرعت', icon: 'tabler-seeding', color: 'success' },
  watered: { label: 'سقي', icon: 'tabler-droplet', color: 'info' },
  reported: { label: 'بلاغ', icon: 'tabler-flag', color: 'error' },
  report_handled: { label: 'معالجة بلاغ', icon: 'tabler-checkup-list', color: 'primary' },
  status_changed: { label: 'تغيّر الحالة', icon: 'tabler-refresh', color: 'warning' },
  updated: { label: 'تحديث', icon: 'tabler-edit', color: 'secondary' },
}
