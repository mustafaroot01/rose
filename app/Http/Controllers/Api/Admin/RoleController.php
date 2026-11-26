<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /** الأدوار الأساسية التي لا تُحذف. */
    private const CORE_ROLES = ['admin', 'citizen'];

    public function index(): AnonymousResourceCollection
    {
        // عدّ المستخدمين لكل دور مباشرة (تفادي مشكلة guard في withCount)
        $counts = DB::table('model_has_roles')
            ->where('model_type', (new User)->getMorphClass())
            ->groupBy('role_id')
            ->select('role_id', DB::raw('count(*) as aggregate'))
            ->pluck('aggregate', 'role_id');

        $roles = Role::query()->with('permissions')->orderBy('id')->get()
            ->each(fn (Role $r) => $r->users_count = (int) ($counts[$r->id] ?? 0));

        return RoleResource::collection($roles);
    }

    /**
     * كل الصلاحيات المتاحة (للمحرّر).
     */
    public function permissions(): JsonResponse
    {
        return response()->json(['data' => Permission::orderBy('id')->pluck('name')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        $role->users_count = 0;

        return response()->json([
            'message' => 'تمت إضافة الدور بنجاح.',
            'data' => new RoleResource($role->load('permissions')),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        // دور admin يحتفظ بكل الصلاحيات دائماً، ولا يُعاد تسميته
        if ($role->name === 'admin') {
            $role->syncPermissions(Permission::pluck('name')->all());
        } else {
            if (! in_array($role->name, self::CORE_ROLES, true)) {
                $role->update(['name' => $data['name']]);
            }

            $role->syncPermissions($data['permissions'] ?? []);
        }

        $role->users_count = $role->users()->count();

        return response()->json([
            'message' => 'تم تحديث الدور بنجاح.',
            'data' => new RoleResource($role->load('permissions')),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, self::CORE_ROLES, true)) {
            return response()->json(['message' => 'لا يمكن حذف دور أساسي.'], 422);
        }

        if ($role->users()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف دور مرتبط بمستخدمين.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'تم حذف الدور.']);
    }
}
