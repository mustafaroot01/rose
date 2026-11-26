<script lang="ts" setup>
import type { Notification } from '@layouts/types'

interface ApiNotification {
  id: number
  type: string
  title: string
  subtitle: string | null
  icon: string | null
  color: string
  link: string | null
  is_seen: boolean
  created_at: string
}

const notifications = ref<Notification[]>([])
const loading = ref(false)

const iconMap: Record<string, string> = {
  plant_added: 'tabler-plant-2',
  report_new: 'tabler-flag',
  user_registered: 'tabler-user-plus',
  status_changed: 'tabler-toggle-right',
  report_handled: 'tabler-checkup-list',
}

const timeAgo = (iso: string) => {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)

  if (mins < 1)
    return 'الآن'
  if (mins < 60)
    return `منذ ${mins} دقيقة`

  const hours = Math.floor(mins / 60)

  if (hours < 24)
    return `منذ ${hours} ساعة`

  const days = Math.floor(hours / 24)

  return `منذ ${days} يوم`
}

const mapNotification = (n: ApiNotification): Notification => ({
  id: n.id,
  title: n.title,
  subtitle: n.subtitle ?? '',
  time: timeAgo(n.created_at),
  color: n.color,
  icon: n.icon ?? iconMap[n.type] ?? 'tabler-bell',
  isSeen: n.is_seen,
})

const fetchNotifications = async () => {
  loading.value = true
  try {
    const res = await $api<{ data: ApiNotification[] }>('/admin/notifications')

    notifications.value = res.data.map(mapNotification)
  }
  catch {
    // silent
  }
  finally {
    loading.value = false
  }
}

fetchNotifications()

// poll every 60 seconds
const interval = setInterval(fetchNotifications, 60000)

onBeforeUnmount(() => clearInterval(interval))

const removeNotification = async (id: number) => {
  try {
    await $api(`/admin/notifications/${id}`, { method: 'DELETE' })
    notifications.value = notifications.value.filter(n => n.id !== id)
  }
  catch { /* silent */ }
}

const markRead = async (ids: number[]) => {
  try {
    await $api('/admin/notifications/read', { method: 'PATCH', body: { ids } })
    notifications.value.forEach(n => { if (ids.includes(n.id)) n.isSeen = true })
  }
  catch { /* silent */ }
}

const markUnRead = (ids: number[]) => {
  notifications.value.forEach(n => { if (ids.includes(n.id)) n.isSeen = false })
}

const handleNotificationClick = (notification: Notification) => {
  if (!notification.isSeen)
    markRead([notification.id])
}
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
