<?php

use App\Http\Controllers\Api\Admin\AccountTypeController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\DistrictController;
use App\Http\Controllers\Api\Admin\GovernorateController;
use App\Http\Controllers\Api\Admin\NahiyaController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\PlantCategoryController;
use App\Http\Controllers\Api\Admin\PlantController;
use App\Http\Controllers\Api\Admin\PlantReportController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CitizenAuthController;
use App\Http\Controllers\Api\CitizenPlantController;
use App\Http\Controllers\Api\CitizenProfileController;
use App\Models\AccountType;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Nahiya;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| مسارات المصادقة (Auth)
|--------------------------------------------------------------------------
*/
// تقديم ملفات الوسائط عبر API (يمر على middleware الـ CORS — ضروري لأن
// `php artisan serve` يخدم ملفات /storage الثابتة مباشرة بدون رؤوس CORS).
Route::get('media/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);
    abort_unless(\Illuminate\Support\Facades\Storage::disk('public')->exists($path), 404);

    return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
})->where('path', '.*');

// الحملات (عامة — تظهر للزوّار والمسجّلين)
Route::get('campaigns', [\App\Http\Controllers\Api\CampaignController::class, 'index']);
Route::get('campaigns/{slug}', [\App\Http\Controllers\Api\CampaignController::class, 'show']);
Route::get('leaderboard', [\App\Http\Controllers\Api\LeaderboardController::class, 'index']);
Route::get('contents/{key}', [\App\Http\Controllers\Api\AppContentController::class, 'show']);
Route::get('campaigns/{campaign}/participants', [\App\Http\Controllers\Api\CampaignController::class, 'participants']);

// نقاط عامّة تُستخدم في فورم التسجيل
Route::get('account-types/active', fn () => AccountType::where('is_active', true)->orderBy('name')->get(['id', 'name', 'icon']));
Route::get('plant-categories/active', fn () => \App\Models\PlantCategory::where('is_active', true)->orderBy('name')->get(['id', 'name', 'icon', 'color']));
Route::get('governorates/active', fn () => Governorate::where('is_active', true)->orderBy('name')->get(['id', 'name']));
Route::get('plants/map', function (\Illuminate\Http\Request $r) {
    $viewerId = optional($r->user('sanctum'))->id;

    $plants = \App\Models\Plant::query()
        ->select('id', 'name', 'type', 'description', 'planting_date', 'image_path', 'latitude', 'longitude', 'status', 'category_id', 'citizen_id', 'user_id', 'campaign_id')
        ->with(['category:id,name,color,icon', 'citizen:id,name,avatar', 'user:id,name,avatar', 'images', 'campaign:id,title'])
        ->withCount('likes')
        ->when($viewerId, fn ($q) => $q->with(['likes' => fn ($l) => $l->where('citizen_id', $viewerId)]))
        ->where('status', 'approved')
        ->when($r->filled('category_id'), fn ($q) => $q->where('category_id', $r->integer('category_id')))
        ->when($r->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $r->integer('governorate_id')))
        ->when($r->filled('campaign_id'), fn ($q) => $q->where('campaign_id', $r->integer('campaign_id')))
        ->when($r->filled('q'), fn ($q) => $q->where(function ($w) use ($r) {
            $term = '%'.$r->string('q').'%';
            $w->where('name', 'like', $term)->orWhere('type', 'like', $term);
        }))
        ->get();

    $features = $plants->map(function (\App\Models\Plant $p) {
        $owner = $p->citizen ?? $p->user;
        $ownerAvatar = $owner?->avatar ? url('api/media/'.$owner->avatar) : null;

        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [(float) $p->longitude, (float) $p->latitude]],
            'properties' => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->type,
                'description' => $p->description,
                'category' => $p->category?->name,
                'color' => $p->category?->color ?? '#2E7D32',
                'image' => $p->imageUrl(),
                'images' => $p->imageUrls(),
                'age_label' => $p->ageLabel(),
                'planting_date' => $p->planting_date?->toDateString(),
                'owner' => $owner?->name ?? '',
                'owner_avatar' => $ownerAvatar,
                'campaign' => $p->campaign?->title,
                'likes_count' => (int) ($p->likes_count ?? 0),
                'is_liked' => $p->relationLoaded('likes') ? $p->likes->isNotEmpty() : false,
            ],
        ];
    });

    return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
});

Route::get('banners', fn () => \App\Models\Banner::where('is_active', true)
    ->orderBy('sort_order')->orderBy('id')->get()
    ->map(fn (\App\Models\Banner $b) => ['id' => $b->id, 'url' => $b->url(), 'title' => $b->title, 'link' => $b->link]));

Route::get('districts/active', fn (\Illuminate\Http\Request $r) => District::where('is_active', true)
    ->when($r->filled('governorate_id'), fn ($q) => $q->where('governorate_id', $r->integer('governorate_id')))
    ->orderBy('name')->get(['id', 'name', 'governorate_id']));

