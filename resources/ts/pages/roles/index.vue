<script setup lang="ts">
import RoleDialog from '@/views/users/RoleDialog.vue'
import { type Role, coreRoles, permissionLabels, roleLabels } from '@/views/users/roleTypes'

const [{ data: rolesData, execute: fetchRoles }, { data: permsData }] = await Promise.all([
  useApi<{ data: Role[] }>('/admin/roles'),
  useApi<{ data: string[] }>('/admin/permissions'),
])

const roles = computed<Role[]>(() => rolesData.value?.data ?? [])
const allPermissions = computed<string[]>(() => permsData.value?.data ?? [])

const roleColor = (name: string) =>
  name === 'admin' ? 'primary' : name === 'supervisor' ? 'warning' : name === 'citizen' ? 'info' : 'secondary'

const snackbar = ref({ show: false, text: '', color: 'success' })
const notify = (text: string, color = 'success') => { snackbar.value = { show: true, text, color } }

const isDialogVisible = ref(false)
const editingRole = ref<Role | null>(null)
const openAdd = () => { editingRole.value = null; isDialogVisible.value = true }
const openEdit = (role: Role) => { editingRole.value = role; isDialogVisible.value = true }
const onSaved = (message: string) => { notify(message); fetchRoles() }

const deleteTarget = ref<Role | null>(null)
const isDeleteVisible = ref(false)
const deleting = ref(false)
const confirmDelete = (role: Role) => { deleteTarget.value = role; isDeleteVisible.value = true }

const doDelete = async () => {
  if (!deleteTarget.value)
    return
  deleting.value = true
  try {
    const res = await $api<{ message: string }>(`/admin/roles/${deleteTarget.value.id}`, { method: 'DELETE' })
    notify(res.message)
    isDeleteVisible.value = false
    fetchRoles()
  }
  catch (e: any) {
    notify(e?.data?.message ?? 'تعذّر الحذف.', 'error')
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <section>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h4 class="text-h4">
          الأدوار والصلاحيات
        </h4>
        <p class="text-body-1 mb-0">
          كل دور يملك مجموعة صلاحيات تحدّد ما يقدر يوصّله في لوحة التحكم.
        </p>
      </div>
      <VBtn
        prepend-icon="tabler-plus"
        @click="openAdd"
      >
        إضافة دور
      </VBtn>
    </div>

    <VRow>
      <VCol
        v-for="role in roles"
        :key="role.id"
        cols="12"
        sm="6"
        lg="4"
      >
        <VCard>
          <VCardText class="d-flex align-center justify-space-between pb-2">
            <span class="text-sm text-disabled">{{ role.users_count ?? 0 }} مستخدم</span>
            <div class="d-flex">
              <IconBtn
                size="small"
                @click="openEdit(role)"
              >
                <VIcon icon="tabler-edit" />
              </IconBtn>
              <IconBtn
                v-if="!coreRoles.includes(role.name)"
                size="small"
                color="error"
                @click="confirmDelete(role)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
            </div>
          </VCardText>

          <VCardText>
            <div class="d-flex align-center gap-x-3 mb-3">
              <VAvatar
                :color="roleColor(role.name)"
                variant="tonal"
                rounded
                size="42"
              >
                <VIcon
                  icon="tabler-shield-check"
                  size="24"
                />
              </VAvatar>
              <div>
                <h5 class="text-h5">
                  {{ roleLabels[role.name] || role.name }}
                </h5>
                <span class="text-sm text-disabled">{{ role.permissions.length }} صلاحية</span>
              </div>
            </div>

            <div
              v-if="role.name === 'admin'"
              class="text-body-2 text-primary"
            >
              كل الصلاحيات
            </div>
            <div
              v-else-if="role.permissions.length"
              class="d-flex flex-wrap gap-1"
            >
              <VChip
                v-for="p in role.permissions"
                :key="p"
                size="x-small"
                variant="tonal"
                :color="roleColor(role.name)"
              >
                {{ permissionLabels[p] || p }}
              </VChip>
            </div>
            <div
              v-else
              class="text-disabled text-body-2"
            >
              بلا صلاحيات إدارية
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <RoleDialog
      v-model="isDialogVisible"
      :role="editingRole"
      :all-permissions="allPermissions"
      @saved="onSaved"
    />

    <VDialog
      v-model="isDeleteVisible"
      max-width="420"
    >
      <VCard>
        <VCardItem>
          <template #prepend>
            <VAvatar
              color="error"
              variant="tonal"
              rounded
              size="42"
            >
              <VIcon
                icon="tabler-trash"
                size="24"
              />
            </VAvatar>
          </template>
          <VCardTitle>حذف الدور</VCardTitle>
        </VCardItem>
        <VCardText>
          هل أنت متأكد من حذف دور <strong>{{ deleteTarget?.name }}</strong>؟
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 pt-0">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDeleteVisible = false"
          >
            إلغاء
          </VBtn>
          <VBtn
            color="error"
            :loading="deleting"
            @click="doDelete"
          >
            حذف
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      location="top"
      :timeout="3000"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </section>
</template>
