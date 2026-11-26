<?php

namespace App\Http\Middleware;

use App\Models\AdminActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يسجّل تلقائياً كل إجراءات التعديل (POST/PUT/PATCH/DELETE) التي يقوم بها المسؤولون
 * في لوحة التحكم، في جدول admin_activity_log — دون الحاجة لتعديل كل متحكم.
 */
class LogAdminActivity
{
    /** الطرق التي تُعدّل البيانات فقط. */
    private const MUTATING = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** تسميات عربية للأفعال حسب طريقة الطلب. */
    private const VERB = [
        'POST' => 'إنشاء',
        'PUT' => 'تعديل',
        'PATCH' => 'تعديل',
        'DELETE' => 'حذف',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // سجّل فقط عند نجاح إجراء تعديلي لمستخدم مسؤول مسجّل
        if (
            in_array($request->method(), self::MUTATING, true)
            && $response->getStatusCode() < 400
            && $request->user()
        ) {
            $this->record($request, $response);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $segments = $request->segments();                 // ['api','admin','campaigns','3','status']
        $resource = $segments[2] ?? 'غير معروف';           // الكيان المُستهدف
        $verb = self::VERB[$request->method()] ?? 'إجراء';

        // تجاهل تسجيل الدخول/الخروج والقراءات الحسّاسة
        if (in_array($resource, ['login', 'logout', 'stats', 'activity-log'], true)) {
            return;
        }

        // معرّف العنصر إن وُجد في المسار
        $targetId = null;
        foreach ($segments as $seg) {
            if (is_numeric($seg)) {
                $targetId = (int) $seg;
                break;
            }
        }

        $extra = '';
        if (str_ends_with($request->path(), '/status') || $request->has('status')) {
            $extra = ' (تغيير حالة)';
        }

        $description = trim("{$verb} {$resource}".($targetId ? " #{$targetId}" : '').$extra);

        try {
            AdminActivityLog::create([
                'user_id' => $request->user()->id,
                'action' => strtolower($request->method()).':'.$resource,
                'subject_type' => null,
                'subject_id' => $targetId,
                'description' => $description,
                'meta' => [
                    'method' => $request->method(),
                    'path' => $request->path(),
                ],
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // لا نُفشل الطلب الأساسي إن تعذّر التسجيل
        }
    }
}
