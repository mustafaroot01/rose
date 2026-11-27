export interface Campaign {
  id: number
  title: string
  slug: string
  description: string | null
  cover: string | null
  governorate?: { id: number, name: string } | null
  district?: { id: number, name: string } | null
  goal_trees: number
  trees_count: number
  progress: number
  participants_count?: number
  starts_at: string | null
  ends_at: string | null
  status: 'draft' | 'active' | 'ended'
  sponsor?: { name: string, logo: string | null, url: string | null } | null
  created_at?: string
}

export const campaignStatusMeta: Record<string, { label: string, color: string, icon: string }> = {
  draft: { label: 'مسودّة', color: 'secondary', icon: 'tabler-pencil' },
  active: { label: 'نشطة', color: 'success', icon: 'tabler-flame' },
  ended: { label: 'منتهية', color: 'error', icon: 'tabler-flag-check' },
}
