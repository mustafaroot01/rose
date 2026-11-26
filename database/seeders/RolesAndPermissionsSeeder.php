<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // تفريغ كاش الصلاحيات
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 👉 الصلاحيات
        $permissions = [
            'manage-locations' => 'إدارة المواقع (المحافظات/الأقضية)',
            'manage-account-types' => 'إدارة أنواع الحسابات',
            'manage-plant-categories' => 'إدارة أصناف النباتات',
            'manage-plants' => 'إدارة النباتات',
            'review-reports' => 'مراجعة البلاغات',
            'manage-users' => 'إدارة المستخدمين',
            'manage-roles' => 'إدارة الأدوار والصلاحيات',
        ];

        foreach (array_keys($permissions) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 👉 الأدوار
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_keys($permissions)); // كل الصلاحيات

        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions(['manage-plants', 'review-reports']);

        $citizen = Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        $citizen->syncPermissions([]); // المواطن بلا صلاحيات إدارية

        // 👉 ربط المستخدمين الحاليين بأدوارهم (حسب عمود role)
        User::query()->each(function (User $user) {
            $role = in_array($user->role, ['admin', 'supervisor', 'citizen'], true) ? $user->role : 'citizen';
            $user->syncRoles([$role]);
        });
    }
}
