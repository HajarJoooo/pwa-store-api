<?php

namespace Database\Factories;

use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected static array $sampleReviews = [
        ['rating' => 5, 'title' => 'تطبيق فائق السرعة ومريح جداً', 'content' => 'أفضل ما فيه سرعة الفتح وعدم استهلاك المساحة على الهاتف، الواجهة سلسة ومريحة للعين.'],
        ['rating' => 5, 'title' => 'فكرة ممتازة وتنفيذ متقن', 'content' => 'استغنيت به عن التطبيق الأصلي الثقيل، الإشعارات تعمل بشكل رائع والتجربة العامة ممتازة.'],
        ['rating' => 4, 'title' => 'تجربة ممتازة مع بعض الملاحظات', 'content' => 'التطبيق سريع جداً وخفيف، أتمنى إضافة المزيد من خيارات التخصيص في التحديث القادم.'],
        ['rating' => 4, 'title' => 'جيد جداً ويلبي الغرض بالكامل', 'content' => 'يعمل دون أي بطء ويحفظ البيانات بشكل ممتاز، شكراً للمطور على هذا العمل.'],
        ['rating' => 3, 'title' => 'تطبيق واعد ولكنه يحتاج تحسينات', 'content' => 'الأداء جيد عموماً، لكن واجهة المستخدم تحتاج بعض التعديلات لتصبح أكثر عصرية.'],
        ['rating' => 2, 'title' => 'يواجه بعض التوقف عند ضعف الشبكة', 'content' => 'فكرة PWA ممتازة لكن يحتاج دعم أفضل للعمل في وضع عدم الاتصال.'],
        ['rating' => 5, 'title' => 'رائع وخفيف على موارد الجهاز', 'content' => 'يعمل بكفاءة عالية وبطارية الجهاز لا تتأثر مطلقاً. أنصح به بشدة لكل مستخدم.'],
        ['rating' => 4, 'title' => 'تصميم عربي أنيق وخطوط واضحة', 'content' => 'الاهتمام بالتفاصيل واللغة العربية ملحوظ وجميل، تجربة مستخدم موفقة.'],
    ];

    public function definition(): array
    {
        $review = fake()->randomElement(self::$sampleReviews);

        return [
            'pwa_id' => Pwa::factory(),
            'user_id' => User::factory(),
            'title' => $review['title'],
            'content' => $review['content'],
            'rating' => $review['rating'],
            'helpful_count' => fake()->numberBetween(0, 30),
            'is_approved' => true,
        ];
    }
}
