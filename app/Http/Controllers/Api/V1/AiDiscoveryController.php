<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PwaResource;
use App\Models\Pwa;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group مساعد استكشاف التطبيقات بالذكاء الاصطناعي (AI Discovery)
 */
class AiDiscoveryController extends Controller
{
    use ApiResponse;

    /**
     * Process natural language discovery request and recommend suitable PWAs from database.
     */
    public function discover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array'],
        ]);

        $prompt = trim($validated['prompt']);
        $normalizedPrompt = $this->normalizeText($prompt);

        // جلب جميع التطبيقات المنشورة من قاعدة البيانات
        $pwas = Pwa::published()
            ->with(['category', 'developer'])
            ->get();

        $scoredApps = [];

        foreach ($pwas as $pwa) {
            $normName = $this->normalizeText($pwa->name);
            $normShortDesc = $this->normalizeText($pwa->short_description ?? '');
            $normDesc = $this->normalizeText($pwa->description ?? '');
            $normCategory = $this->normalizeText($pwa->category?->name_ar ?? '') . ' ' . $this->normalizeText($pwa->category?->name ?? '');

            $score = 0;
            $reasons = [];
            $features = [];

            // 1. فحص مجالات الاهتمام الشائعة
            if (preg_match('/(مهام|مهمة|تنظيم|وقت|انجاز|بومودورو|task|todo|productivity)/iu', $normalizedPrompt)) {
                if (str_contains($normCategory, 'انتاج') || str_contains($normName, 'مهام') || str_contains($normCategory, 'productivity')) {
                    $score += 85;
                    $reasons[] = 'يتميز بأدوات تنظيم المهام وترتيب الأولويات اليومية';
                    $features[] = 'إدارة المهام اليومية';
                }
            }

            if (preg_match('/(مصاريف|مالي|ميزانية|نقود|حسابات|فلوس|finance|budget|money|expense)/iu', $normalizedPrompt)) {
                if (str_contains($normCategory, 'مال') || str_contains($normName, 'مصاريف') || str_contains($normCategory, 'finance')) {
                    $score += 90;
                    $reasons[] = 'يقدم تحليلاً دقيقاً للميزانية وتتبع المصاريف والعملات';
                    $features[] = 'تتبع النفقات اليومية';
                }
            }

            if (preg_match('/(شطرنج|لعبة|العاب|تسلية|ذكاء|game|play|chess)/iu', $normalizedPrompt)) {
                if (str_contains($normCategory, 'العاب') || str_contains($normCategory, 'games') || str_contains($normName, 'شطرنج')) {
                    $score += 90;
                    $reasons[] = 'يوفر تجربة ألعاب ذهنية واستراتيجية ممتازة بدون تحميل ثقيل';
                    $features[] = 'لعب فوري بدون تثبيت';
                }
            }

            if (preg_match('/(قران|اسلام|دين|ايات|تدبر|ذكر|quran|islamic)/iu', $normalizedPrompt)) {
                if (str_contains($normName, 'قران') || str_contains($normShortDesc, 'قران') || str_contains($normDesc, 'ايات')) {
                    $score += 95;
                    $reasons[] = 'تطبيق إسلامي موثوق مع قراءة وتدبر الآيات الكريمة';
                    $features[] = 'قراءة وتدبر الآيات';
                }
            }

            if (preg_match('/(محادثة|شات|تواصل|مجلس|نقاش|community|chat|social)/iu', $normalizedPrompt)) {
                if (str_contains($normCategory, 'اجتماع') || str_contains($normName, 'مجلس') || str_contains($normCategory, 'social')) {
                    $score += 85;
                    $reasons[] = 'منصة تفاعلية ممتازة للمناقشات وتبادل الأفكار';
                    $features[] = 'غرف نقاش حية';
                }
            }

            // 2. مطابقة الكلمات المفتاحية المباشرة
            $words = array_filter(explode(' ', $normalizedPrompt), fn ($w) => mb_strlen($w) > 2);
            foreach ($words as $word) {
                if (str_contains($normName, $word)) {
                    $score += 30;
                }
                if (str_contains($normShortDesc, $word)) {
                    $score += 15;
                }
                if (str_contains($normCategory, $word)) {
                    $score += 20;
                }
            }

            // إضافة نقاط لتقييم التطبيق
            if ($pwa->rating_average > 0) {
                $score += min(15, (int) ($pwa->rating_average * 3));
            }

            if ($score >= 25) {
                $confidence = min(99, max(65, $score));
                $reasonText = ! empty($reasons)
                    ? implode('، و', $reasons)
                    : 'مطابق لاهتماماتك في فئة ' . ($pwa->category?->name_ar ?? 'التطبيقات العامة');

                $scoredApps[] = [
                    'app' => new PwaResource($pwa),
                    'reason' => $reasonText,
                    'confidenceScore' => $confidence,
                    'highlightedFeatures' => ! empty($features) ? $features : ['تطبيق ويب تقدمي', 'خفيف وسريع'],
                    'score' => $score,
                ];
            }
        }

        // ترتيب النتائج حسب الدرجة الأعلى
        usort($scoredApps, fn ($a, $b) => $b['score'] <=> $a['score']);
        $recommendations = array_slice($scoredApps, 0, 3);

        $count = count($recommendations);
        if ($count > 0) {
            $answerText = "عثرت لك على {$count} من أفضل تطبيقات الويب التقدمية المناسبة لطلبك بدقة من دليل متجرنا:";
        } else {
            $answerText = "لم أجد تطبيقاً يطابق طلبك بدقة، ولكن يمكنك استكشاف مئات التطبيقات المتاحة في مختلف التصنيفات، أو تجربة صياغة طلبك بطريقة أخرى.";
        }

        return $this->successResponse([
            'answerText' => $answerText,
            'recommendations' => $recommendations,
            'suggestedChips' => [
                'أريد تطبيقاً لتنظيم مهامي اليومية',
                'أريد تطبيقاً لإدارة مصاريفي',
                'أبحث عن لعبة ذكاء وشطرنج',
                'أحتاج تطبيقاً تعليمياً',
            ],
        ]);
    }

    /**
     * Helper to normalize Arabic and English text for accurate matching.
     */
    private function normalizeText(?string $str): string
    {
        if (blank($str)) {
            return '';
        }

        $str = mb_strtolower($str, 'UTF-8');
        // تطبيع أشكال الألف والهمزات
        $str = preg_replace('/[أإآ]/u', 'ا', $str);
        $str = preg_replace('/ة/u', 'ه', $str);
        $str = preg_replace('/ى/u', 'ي', $str);
        // إزالة علامات التشكيل
        $str = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $str);

        return trim($str);
    }
}

