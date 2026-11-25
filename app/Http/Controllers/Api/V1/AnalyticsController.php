<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Pwa;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group تحليلات واستخدام التطبيقات (Analytics)
 */
class AnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Record an analytics event (view, detail_visit, install_clicked, installed_confirmed, external_opened).
     */
    public function logEvent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pwa_id' => ['nullable', 'integer', 'exists:pwas,id'],
            'event_type' => ['required', 'string', 'in:view,detail_visit,install_clicked,installed_confirmed,external_opened'],
            'metadata' => ['nullable', 'array'],
        ]);

        $event = AnalyticsEvent::create([
            'pwa_id' => $validated['pwa_id'] ?? null,
            'user_id' => $request->user()?->id,
            'event_type' => $validated['event_type'],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'metadata' => $validated['metadata'] ?? null,
            'created_at' => now(),
        ]);

        // تحديث عدادات التطبيق حسب نوع الحدث
        if (! empty($validated['pwa_id'])) {
            if ($validated['event_type'] === 'installed_confirmed') {
                Pwa::where('id', $validated['pwa_id'])->increment('install_count');
            } elseif (in_array($validated['event_type'], ['view', 'detail_visit'], true)) {
                Pwa::where('id', $validated['pwa_id'])->increment('views_count');
            } elseif (in_array($validated['event_type'], ['install_clicked', 'external_opened'], true)) {
                Pwa::where('id', $validated['pwa_id'])->increment('clicks_count');
            }
        }

        return $this->successResponse(null, 'تم تسجيل الحدث بنجاح');
    }

    /**
     * Get aggregate analytics summary for a PWA (developer or admin only).
     */
    public function pwaStats(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pwa = Pwa::findOrFail($id);

        // التحقق من الصلاحيات: المطور المالك أو الإدارة
        if ($pwa->developer_id !== $user->id && ! $user->hasRole('admin')) {
            return $this->errorResponse('غير مصرح لك باستعراض إحصائيات هذا التطبيق', 403);
        }

        $eventsCount = AnalyticsEvent::where('pwa_id', $pwa->id)
            ->selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();

        return $this->successResponse([
            'pwa_id' => $pwa->id,
            'name' => $pwa->name,
            'install_count' => $pwa->install_count,
            'detail_visits' => $eventsCount['detail_visit'] ?? 0,
            'install_clicks' => $eventsCount['install_clicked'] ?? 0,
            'confirmed_installs' => $eventsCount['installed_confirmed'] ?? 0,
            'external_opens' => $eventsCount['external_opened'] ?? 0,
        ]);
    }
}

