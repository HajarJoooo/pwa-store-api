<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Games',
                'name_ar' => 'ألعاب',
                'slug' => 'games',
                'description' => 'أفضل ألعاب الويب التقدمية الخفيفة والممتعة المتاحة للعب فوراً بدون تحميل مساحة إضافية.',
                'icon_url' => 'https://api.iconify.design/lucide:gamepad-2.svg',
                'sort_order' => 1,
            ],
            [
                'name' => 'Education',
                'name_ar' => 'تعليم',
                'slug' => 'education',
                'description' => 'تطبيقات تعليمية، دورات تدريبية، وأدوات تعلّم اللغات والعلوم.',
                'icon_url' => 'https://api.iconify.design/lucide:graduation-cap.svg',
                'sort_order' => 2,
            ],
            [
                'name' => 'Productivity',
                'name_ar' => 'إنتاجية',
                'slug' => 'productivity',
                'description' => 'أدوات إدارة المهام، تدوين الملاحظات، وجدولة المواعيد لزيادة فاعليتك اليومية.',
                'icon_url' => 'https://api.iconify.design/lucide:check-square.svg',
                'sort_order' => 3,
            ],
            [
                'name' => 'Social Media',
                'name_ar' => 'تواصل اجتماعي',
                'slug' => 'social',
                'description' => 'منصات التواصل، المحادثات، وبناء المجتمعات الرقمية.',
                'icon_url' => 'https://api.iconify.design/lucide:users.svg',
                'sort_order' => 4,
            ],
            [
                'name' => 'Entertainment',
                'name_ar' => 'ترفيه',
                'slug' => 'entertainment',
                'description' => 'تطبيقات البودكاست، الموسيقى، الفيديوهات والراديو.',
                'icon_url' => 'https://api.iconify.design/lucide:tv.svg',
                'sort_order' => 5,
            ],
            [
                'name' => 'News',
                'name_ar' => 'أخبار',
                'slug' => 'news',
                'description' => 'متابعة الأخبار العربية والعالمية لحظة بلحظة مع تجربة قراءة سريعة.',
                'icon_url' => 'https://api.iconify.design/lucide:newspaper.svg',
                'sort_order' => 6,
            ],
            [
                'name' => 'Shopping',
                'name_ar' => 'تسوق',
                'slug' => 'shopping',
                'description' => 'متاجر إلكترونية تقدمية للتسوق وشراء المنتجات بسهولة وسرعة فائقة.',
                'icon_url' => 'https://api.iconify.design/lucide:shopping-bag.svg',
                'sort_order' => 7,
            ],
            [
                'name' => 'Health & Fitness',
                'name_ar' => 'صحة ولياقة',
                'slug' => 'health-fitness',
                'description' => 'تتبع التمارين، الحمية الغذائية، والصحة العامة والنفسية.',
                'icon_url' => 'https://api.iconify.design/lucide:heart-pulse.svg',
                'sort_order' => 8,
            ],
            [
                'name' => 'Tools',
                'name_ar' => 'أدوات',
                'slug' => 'tools',
                'description' => 'حاسبات، محولات عملات ووحدات، وأدوات مساعدة يومية.',
                'icon_url' => 'https://api.iconify.design/lucide:wrench.svg',
                'sort_order' => 9,
            ],
            [
                'name' => 'Finance',
                'name_ar' => 'مالية',
                'slug' => 'finance',
                'description' => 'إدارة الميزانية الشخصية، تتبع المصروفات، وأدوات الاستثمار والعملات.',
                'icon_url' => 'https://api.iconify.design/lucide:wallet.svg',
                'sort_order' => 10,
            ],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(
                ['slug' => $cat['slug']],
                array_merge($cat, ['is_active' => true])
            );
        }
    }
}
