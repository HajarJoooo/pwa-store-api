<?php

namespace Database\Seeders;

use App\Enums\PwaStatus;
use App\Models\Category;
use App\Models\Installation;
use App\Models\Pwa;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class PwaSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $developers = User::role('developer')->get();
        $moderator = User::role('moderator')->first();
        $users = User::role('user')->get();
        $categories = Category::all()->keyBy('slug');

        if ($developers->isEmpty() || $categories->isEmpty()) {
            return;
        }

        Pwa::unguard();
        Review::unguard();

        $apps = [
            [
                'name' => 'صلاتي ونبض القبلة',
                'slug' => 'salati-qibla',
                'category_slug' => 'tools',
                'short_description' => 'مواقيت الصلاة الدقيقة وتحديد اتجاه القبلة مع تنبيهات الأذان بدون إنترنت.',
                'description' => 'تطبيق ويب تقدمي خفيف جداً يتيح لك معرفة مواقيت الصلاة في أي مدينة عربية، مع بوصلة القبلة التفاعلية ودعم العمل بدون اتصال بالشبكة كلياً.',
                'manifest_url' => 'https://salati.pwa.app/manifest.json',
                'start_url' => 'https://salati.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:compass.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'نبض الأخبار العربية',
                'slug' => 'nabd-news-ar',
                'category_slug' => 'news',
                'short_description' => 'أبرز الأخبار العاجلة والمحلية من مصادر موثوقة بتجربة قراءة فائقة السرعة.',
                'description' => 'منصة إخبارية تجمع لك الأحداث الهامة لحظة بلحظة مع ميزة القراءة الليلية وتوفير استهلاك البيانات.',
                'manifest_url' => 'https://nabd.pwa.app/manifest.json',
                'start_url' => 'https://nabd.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:newspaper.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'منجز - إدارة المهام',
                'slug' => 'monjez-tasks',
                'category_slug' => 'productivity',
                'short_description' => 'نظم يومك، رتب أولوياتك، وأنجز مشاريعك بسلاسة مع تقنية البومودورو.',
                'description' => 'تطبيق إنتاجية عربي متكامل يحتوي على قوائم مهام، تصنيفات للمشاريع، وتقارير أسبوعية للأداء اليومي مع دعم التخزين المحلي.',
                'manifest_url' => 'https://monjez.pwa.app/manifest.json',
                'start_url' => 'https://monjez.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:check-circle-2.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'تحدي الكلمات الذكية',
                'slug' => 'word-challenge-ar',
                'category_slug' => 'games',
                'short_description' => 'لعبة كلمات متقاطعة وألغاز لغوية يومية تختبر ذكاءك ومفرداتك العربية.',
                'description' => 'لعبة ألغاز عربية ممتعة تناسب جميع أفراد العائلة، مع لغز يومي جديد ولوحة شرف لأعلى النتائج.',
                'manifest_url' => 'https://words.pwa.app/manifest.json',
                'start_url' => 'https://words.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:gamepad-2.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'دروب لتعليم البرمجة',
                'slug' => 'doroob-learn',
                'category_slug' => 'education',
                'short_description' => 'دروس تفاعلية وتمارين كود فورية لتعلم تطوير الويب باللغة العربية.',
                'description' => 'أكاديمية تعليمية تفاعلية مصغرة تركز على أساسيات البرمجة، مع بيئة كتابة كود مباشرة داخل المتصفح.',
                'manifest_url' => 'https://doroob.pwa.app/manifest.json',
                'start_url' => 'https://doroob.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:code-2.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'حاسبة الزكاة والمواريث',
                'slug' => 'zakat-calculator',
                'category_slug' => 'finance',
                'short_description' => 'احسب زكاة مالك، الذهب، والأسهم وعروض التجارة بدقة شرعية وسهولة.',
                'description' => 'حاسبة مالية مخصصة لحساب مختلف أنواع الزكاة الشرعية وتوزيع التركات وفق الضوابط الفقهية المعتمدة.',
                'manifest_url' => 'https://zakat.pwa.app/manifest.json',
                'start_url' => 'https://zakat.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:calculator.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'سوق الدار للتسوق',
                'slug' => 'souq-al-dar',
                'category_slug' => 'shopping',
                'short_description' => 'متجر إلكتروني محلي للمنتجات الحرفية واليدوية العربية بجودة عالية.',
                'description' => 'منصة تسوق تربط الحرفيين المحليين بالمشترين مباشرة بتجربة شراء سريعة وتدعم الدفع المباشر.',
                'manifest_url' => 'https://souqaldar.pwa.app/manifest.json',
                'start_url' => 'https://souqaldar.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:shopping-cart.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'خطواتي - للياقة البدنية',
                'slug' => 'khotwati-fitness',
                'category_slug' => 'health-fitness',
                'short_description' => 'تتبع المشي، استهلاك الماء، وحرق السعرات الحرارية بهدف صحي يومي.',
                'description' => 'مساعد صحي يومي يساعدك على شرب الماء بانتظام ومتابعة التمارين الخفيفة لتحقيق نمط حياة نشط.',
                'manifest_url' => 'https://khotwati.pwa.app/manifest.json',
                'start_url' => 'https://khotwati.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:activity.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'أثير البودكاست العربي',
                'slug' => 'atheer-podcast',
                'category_slug' => 'entertainment',
                'short_description' => 'استمع لأفضل البرامج الصوتية والبودكاست في شتى المجالات بدون إعلانات مزعجة.',
                'description' => 'مشغل بودكاست متقدم يتيح لك تحميل الحلقات للاستماع أثناء التنقل مع ميزة تسريع الصوت وتخطي الصمت.',
                'manifest_url' => 'https://atheer.pwa.app/manifest.json',
                'start_url' => 'https://atheer.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:headphones.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'مجلس - ديوانية النقاش',
                'slug' => 'majlis-community',
                'category_slug' => 'social',
                'short_description' => 'مجتمعات تخصصية للنقاش في التقنية، الأدب، وريادة الأعمال.',
                'description' => 'مساحة عربية هادئة لتبادل الأفكار والنقاشات المعمقة مع متابعين يشاركونك نفس الاهتمامات.',
                'manifest_url' => 'https://majlis.pwa.app/manifest.json',
                'start_url' => 'https://majlis.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:message-square.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'تدبر آيات القرآن الكريم',
                'slug' => 'tadabor-quran',
                'category_slug' => 'education',
                'short_description' => 'تفسير الآيات، معاني الكلمات، ووقفات تدبرية يومية لتعزيز الفهم والتدبر.',
                'description' => 'مصحف تدبري سهل التصفح مع تفاسير معتمدة وبحث فوري في نص القرآن الكريم كاملاً دون إنترنت.',
                'manifest_url' => 'https://tadabor.pwa.app/manifest.json',
                'start_url' => 'https://tadabor.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:book-open.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'مصاريفي - تتبع الميزانية',
                'slug' => 'masareefi-budget',
                'category_slug' => 'finance',
                'short_description' => 'سجل مصروفاتك اليومية واطلع على رسوم بيانية توضح بنود إنفاقك الشهرية.',
                'description' => 'طريقة سهلة وبسيطة للتحكم في أموالك بدون تعقيدات، مع دعم كامل للعملات الخليجية والعربية.',
                'manifest_url' => 'https://masareefi.pwa.app/manifest.json',
                'start_url' => 'https://masareefi.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:pie-chart.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'شطرنج العرب التكتيكي',
                'slug' => 'arab-chess-tactics',
                'category_slug' => 'games',
                'short_description' => 'العب الشطرنج ضد الذكاء الاصطناعي أو حل مسائل تكتيكية يومية.',
                'description' => 'تطبيق تكتيكي لمحبي لعبة الشطرنج لتقوية مهارات افتتاحيات ونهايات الأدوار.',
                'manifest_url' => 'https://chess.pwa.app/manifest.json',
                'start_url' => 'https://chess.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:shield.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            [
                'name' => 'محول العملات والوحدات الفوري',
                'slug' => 'unit-currency-converter',
                'category_slug' => 'tools',
                'short_description' => 'تحويل أسعار صرف العملات والقياسات الفيزيائية بضغطة زر واحدة.',
                'description' => 'تطبيق أداة شامل لتحويل أسعار العملات العالمية والوحدات المترية بدقة وتحديث مستمر.',
                'manifest_url' => 'https://converter.pwa.app/manifest.json',
                'start_url' => 'https://converter.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:repeat.svg',
                'status' => PwaStatus::Approved,
                'is_published' => true,
            ],
            // 4 Pending Apps
            [
                'name' => 'يومياتي السريعة',
                'slug' => 'yawmiyati-diary',
                'category_slug' => 'productivity',
                'short_description' => 'تدوين المذكرات واليوميات مع تشفير تام وكلمة مرور.',
                'description' => 'تطبيق لتدوين الملاحظات واليوميات مع حماية الخصوصية ومزامنة محلية.',
                'manifest_url' => 'https://diary.pwa.app/manifest.json',
                'start_url' => 'https://diary.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:lock.svg',
                'status' => PwaStatus::Pending,
                'is_published' => false,
            ],
            [
                'name' => 'أصوات الطبيعة للاسترخاء',
                'slug' => 'nature-sounds-relax',
                'category_slug' => 'entertainment',
                'short_description' => 'أصوات مطر، بحر، ورياح للمساعدة على النوم والتركيز أثناء العمل.',
                'description' => 'مؤثرات صوتية هادئة تعزز الاسترخاء وتزيد من ساعات التركيز العميق.',
                'manifest_url' => 'https://relax.pwa.app/manifest.json',
                'start_url' => 'https://relax.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:waves.svg',
                'status' => PwaStatus::Pending,
                'is_published' => false,
            ],
            [
                'name' => 'سوق العقارات الذكي',
                'slug' => 'smart-real-estate',
                'category_slug' => 'shopping',
                'short_description' => 'تصفح عروض بيع وإيجار العقارات والشقق في المدن الكبرى.',
                'description' => 'منصة عقارية حديثة تتيح استعراض المخططات والصور وتفاصيل المشاريع السكنية.',
                'manifest_url' => 'https://aqar.pwa.app/manifest.json',
                'start_url' => 'https://aqar.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:home.svg',
                'status' => PwaStatus::Pending,
                'is_published' => false,
            ],
            [
                'name' => 'دليلي الطبي السريع',
                'slug' => 'medical-guide-quick',
                'category_slug' => 'health-fitness',
                'short_description' => 'إرشادات الإسعافات الأولية ودليل المستشفيات وأرقام الطوارئ.',
                'description' => 'مرجع طبي سريع للإسعافات الأولية والتصرف السليم أثناء الحالات الطارئة.',
                'manifest_url' => 'https://medical.pwa.app/manifest.json',
                'start_url' => 'https://medical.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:cross.svg',
                'status' => PwaStatus::Pending,
                'is_published' => false,
            ],
            // 1 Draft
            [
                'name' => 'مشروع أداة قيد التطوير',
                'slug' => 'wip-tool-project',
                'category_slug' => 'tools',
                'short_description' => 'مسودة أداة مبرمجين لا تزال قيد البرمجة والتجربة.',
                'description' => 'تطبيق لم يتم إكماله بعد من قبل المطور، مخصص لاختبار واجهة برمجة التطبيقات.',
                'manifest_url' => 'https://wip.pwa.app/manifest.json',
                'start_url' => 'https://wip.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:terminal.svg',
                'status' => PwaStatus::Draft,
                'is_published' => false,
            ],
            // 2 Rejected
            [
                'name' => 'تطبيق مخالف لسياسة الخصوصية',
                'slug' => 'spam-ad-app',
                'category_slug' => 'tools',
                'short_description' => 'تطبيق يطلب صلاحيات مفرطة مع وجود إعلانات منبثقة غير مصرح بها.',
                'description' => 'تم رفض هذا التطبيق لمخالفته إرشادات الأمان وسياسة حماية بيانات المستخدمين.',
                'manifest_url' => 'https://spam.pwa.app/manifest.json',
                'start_url' => 'https://spam.pwa.app/',
                'icon_url' => 'https://api.iconify.design/lucide:alert-triangle.svg',
                'status' => PwaStatus::Rejected,
                'rejection_reason' => 'التطبيق يطلب صلاحيات مفرطة غير مبررة ويحتوي على روابط إعلانية مشبوهة.',
                'is_published' => false,
            ],
        ];

        foreach ($apps as $index => $appData) {
            $developer = $developers[$index % $developers->count()];
            $category = $categories[$appData['category_slug']] ?? $categories->first();

            $pwa = Pwa::updateOrCreate(
                ['slug' => $appData['slug']],
                [
                    'developer_id' => $developer->id,
                    'category_id' => $category->id,
                    'name' => $appData['name'],
                    'short_description' => $appData['short_description'],
                    'description' => $appData['description'],
                    'manifest_url' => $appData['manifest_url'],
                    'start_url' => $appData['start_url'],
                    'icon_url' => $appData['icon_url'],
                    'screenshots' => [
                        "https://picsum.photos/seed/{$appData['slug']}1/1080/1920",
                        "https://picsum.photos/seed/{$appData['slug']}2/1080/1920",
                    ],
                    'version' => '1.0.0',
                    'status' => $appData['status'],
                    'rejection_reason' => $appData['rejection_reason'] ?? null,
                    'reviewed_by' => in_array($appData['status'], [PwaStatus::Approved, PwaStatus::Rejected], true) && $moderator ? $moderator->id : null,
                    'reviewed_at' => in_array($appData['status'], [PwaStatus::Approved, PwaStatus::Rejected], true) ? now() : null,
                    'is_published' => $appData['is_published'],
                    'install_count' => 0,
                    'rating_average' => 0,
                    'review_count' => 0,
                ]
            );

            // Add some installations and reviews for approved apps
            if ($pwa->status === PwaStatus::Approved && $users->isNotEmpty()) {
                $sampleUsers = $users->random(min(rand(3, 7), $users->count()));
                $ratings = [];
                $sampleReviews = [
                    ['rating' => 5, 'title' => 'تطبيق فائق السرعة ومريح جداً', 'content' => 'أفضل ما فيه سرعة الفتح وعدم استهلاك المساحة على الهاتف، الواجهة سلسة ومريحة للعين.'],
                    ['rating' => 5, 'title' => 'فكرة ممتازة وتنفيذ متقن', 'content' => 'استغنيت به عن التطبيق الأصلي الثقيل، الإشعارات تعمل بشكل رائع والتجربة العامة ممتازة.'],
                    ['rating' => 4, 'title' => 'تجربة ممتازة مع بعض الملاحظات', 'content' => 'التطبيق سريع جداً وخفيف، أتمنى إضافة المزيد من خيارات التخصيص في التحديث القادم.'],
                    ['rating' => 4, 'title' => 'جيد جداً ويلبي الغرض بالكامل', 'content' => 'يعمل دون أي بطء ويحفظ البيانات بشكل ممتاز، شكراً للمطور على هذا العمل.'],
                    ['rating' => 5, 'title' => 'رائع وخفيف على موارد الجهاز', 'content' => 'يعمل بكفاءة عالية وبطارية الجهاز لا تتأثر مطلقاً. أنصح به بشدة لكل مستخدم.'],
                    ['rating' => 4, 'title' => 'تصميم عربي أنيق وخطوط واضحة', 'content' => 'الاهتمام بالتفاصيل واللغة العربية ملحوظ وجميل، تجربة مستخدم موفقة.'],
                ];

                foreach ($sampleUsers as $userIndex => $user) {
                    // Installation
                    Installation::firstOrCreate([
                        'user_id' => $user->id,
                        'pwa_id' => $pwa->id,
                    ], [
                        'installed_at' => now()->subDays(rand(1, 30)),
                    ]);

                    // Review (for some of them)
                    if ($userIndex % 2 === 0) {
                        $revTemplate = $sampleReviews[$userIndex % count($sampleReviews)];
                        $ratings[] = $revTemplate['rating'];

                        Review::updateOrCreate([
                            'pwa_id' => $pwa->id,
                            'user_id' => $user->id,
                        ], [
                            'title' => $revTemplate['title'],
                            'content' => $revTemplate['content'],
                            'rating' => $revTemplate['rating'],
                            'helpful_count' => rand(1, 10),
                            'is_approved' => true,
                        ]);
                    }
                }

                $reviewCount = count($ratings);
                $pwa->update([
                    'install_count' => $sampleUsers->count(),
                    'review_count' => $reviewCount,
                    'rating_average' => $reviewCount > 0 ? round(array_sum($ratings) / $reviewCount, 2) : 5.00,
                ]);
            }
        }

        Pwa::reguard();
        Review::reguard();
    }
}
