<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\PlantResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * قائمة المستخدمين — بحث + فلترة (دور/حالة/نوع/محافظة) + ترتيب + ترقيم.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with(['accountType', 'governorate', 'district', 'nahiya']);

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        });

        $rolesList = fn () => is_array($request->input('roles')) ? $request->input('roles') : explode(',', (string) $request->input('roles'));
        $query->when($request->filled('role'), fn ($q) => $q->where('role', $request->input('role')));
        $query->when($request->filled('roles'), fn ($q) => $q->whereIn('role', $rolesList()));
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));
        $query->when($request->filled('account_type_id'), fn ($q) => $q->where('account_type_id', $request->integer('account_type_id')));
        $query->when($request->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $request->integer('governorate_id')));

        $sortBy = in_array($request->input('sortBy'), ['name', 'role', 'status', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        // إحصائيات تحترم فلتر الدور (المواطنون / المشرفون)
        $roleScope = $request->input('role');
        $rolesScope = $request->input('roles');
        $rolesScopeList = is_array($rolesScope) ? $rolesScope : explode(',', (string) $rolesScope);
        $scoped = fn () => User::query()
            ->when($roleScope, fn ($q) => $q->where('role', $roleScope))
            ->when($rolesScope, fn ($q) => $q->whereIn('role', $rolesScopeList));

        return UserResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => $scoped()->count(),
                'active' => $scoped()->where('status', 'active')->count(),
                'blocked' => $scoped()->where('status', 'blocked')->count(),
                'verified' => $scoped()->whereNotNull('phone_verified_at')->count(),
            ]]);
    }

    /**
     * إضافة مشرف/مدير جديد — الهاتف يُعتبر موثّقاً عند الإنشاء الإداري.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'phone_verified_at' => now(),
        ]);
        $user->syncRoles([$user->role]); // مزامنة الدور مع مكتبة الصلاحيات

        return response()->json([
            'message' => 'تمت إضافة المستخدم بنجاح.',
            'data' => new UserResource($user->load(['accountType', 'governorate', 'district', 'nahiya'])),
        ], 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource(
            $user->load(['accountType', 'governorate', 'district', 'nahiya'])->loadCount('plants'),
        );
    }

    /**
     * مشاركات المستخدم (نباتاته) — مرقّمة.
     */
    public function plants(Request $request, User $user): AnonymousResourceCollection
    {
        $query = $user->plants()->with(['category', 'governorate'])->withCount('reports')->latest();

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return PlantResource::collection($query->paginate($perPage));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        $user->syncRoles([$user->role]); // مزامنة الدور مع مكتبة الصلاحيات

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح.',
            'data' => new UserResource($user->load(['accountType', 'governorate', 'district', 'nahiya'])),
        ]);
    }

    /**
     * حظر/تفعيل الحساب (toggle) — لا يسمح بحظر حسابك الشخصي.
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك حظر حسابك الشخصي.'], 422);
        }

        $user->update(['status' => $user->status === 'active' ? 'blocked' : 'active']);

        return response()->json([
            'message' => $user->status === 'active' ? 'تم تفعيل الحساب.' : 'تم حظر الحساب.',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الشخصي.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'تم حذف المستخدم.']);
    }

    /**
     * حذف جماعي — يستثني حسابك الشخصي.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $ids = array_diff($ids, [$request->user()->id]);
        $count = User::whereIn('id', $ids)->delete();

        return response()->json(['message' => "تم حذف {$count} مستخدم.", 'count' => $count]);
    }

    /**
     * تغيير حالة جماعي (حظر/تفعيل) — يستثني حسابك الشخصي.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'status' => ['required', Rule::in(['active', 'blocked'])],
        ]);

        $ids = array_diff($data['ids'], [$request->user()->id]);
        $count = User::whereIn('id', $ids)->update(['status' => $data['status']]);

        return response()->json([
            'message' => $data['status'] === 'active' ? "تم تفعيل {$count} مستخدم." : "تم حظر {$count} مستخدم.",
            'count' => $count,
        ]);
    }
}
