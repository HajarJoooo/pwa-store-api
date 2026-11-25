# متجر تطبيقات الويب التقدمية - واجهة برمجة التطبيقات (PWA Store API)

واجهة برمجة تطبيقات خلفية (RESTful API) متكاملة مبنية باستخدام **Laravel 13** و **PHP 8.5**، مصممة لإدارة متجر تطبيقات الويب التقدمية (Progressive Web Apps)، مع دعم كامل للغة العربية أولاً (Arabic-First)، بنية أمان متعددة الطبقات، وإدارة أدوار وصلاحيات دقيقة عبر **Spatie Permission** و **Laravel Sanctum**.

---

##  مميزات المشروع

- **بنية برمجية نظيفة (Clean Architecture):** فصل طبقات التحكم عبر Services و Form Requests و API Resources و Traits الموحدة.
- **تعدد الأدوار والصلاحيات (RBAC):** 4 أدوار أساسية (`user`، `developer`، `moderator`، `admin`) مع 9 صلاحيات محددة.
- **دورة حياة التطبيقات (PWA Lifecycle):** تقديم المطور للتطبيق (`pending`) ← فحص ومراجعة المشرف ← الموافقة والنشر التلقائي (`approved` / `is_published: true`) أو الرفض مع سبب مطهر (`rejected`).
- **تثبيت التطبيقات (Idempotent Installations):** تسجيل تثبيتات المستخدمين بدون تكرار عدادات التثبيت مع تتبع عناوين IP و User-Agent.
- **المراجعات والتقييمات:** مراجعة واحدة لكل مستخدم مع إعادة حساب تلقائي لمتوسط التقييم بدقة منزلتين عشريتين (`rating_average`).
- **أداء استثنائي وتخزين مؤقت:** استعلامات مُحسنة لمنع N+1 بالكامل (<= 2-3 استعلامات لكل مسار)، وتخزين مؤقت لشجرة التصنيفات (`categories_tree_public`) مع إبطال ذكي وفوري عند نشر أو تعديل أي تطبيق.
- **الأمان والتقوية (Hardening):**
  - ترويسات أمنية عالمية (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`).
  - سياسة CORS مخصصة تُقرأ من البيئة مع حظر النجمة `*` في الإنتاج.
  - Rate Limiting صارم (30 طلب/دقيقة لمسارات الكتابة، 5 طلبات/دقيقة لتسجيل الدخول).
  - حماية كاملة ضد Mass Assignment وتطهير مدخلات المشرفين ضد XSS.
  - أمر Artisan آمن لإنشاء المشرفين في الإنتاج `app:create-admin` بكلمة مرور عالية التعقيد (>= 12 خانة).
  - حارس أمني في الاختبارات يمنع الاتصال بقاعدة بيانات TiDB Cloud الإنتاجية نهائياً.

---

##  المتطلبات البرمجية

- **PHP:** الإصدار 8.5 فما فوق مع الامتدادات الأساسية (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `curl`, `json`, `intl`, `bcmath`).
- **Composer:** الإصدار 2 فما فوق.
- **قاعدة البيانات:**
  - **التطوير المحلي والاختبارات:** SQLite (افتراضي) أو MySQL 8.
  - **الإنتاج:** TiDB Cloud v8.5+ Serverless (متوافق مع MySQL).
- **Docker & Docker Compose:** (اختياري) لتشغيل بيئة اختبار MySQL 8 محلية.

---

##  التثبيت والتشغيل المحلي

### 1. استنساخ المستودع وتثبيت الاعتماديات
```bash
git clone https://github.com/HassanAlmaliki/pwa-store-api.git
cd pwa_store
composer install
```

### 2. إعداد ملف البيئة
```bash
cp .env.example .env
php artisan key:generate
```

تأكد من ضبط متغيرات البيئة في `.env`:
```env
APP_NAME="PWA Store"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_LOCALE=ar

# الاتصال بقاعدة البيانات المحلية (افتراضياً SQLite للتطوير السريع):
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

# نطاقات CORS المسموحة
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
```

إن كنت تستخدم SQLite محلياً، أنشئ الملف:
```bash
touch database/database.sqlite
```

### 3. تشغيل الـ Migrations والـ Seeders
```bash
php artisan migrate --seed
```

> **ملاحظة أمنية:** الـ Seeders التجريبية للبيانات الوهمية (`UserSeeder` و `PwaSeeder`) معطلة تلقائياً في بيئة الإنتاج (`production`). في بيئة الإنتاج يتم تشغيل `RoleSeeder` و `PermissionSeeder` و `CategorySeeder` فقط.

### 4. إنشاء حساب مدير أو مشرف في الإنتاج (بأمان)
لإنشاء حساب إداري بكلمة مرور قوية ومخفية:
```bash
php artisan app:create-admin --role=admin
```
أو لإنشاء حساب مشرف:
```bash
php artisan app:create-admin --role=moderator
```

### 5. تشغيل خادم التطوير
```bash
php artisan serve
```
سيكون الخادم متاحاً على الرابط: `http://localhost:8000`

---

##  الاختبارات الآلية (Testing Suite)

يحتوي المشروع على مجموعة اختبارات شاملة تغطي كافة السيناريوهات ودورات الحياة (79+ اختباراً و 500+ تأكيداً).

### تشغيل الاختبارات الافتراضية (SQLite في الذاكرة)
```bash
php artisan test
```

### تشغيل الاختبارات على خادم MySQL 8 محلي (Docker)
وفرنا ملف `docker-compose.test.yml` مخصصاً لتشغيل حاوية MySQL 8 اختبارية سريعة عبر `tmpfs`:

1. تشغيل حاوية MySQL الاختبارية:
```bash
docker compose -f docker-compose.test.yml up -d
```
2. تشغيل الاختبارات عبر اتصال `testing_mysql`:
```bash
DB_CONNECTION=testing_mysql php artisan test
```
3. إيقاف الحاوية بعد الانتهاء:
```bash
docker compose -f docker-compose.test.yml down
```

> **درع الحماية:** تم تضمين حارس صارم في `tests/TestCase.php` يقوم بإيقاف الاختبار فوراً برمي استثناء قاتل في حال محاولة الاتصال بـ TiDB Cloud أثناء تشغيل الاختبارات.

---

##  التوثيق التفاعلي للـ API

تم توليد وتجهيز توثيق كامل للواجهات البرمجية بصيغ متعددة:

- **واجهة التوثيق التفاعلية (Scribe HTML):** قم بزيارة `/docs` في المتصفح أثناء تشغيل الخادم:
  `http://localhost:8000/docs`
- **مواصفات OpenAPI 3.0:** متوفرة في الملف:
  [`docs/openapi.yaml`](file:///docs/openapi.yaml)
- **مجموعة Postman الرسمية:** متوفرة ومجهزة للاستيراد في Postman:
  [`docs/pwa_store_postman_collection.json`](file:///docs/pwa_store_postman_collection.json)
- **ملفات طلبات HTTP لاختبار الـ IDE (VSCode / PhpStorm):**
  - `docs/api_v1_auth.http`
  - `docs/api_v1_public.http`
  - `docs/api_v1_user.http`
  - `docs/api_v1_developer.http`
  - `docs/api_v1_moderator.http`

لتحديث التوثيق بعد أي تعديل:
```bash
php artisan scribe:generate
```

---

##  جدول نقاط النهاية (Endpoints Summary)

| الطريقة | المسار | المصادقة / الصلاحية | Rate Limit | الوصف |
|:---|:---|:---|:---|:---|
| **POST** | `/api/v1/auth/register` | عام | `throttle:register` (5/min) | إنشاء حساب مستخدم أو مطور جديد |
| **POST** | `/api/v1/auth/login` | عام | `throttle:login` (5/min) | تسجيل الدخول واستخراج Bearer Token |
| **GET** | `/api/v1/auth/me` | `auth:sanctum` | `throttle:api` (60/min) | استرجاع بيانات الحساب والصلاحيات |
| **POST** | `/api/v1/auth/logout` | `auth:sanctum` | `throttle:api` (60/min) | إبطال رمز الوصول الحالي |
| **GET** | `/api/v1/pwas` | عام | `throttle:api` (60/min) | تصفح وبحث التطبيقات المنشورة |
| **GET** | `/api/v1/pwas/{slug}` | عام | `throttle:api` (60/min) | عرض تفاصيل تطبيق منشور |
| **GET** | `/api/v1/pwas/{slug}/reviews` | عام | `throttle:api` (60/min) | استعراض مراجعات التطبيق المنشور |
| **GET** | `/api/v1/categories` | عام | `throttle:api` (60/min) | شجرة التصنيفات النشطة مع أعداد التطبيقات |
| **GET** | `/api/v1/me/installations` | `auth:sanctum` | `throttle:api` (60/min) | قائمة التطبيقات المثبتة للمستخدم |
| **POST** | `/api/v1/pwas/{slug}/install` | `auth:sanctum` + `pwas.install` | `throttle:write` (30/min) | تثبيت التطبيق (Idempotent) |
| **POST** | `/api/v1/pwas/{slug}/reviews` | `auth:sanctum` + `reviews.create` | `throttle:write` (30/min) | تقييم ومراجعة التطبيق |
| **PUT/PATCH** | `/api/v1/reviews/{id}` | `auth:sanctum` + `reviews.update-own` | `throttle:write` (30/min) | تعديل مراجعة المستخدم الخاصة |
| **DELETE** | `/api/v1/reviews/{id}` | `auth:sanctum` + `reviews.delete-own` | `throttle:write` (30/min) | حذف مراجعة المستخدم الخاصة |
| **GET** | `/api/v1/developer/dashboard` | `auth:sanctum` + `developer.dashboard` | `throttle:api` (60/min) | إحصائيات لوحة تحكم المطور |
| **GET** | `/api/v1/developer/pwas` | `auth:sanctum` + `developer.dashboard` | `throttle:api` (60/min) | استعراض تطبيقات المطور |
| **POST** | `/api/v1/developer/pwas` | `auth:sanctum` + `pwas.submit` | `throttle:write` (30/min) | تقديم تطبيق جديد للمراجعة |
| **PUT/PATCH** | `/api/v1/developer/pwas/{id}` | `auth:sanctum` + `pwas.update-own` | `throttle:write` (30/min) | تحديث تطبيق للمطور (مسودة أو مرفوض) |
| **POST** | `/api/v1/developer/pwas/{id}/resubmit` | `auth:sanctum` + `pwas.submit` | `throttle:write` (30/min) | إعادة تقديم تطبيق بعد تصحيحه |
| **GET** | `/api/v1/moderator/pwas/pending` | `auth:sanctum` + `pwas.view-pending` | `throttle:api` (60/min) | قائمة التطبيقات المعلقة بنظام FIFO |
| **GET** | `/api/v1/moderator/pwas/{id}` | `auth:sanctum` + `pwas.view-pending` | `throttle:api` (60/min) | تفاصيل التطبيق وبيانات المطور للمراجعة |
| **POST** | `/api/v1/moderator/pwas/{id}/approve` | `auth:sanctum` + `pwas.review` | `throttle:write` (30/min) | قبول التطبيق ونشره فوراً |
| **POST** | `/api/v1/moderator/pwas/{id}/reject` | `auth:sanctum` + `pwas.review` | `throttle:write` (30/min) | رفض التطبيق مع سبب مطهر |
| **GET** | `/up` | عام | غير مقيد | فحص جاهزية الخادم (Health Check) |

---

##  مؤشرات الأداء وعدد الاستعلامات (Query Benchmarks)

| نقطة النهاية | عدد الاستعلامات المتوقعة | آلية التحسين والتخزين المؤقت |
|:---|:---:|:---|
| `GET /api/v1/pwas` | **1 - 2** | `with(['category', 'developer'])` لمنع N+1 بالكامل |
| `GET /api/v1/pwas/{slug}` | **1** | استعلام مفرد مع العلاقات عبر الفهرس الفريد `slug` |
| `GET /api/v1/categories` | **0 (عند توفر الكاش) / 2 (أول مرة)** | مخزنة في كاش الذاكرة لمدة 10 دقائق وتُبطل فورياً عند أي نشر |
| `GET /api/v1/developer/dashboard` | **3** | استعلام تجميعي مفرد + استعلام أحدث المراجعات بـ JOIN واحد |
| `GET /api/v1/moderator/pwas/pending` | **2** | استعلام الفرز `FIFO` مع العلاقات وتعداد الصفحات |

---

##  أمان الواجهة الأمامية وعرض النصوص (Frontend Security Notice)

يقوم الـ API بتطهير المدخلات وفحص الروابط والنصوص بدقة ضد هجمات الحقن البرمجي (XSS و SSRF)، ومع ذلك يجب على مطوري الواجهة الأمامية (Frontend) الالتزام بعرض النصوص كنصوص خام فقط (`text content` / `textContent`)، وعدم استخدام وسوم العرض المباشر للـ HTML مثل `innerHTML` أو `dangerouslySetInnerHTML` في أطر العمل كـ React أو Vue لتفادي أي ثغرات من جانب العميل.

---

##  رخصة المشروع
المشروع مبني ومرخص وفق رخصة [MIT License](LICENSE).
