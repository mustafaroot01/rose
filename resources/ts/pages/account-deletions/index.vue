<script setup lang="ts">
interface DeletionRequest {
  id: number
  citizen_id: number | null
  citizen_name: string | null
  citizen_phone: string | null
  reason: string | null
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  requested_at: string
  processed_at: string | null
  processor?: { id: number; name: string } | null
  admin_note: string | null
}

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const loading = ref(true)
const items = ref<DeletionRequest[]>([])
const stats = ref({ pending: 0, approved: 0 })
const statusFilter = ref<string | null>(null)
const busyId = ref<number | null>(null)

const statusMeta: Record<string, { label: string; color: string }> = {
  pending: { label: 'قيد المراجعة', color: 'warning' },
  approved: { label: 'تمت الموافقة (محذوف)', color: 'error' },
  rejected: { label: 'مرفوض', color: 'secondary' },
  cancelled: { label: 'ملغى', color: 'secondary' },
}

const load = async () => {
  loading.value = true
  try {
    const q = statusFilter.value ? `?status=${statusFilter.value}` : ''
    const res = await $api<{ data: DeletionRequest[]; stats: { pending: number; approved: number } }>(`/admin/account-deletions${q}`)
    items.value = res.data
    stats.value = res.stats
  } catch {
    notify('تعذّر تحميل الطلبات', 'error')
  } finally {
    loading.value = false
  }
}

const approve = async (r: DeletionRequest) => {
  if (!confirm(`سيتم حذف حساب «${r.citizen_name}» وبياناته الشخصية نهائيًا. متابعة؟`)) return
  busyId.value = r.id
  try {
    const res = await $api<{ message: string }>(`/admin/account-deletions/${r.id}/approve`, { method: 'POST' })
    notify(res.message)
    await load()
  } catch (e: any) {
    notify(e?.data?.message || 'تعذّر تنفيذ الموافقة', 'error')
  } finally {
    busyId.value = null
  }
}

const reject = async (r: DeletionRequest) => {
  const note = prompt('سبب الرفض (اختياري):') ?? ''
  busyId.value = r.id
  try {
    const res = await $api<{ message: string }>(`/admin/account-deletions/${r.id}/reject`, { method: 'POST', body: { admin_note: note } })
    notify(res.message)
    await load()
  } catch (e: any) {
    notify(e?.data?.message || 'تعذّر الرفض', 'error')
  } finally {
    busyId.value = null
  }
}

const fmt = (d: string | null) => d ? new Date(d).toLocaleString('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

watch(statusFilter, load)
onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-6">
      <div>
        <h4 class="text-h4 mb-1">طلبات حذف الحسابات</h4>
        <span class="text-body-1 text-medium-emphasis">مراجعة ومعالجة طلبات حذف حسابات المواطنين (امتثال لسياسات المتاجر)</span>
      </div>
      <VBtn variant="tonal" prepend-icon="tabler-refresh" :loading="loading" @click="load">تحديث</VBtn>
    </div>

    <VRow class="mb-4">
      <VCol cols="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar color="warning" variant="tonal" rounded><VIcon icon="tabler-hourglass" /></VAvatar>
            <div>
              <div class="text-h5">{{ stats.pending }}</div>
              <div class="text-body-2 text-medium-emphasis">قيد المراجعة</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6" md="3">
        <VCard>
          <VCardText class="d-flex align-center gap-3">
            <VAvatar color="error" variant="tonal" rounded><VIcon icon="tabler-user-off" /></VAvatar>
            <div>
              <div class="text-h5">{{ stats.approved }}</div>
              <div class="text-body-2 text-medium-emphasis">حُذفت</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardText>
        <VSelect
          v-model="statusFilter"
          :items="[
            { title: 'الكل', value: null },
            { title: 'قيد المراجعة', value: 'pending' },
            { title: 'تمت الموافقة', value: 'approved' },
            { title: 'مرفوض', value: 'rejected' },
            { title: 'ملغى', value: 'cancelled' },
          ]"
          label="تصفية حسب الحالة"
          style="max-inline-size: 260px"
          clearable
        />
      </VCardText>
      <VDivider />
      <VTable>
        <thead>
          <tr>
            <th>المواطن</th>
            <th>الهاتف</th>
            <th>السبب</th>
            <th>الحالة</th>
            <th>تاريخ الطلب</th>
            <th>المعالِج</th>
            <th class="text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="7" class="text-center py-8"><VProgressCircular indeterminate color="primary" /></td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="7" class="text-center py-8 text-disabled">لا توجد طلبات</td>
          </tr>
          <tr v-for="r in items" :key="r.id">
            <td class="font-weight-medium">{{ r.citizen_name || '—' }}</td>
            <td dir="ltr">{{ r.citizen_phone || '—' }}</td>
            <td style="max-inline-size: 240px">{{ r.reason || '—' }}</td>
            <td>
              <VChip size="small" :color="statusMeta[r.status]?.color" label>{{ statusMeta[r.status]?.label }}</VChip>
            </td>
            <td>{{ fmt(r.requested_at) }}</td>
            <td>{{ r.processor?.name || '—' }}</td>
            <td class="text-center">
              <div v-if="r.status === 'pending'" class="d-flex gap-1 justify-center">
                <VBtn size="small" color="error" variant="tonal" :loading="busyId === r.id" @click="approve(r)">موافقة وحذف</VBtn>
                <VBtn size="small" color="secondary" variant="tonal" :loading="busyId === r.id" @click="reject(r)">رفض</VBtn>
              </div>
              <span v-else class="text-disabled">—</span>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" location="top">{{ snackbar.text }}</VSnackbar>
  </div>
</template>
