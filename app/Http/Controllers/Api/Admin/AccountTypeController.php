<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountType\StoreAccountTypeRequest;
use App\Http\Requests\AccountType\UpdateAccountTypeRequest;
use App\Http\Resources\AccountTypeResource;
use App\Models\AccountType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountTypeController extends Controller
{
    use HandlesBulkActions;

    protected function bulkModel(): string
    {
        return AccountType::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AccountType::query()->withCount(['users', 'citizens']);

        $query->when($request->filled('q'), function ($q) use ($request) {
            $term = $request->string('q');
            $q->where('name', 'like', "%{$term}%");
        });

        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('is_active', $request->input('status') === 'active');
        });

        $sortBy = in_array($request->input('sortBy'), ['name', 'is_active', 'users_count', 'created_at'], true)
            ? $request->input('sortBy')
            : 'created_at';
        $order = $request->input('orderBy') === 'asc' ? 'asc' : 'desc';
        // "عدد المستخدمين" = مستخدمو التطبيق + الموظفون
        $sortBy === 'users_count'
            ? $query->orderByRaw("(citizens_count + users_count) $order")
            : $query->orderBy($sortBy, $order);

        $perPage = (int) $request->input('itemsPerPage', 10);
        $perPage = $perPage < 1 ? $query->count() ?: 1 : $perPage;

        return AccountTypeResource::collection($query->paginate($perPage))
            ->additional(['stats' => [
                'total' => AccountType::count(),
                'active' => AccountType::where('is_active', true)->count(),
                'hidden' => AccountType::where('is_active', false)->count(),
            ]]);
    }

    public function store(StoreAccountTypeRequest $request): JsonResponse
    {
        $type = AccountType::create($request->validated());

        return response()->json([
            'message' => 'تمت إضافة نوع الحساب بنجاح.',
            'data' => new AccountTypeResource($type->loadCount(['users', 'citizens'])),
        ], 201);
    }

    public function update(UpdateAccountTypeRequest $request, AccountType $accountType): JsonResponse
    {
        $accountType->update($request->validated());

        return response()->json([
            'message' => 'تم تحديث نوع الحساب بنجاح.',
            'data' => new AccountTypeResource($accountType->loadCount(['users', 'citizens'])),
        ]);
    }

    public function toggleVisibility(AccountType $accountType): JsonResponse
    {
        $accountType->update(['is_active' => ! $accountType->is_active]);

        return response()->json([
            'message' => $accountType->is_active ? 'تم إظهار النوع.' : 'تم إخفاء النوع.',
            'data' => new AccountTypeResource($accountType->loadCount(['users', 'citizens'])),
        ]);
    }

    public function destroy(AccountType $accountType): JsonResponse
    {
        $accountType->delete();

        return response()->json(['message' => 'تم حذف نوع الحساب.']);
    }
}