Route::prefix('auth')->group(function () {
    // عامّة — محمية بحدود معدّل لمنع التخمين العشوائي (brute force)
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // محمية
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| مصادقة المواطن (جدول citizens منفصل)
|--------------------------------------------------------------------------
*/
Route::prefix('citizen/auth')->group(function () {
    Route::post('register', [CitizenAuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('verify-otp', [CitizenAuthController::class, 'verifyOtp'])->middleware('throttle:5,1');
    Route::post('resend-otp', [CitizenAuthController::class, 'resendOtp'])->middleware('throttle:6,1');
    Route::post('login', [CitizenAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [CitizenAuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('reset-password', [CitizenAuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [CitizenAuthController::class, 'logout']);
        Route::get('me', [CitizenAuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| إجراءات المواطن (محمية بتوكن sanctum — جدول citizens)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('citizen')->group(function () {
    Route::get('profile', [CitizenProfileController::class, 'show']);
    Route::post('profile', [CitizenProfileController::class, 'update']);
    Route::put('profile/password', [CitizenProfileController::class, 'updatePassword']);

    Route::get('my-plants', [CitizenPlantController::class, 'index']);
    Route::post('plants', [CitizenPlantController::class, 'store']);
    Route::get('plants/{plant}', [CitizenPlantController::class, 'show']);
    Route::put('plants/{plant}', [CitizenPlantController::class, 'update']);
    Route::delete('plants/{plant}', [CitizenPlantController::class, 'destroy']);
    Route::post('plants/{plant}/report', [CitizenPlantController::class, 'report']);
    Route::post('campaigns/{campaign}/join', [\App\Http\Controllers\Api\CampaignController::class, 'join']);
    Route::post('campaigns/{campaign}/leave', [\App\Http\Controllers\Api\CampaignController::class, 'leave']);
    Route::post('plants/{plant}/water', [CitizenPlantController::class, 'water']);
    Route::post('plants/{plant}/like', [CitizenPlantController::class, 'toggleLike'])->middleware('throttle:30,1');

    // حذف الحساب (امتثال لسياسات المتاجر)
    Route::get('account/deletion-request', [\App\Http\Controllers\Api\AccountDeletionController::class, 'status']);
    Route::post('account/deletion-request', [\App\Http\Controllers\Api\AccountDeletionController::class, 'store']);
    Route::delete('account/deletion-request', [\App\Http\Controllers\Api\AccountDeletionController::class, 'cancel']);
});

/*
|--------------------------------------------------------------------------
| مسارات لوحة الأدمن (محمية: مسجّل دخول + دور admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin', 'admin.audit'])->prefix('admin')->group(function () {
    // إحصائيات الصفحة الرئيسية
    Route::get('stats', [AdminStatsController::class, 'index']);

    // قوائم مبسّطة للقوائم المنسدلة والفلاتر
    Route::get('governorates/options', fn () => Governorate::orderBy('name')->get(['id', 'name', 'is_active']));
    Route::get('districts/options', fn () => District::with('governorate:id,name')->orderBy('name')->get(['id', 'name', 'governorate_id', 'is_active']));

    // المحافظات (مسارات الجملة قبل المسارات ذات المعرّف)
    Route::delete('governorates/bulk', [GovernorateController::class, 'bulkDestroy']);
    Route::patch('governorates/bulk/toggle', [GovernorateController::class, 'bulkToggle']);
    Route::patch('governorates/{governorate}/toggle', [GovernorateController::class, 'toggleVisibility']);
    Route::apiResource('governorates', GovernorateController::class);

    // الأقضية
    Route::delete('districts/bulk', [DistrictController::class, 'bulkDestroy']);
    Route::patch('districts/bulk/toggle', [DistrictController::class, 'bulkToggle']);
    Route::patch('districts/{district}/toggle', [DistrictController::class, 'toggleVisibility']);
    Route::apiResource('districts', DistrictController::class)->except('show');

    // النواحي
    Route::get('nahiyas/options', fn () => Nahiya::orderBy('name')->get(['id', 'name', 'district_id', 'is_active']));
    Route::delete('nahiyas/bulk', [NahiyaController::class, 'bulkDestroy']);
    Route::patch('nahiyas/bulk/toggle', [NahiyaController::class, 'bulkToggle']);
    Route::patch('nahiyas/{nahiya}/toggle', [NahiyaController::class, 'toggleVisibility']);
    Route::apiResource('nahiyas', NahiyaController::class)->except('show');

    // أنواع الحسابات
    Route::get('account-types/options', fn () => AccountType::orderBy('name')->get(['id', 'name', 'is_active']));
    Route::delete('account-types/bulk', [AccountTypeController::class, 'bulkDestroy']);
    Route::patch('account-types/bulk/toggle', [AccountTypeController::class, 'bulkToggle']);
    Route::patch('account-types/{account_type}/toggle', [AccountTypeController::class, 'toggleVisibility']);
    Route::apiResource('account-types', AccountTypeController::class)->except('show')->parameters(['account-types' => 'account_type']);

    // أصناف النباتات
    Route::get('plant-categories/options', fn () => \App\Models\PlantCategory::orderBy('name')->get(['id', 'name', 'icon', 'color', 'is_active']));
    Route::delete('plant-categories/bulk', [PlantCategoryController::class, 'bulkDestroy']);
    Route::patch('plant-categories/bulk/toggle', [PlantCategoryController::class, 'bulkToggle']);
    Route::patch('plant-categories/{plant_category}/toggle', [PlantCategoryController::class, 'toggleVisibility']);
    Route::apiResource('plant-categories', PlantCategoryController::class)->except('show')->parameters(['plant-categories' => 'plant_category']);

    // النباتات
    Route::get('plants/map', [PlantController::class, 'map']);
    Route::patch('plants/bulk/status', [PlantController::class, 'bulkStatus']);
    Route::delete('plants/bulk', [PlantController::class, 'bulkDestroy']);
    Route::patch('plants/{plant}/status', [PlantController::class, 'updateStatus']);
    Route::post('plants/{plant}/water', [PlantController::class, 'water']);
    Route::get('plants/{plant}/activities', [PlantController::class, 'activities']);
    Route::post('plants/{plant}/images', [PlantController::class, 'uploadImages']);
    Route::delete('plants/{plant}/images/{image}', [PlantController::class, 'destroyImage']);
    Route::apiResource('plants', PlantController::class)->only(['index', 'show', 'update', 'destroy']);

    // بلاغات النباتات (مسارات الجملة قبل المسارات ذات المعرّف)
    Route::patch('plant-reports/bulk/status', [PlantReportController::class, 'bulkStatus']);
    Route::delete('plant-reports/bulk', [PlantReportController::class, 'bulkDestroy']);
    Route::patch('plant-reports/{plant_report}/status', [PlantReportController::class, 'updateStatus']);
    Route::apiResource('plant-reports', PlantReportController::class)->only(['index', 'show', 'destroy'])->parameters(['plant-reports' => 'plant_report']);

    // الأدوار والصلاحيات
    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::apiResource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);

    // المستخدمون
    Route::delete('users/bulk', [UserController::class, 'bulkDestroy']);
    Route::patch('users/bulk/status', [UserController::class, 'bulkStatus']);
    Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::get('users/{user}/plants', [UserController::class, 'plants']);
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

    // المواطنون (جدول citizens منفصل)
    Route::delete('citizens/bulk', [\App\Http\Controllers\Api\Admin\CitizenController::class, 'bulkDestroy']);
    Route::patch('citizens/bulk/status', [\App\Http\Controllers\Api\Admin\CitizenController::class, 'bulkStatus']);
    Route::patch('citizens/{citizen}/toggle-status', [\App\Http\Controllers\Api\Admin\CitizenController::class, 'toggleStatus']);
    Route::get('citizens/{citizen}/plants', [\App\Http\Controllers\Api\Admin\CitizenController::class, 'plants']);
    Route::apiResource('citizens', \App\Http\Controllers\Api\Admin\CitizenController::class)->only(['index', 'show', 'destroy']);

    // الإشعارات
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/read', [NotificationController::class, 'markRead']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    // حملات التشجير
    Route::get('campaigns/{campaign}/participants', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'participants']);
    Route::get('campaigns/{campaign}/plants', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'plants']);
    Route::patch('campaigns/{campaign}/status', [\App\Http\Controllers\Api\Admin\CampaignController::class, 'updateStatus']);
    Route::apiResource('campaigns', \App\Http\Controllers\Api\Admin\CampaignController::class);

    // البنرات (سلايدر الصفحة الرئيسية بالتطبيق)
    Route::get('banners', [\App\Http\Controllers\Api\Admin\BannerController::class, 'index']);
    Route::post('banners', [\App\Http\Controllers\Api\Admin\BannerController::class, 'store']);
    Route::post('banners/reorder', [\App\Http\Controllers\Api\Admin\BannerController::class, 'reorder']);
    Route::patch('banners/{banner}/toggle', [\App\Http\Controllers\Api\Admin\BannerController::class, 'toggle']);
    Route::delete('banners/{banner}', [\App\Http\Controllers\Api\Admin\BannerController::class, 'destroy']);

    // محتوى التطبيق (سياسة الخصوصية + عن التطبيق)
    Route::get('contents', [\App\Http\Controllers\Api\Admin\AppContentController::class, 'index']);
    Route::get('contents/{key}', [\App\Http\Controllers\Api\Admin\AppContentController::class, 'show']);
    Route::put('contents/{key}', [\App\Http\Controllers\Api\Admin\AppContentController::class, 'update']);

    // طلبات حذف الحسابات
    Route::get('account-deletions', [\App\Http\Controllers\Api\Admin\AccountDeletionController::class, 'index']);
    Route::post('account-deletions/{accountDeletionRequest}/approve', [\App\Http\Controllers\Api\Admin\AccountDeletionController::class, 'approve']);
    Route::post('account-deletions/{accountDeletionRequest}/reject', [\App\Http\Controllers\Api\Admin\AccountDeletionController::class, 'reject']);

    // سجل العمليات
    Route::get('activity-log', [ActivityLogController::class, 'index']);
});
