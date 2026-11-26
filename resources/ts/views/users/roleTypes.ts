export interface Role {
  id: number
  name: string
  permissions: string[]
  users_count?: number
  created_at: string
}

export const permissionLabels: Record<string, string> = {
  'manage-locations': 'إدارة المواقع (المحافظات/الأقضية)',
  'manage-account-types': 'إدارة أنواع الحسابات',
  'manage-plant-categories': 'إدارة أصناف النباتات',
  'manage-plants': 'إدارة النباتات',
  'review-reports': 'مراجعة البلاغات',
  'manage-users': 'إدارة المستخدمين',
  'manage-roles': 'إدارة الأدوار والصلاحيات',
}

export const roleLabels: Record<string, string> = {
  admin: 'مشرف عام',
  supervisor: 'مشرف',
  citizen: 'مواطن',
}

export const coreRoles = ['admin', 'citizen']
