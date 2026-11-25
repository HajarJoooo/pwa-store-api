# دليل تكامل واجهة برمجة التطبيقات — PWA Store API (v1)

توثيق عملي ومختصر ومُعدّ خصيصاً لفريق الـ **Frontend** للتكامل مع متجر تطبيقات الويب التقدمية (**PWA Store API**).

---

## 1. الروابط الأساسية (Base URLs)

| البيئة | الرابط |
|---|---|
| **Production API Base URL** | `https://pwa-store-api.vercel.app/api/v1` |
| **التوثيق التفاعلي المباشر (Scribe)** | `https://pwa-store-api.vercel.app/docs` |
| **ملف OpenAPI (Swagger/Types)** | `https://pwa-store-api.vercel.app/docs/openapi.yaml` |
| **ملف Postman Collection** | `https://pwa-store-api.vercel.app/docs/collection.json` |

---

## 2. الترويسات المطلوبة (Required Headers)

أرسل الترويسات التالية مع جميع الطلبات:

```http
Accept: application/json
Content-Type: application/json
```

وللطلبات المحمية (التي تتطلب تسجيل دخول)، أضف توكن **Bearer (Laravel Sanctum)**:

```http
Authorization: Bearer <ACCESS_TOKEN>
```

---

## 3. صيغة الاستجابة الموحدة (Response Envelope)

جميع استجابات الـ API تتبع بنية JSON موحدة:

### استجابة ناجحة (عنصر واحد أو قائمة بسيطة)
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... },
  "errors": null
}
```

### استجابة ناجحة مع ترقيم الصفحات (Pagination)
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3
  },
  "links": {
    "first": "https://pwa-store-api.vercel.app/api/v1/pwas?page=1",
    "last": "https://pwa-store-api.vercel.app/api/v1/pwas?page=3",
    "prev": null,
    "next": "https://pwa-store-api.vercel.app/api/v1/pwas?page=2"
  },
  "errors": null
}
```

### استجابة خطأ التحقق (Validation Error — `422`)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": null,
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password field confirmation does not match."]
  }
}
```

### أكواد الحالة (HTTP Status Codes)
| الكود | المعنى | التصرف المقترح في الـ Frontend |
|---|---|---|
| `200` | نجاح الطلب | عرض/تحديث البيانات |
| `201` | تم الإنشاء بنجاح | حفظ التوكن أو العنصر الجديد |
| `401` | غير مصادق (التوكن مفقود أو منتهي) | حذف التوكن وإعادة التوجيه لصفحة تسجيل الدخول |
| `403` | غير مصرّح (الدور/الصلاحية غير كافية) | إظهار رسالة عدم وجود صلاحية |
| `404` | العنصر غير موجود | إظهار صفحة/رسالة 404 |
| `422` | خطأ في التحقق من المدخلات | عرض `errors` تحت الحقول المطابقة |
| `429` | تجاوز حد الطلبات (Rate Limit) | إظهار رسالة انتظار والمحاولة لاحقاً |
| `500` | خطأ داخلي في الخادم | إظهار رسالة خطأ عامة |

---

## 4. حدود الطلبات (Rate Limiting)

| المجموعة | الحد الأقصى |
|---|---|
| `POST /auth/login` | **5** طلبات / دقيقة (لكل بريد/اسم مستخدم + IP) |
| `POST /auth/register` | **5** طلبات / دقيقة (لكل IP) |
| جميع طلبات القراءة (`api`) | **60** طلب / دقيقة |
| طلبات الكتابة والتعديل (`write`) | **30** طلب / دقيقة |

---

## 5. الأدوار والصلاحيات (Roles)

يدعم النظام 3 أدوار رئيسية، وتُعاد مصفوفتا `roles` و `permissions` داخل كائن المستخدم (`data.user` أو `GET /auth/me`):
- **`user`**: تصفح المتجر، تثبيت التطبيقات، إضافة/تعديل/حذف مراجعاته الخاصة.
- **`developer`**: بالإضافة لما سبق، الوصول إلى لوحة تحكم المطور وإرسال/تعديل/إعادة إرسال تطبيقاته.
- **`moderator`** / **`admin`**: استعراض التطبيقات المعلّقة (`pending`) والموافقة عليها أو رفضها.

---

## 6. نقاط النهاية (API Endpoints)

### أ. المصادقة (Authentication)

#### 1) تسجيل حساب جديد
- **`POST /api/v1/auth/register`** *(عام)*
- **Body:**
```json
{
  "first_name": "Ahmed",
  "last_name": "Ali",
  "username": "ahmed_ali",
  "email": "ahmed@example.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "phone": "+966500000000",
  "role": "user"
}
```
- **قواعد التحقق:**
  - `first_name`, `last_name`: مطلوب، أحرف ومسافات (`max:100`).
  - `username`: مطلوب، فريد، `alpha_dash` (`max:50`).
  - `email`: مطلوب، بريد صالح وفريد (`max:255`).
  - `password`: مطلوب، `min:8`, `max:72`، ويتطلب `password_confirmation`.
  - `phone`: اختياري (`max:30`).
  - `role`: مطلوب، إما `"user"` أو `"developer"`.
- **الاستجابة (`201 Created`):** تعيد `data.user` و `data.token` و `data.token_type` (`"Bearer"`).

#### 2) تسجيل الدخول
- **`POST /api/v1/auth/login`** *(عام)*
- **Body:**
```json
{
  "login": "ahmed@example.com",
  "password": "Password123!"
}
```
> الحقل `login` يقبل **البريد الإلكتروني** أو **اسم المستخدم (`username`)**.
- **الاستجابة (`200 OK`):** تعيد `data.user` و `data.token` و `data.token_type`.

#### 3) بيانات المستخدم الحالي
- **`GET /api/v1/auth/me`** *(محمي — Bearer Token)*
- **الاستجابة (`200 OK`):**
```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Ali",
    "full_name": "Ahmed Ali",
    "username": "ahmed_ali",
    "email": "ahmed@example.com",
    "phone": null,
    "bio": null,
    "avatar_url": null,
    "country": null,
    "city": null,
    "is_verified": false,
    "is_active": true,
    "roles": ["user"],
    "permissions": ["pwas.install", "reviews.create", "reviews.update-own", "reviews.delete-own"],
    "last_login_at": "2026-11-20T18:00:00+00:00",
    "created_at": "2026-11-20T17:55:00+00:00"
  },
  "errors": null
}
```

#### 4) تسجيل الخروج
- **`POST /api/v1/auth/logout`** *(محمي — Bearer Token)*
- **الاستجابة (`200 OK`):** يلغي التوكن الحالي.

---

### ب. المتجر العام (Public Store — بدون مصادقة)

#### 1) قائمة التصنيفات
- **`GET /api/v1/categories`**
- تعيد شجرة التصنيفات النشطة (`id`, `name`, `name_ar`, `slug`, `description`, `icon_url`, `sort_order`, `pwas_count`, `children`).

#### 2) قائمة التطبيقات (PWAs)
- **`GET /api/v1/pwas`**
- **Query Parameters (اختيارية):**
  - `search`: نص البحث (`min:2`, `max:100`)
  - `category`: الـ `slug` الخاص بالتصنيف (مثال: `games`)
  - `sort`: الترتيب — `newest` | `popular` | `top_rated`
  - `per_page`: عدد العناصر في الصفحة (`1` إلى `50`)
  - `page`: رقم الصفحة (`>= 1`)
- **حقول كل عنصر في `data`:** `id`, `name`, `slug`, `short_description`, `icon_url`, `version`, `rating_average`, `review_count`, `install_count`, `category { name, name_ar, slug }`.

#### 3) تفاصيل تطبيق محدد
- **`GET /api/v1/pwas/{slug}`**
- تعيد تفاصيل التطبيق الكاملة: `id`, `name`, `slug`, `short_description`, `description`, `manifest_url`, `start_url`, `icon_url`, `screenshots[]`, `version`, `rating_average`, `review_count`, `install_count`, `category`, `developer { first_name, last_name, username, avatar_url }`, `created_at`, `updated_at`.

#### 4) مراجعات تطبيق محدد
- **`GET /api/v1/pwas/{slug}/reviews`**
- **Query Parameters (اختيارية):**
  - `sort`: `newest` | `helpful`
  - `per_page`: `1..50`
  - `page`: `>= 1`
- **حقول كل مراجعة:** `id`, `rating`, `title`, `content`, `helpful_count`, `created_at`, `reviewer { first_name, username, avatar_url }`.

---

### ج. مسارات المستخدم المصادق عليه (Authenticated User — Bearer Token)

#### 1) قائمة التطبيقات المثبّتة للمستخدم
- **`GET /api/v1/me/installations`**
- **Query Parameters:** `per_page` (`1..50`), `page` (`>= 1`).

#### 2) تثبيت تطبيق
- **`POST /api/v1/pwas/{slug}/install`** *(صلاحية: `pwas.install`)*
- يسجّل تثبيت التطبيق للمستخدم الحالي ويزيد `install_count`.

#### 3) إضافة مراجعة لتطبيق
- **`POST /api/v1/pwas/{slug}/reviews`** *(صلاحية: `reviews.create`)*
- **Body:**
```json
{
  "rating": 5,
  "title": "تطبيق ممتاز",
  "content": "سريع جداً ويعمل بدون مشاكل."
}
```
- **قواعد التحقق:** `rating` مطلوب (`1` إلى `5`)، `title` اختياري (`max:150`)، `content` اختياري (`max:2000`).

#### 4) تعديل مراجعة (الخاصة بالمستخدم فقط)
- **`PUT` أو `PATCH /api/v1/reviews/{id}`** *(صلاحية: `reviews.update-own`)*
- **Body:** أي حقل من `rating` (`1..5`)، `title`، `content`.

#### 5) حذف مراجعة (الخاصة بالمستخدم فقط)
- **`DELETE /api/v1/reviews/{id}`** *(صلاحية: `reviews.delete-own`)*

---

### د. لوحة المطور (Developer — Bearer Token + دور `developer`)

#### 1) إحصائيات لوحة تحكم المطور
- **`GET /api/v1/developer/dashboard`** *(صلاحية: `developer.dashboard`)*
- تعيد إجمالي التطبيقات، التثبيتات، متوسط التقييم، أحدث المراجعات، وأعلى التطبيقات أداءً.

#### 2) قائمة تطبيقات المطور
- **`GET /api/v1/developer/pwas`** *(صلاحية: `developer.dashboard`)*
- **Query Parameters:**
  - `status`: `draft` | `pending` | `approved` | `rejected`
  - `search`: نص البحث (`min:2`, `max:100`)
  - `per_page` (`1..50`), `page` (`>= 1`)

#### 3) إرسال تطبيق جديد للمراجعة
- **`POST /api/v1/developer/pwas`** *(صلاحية: `pwas.submit`)*
- **Body:**
```json
{
  "name": "My Awesome PWA",
  "category_id": 1,
  "short_description": "وصف مختصر للتطبيق",
  "description": "وصف تفصيلي للتطبيق...",
  "manifest_url": "https://myapp.com/manifest.json",
  "start_url": "https://myapp.com/",
  "icon_url": "https://myapp.com/icon-512.png",
  "screenshots": [
    "https://myapp.com/screen1.png",
    "https://myapp.com/screen2.png"
  ],
  "version": "1.0.0"
}
```
- **ملاحظات تحقق مهمة:**
  - جميع الروابط (`manifest_url`, `start_url`, `icon_url`, `screenshots.*`) يجب أن تبدأ بـ **`https://`** وتكون روابط عامة آمنة (ليست `localhost` أو IP داخلي).
  - `screenshots`: مصفوفة بحد أقصى **8** روابط.
  - `version`: اختياري بصيغة SemVer مثل `1.0.0`.

#### 4) تعديل تطبيق خاص بالمطور
- **`PUT` أو `PATCH /api/v1/developer/pwas/{id}`** *(صلاحية: `pwas.update-own`)*
- يقبل نفس حقول الإنشاء (يجب إرسال حقل واحد صالح على الأقل).

#### 5) إعادة إرسال تطبيق مرفوض للمراجعة
- **`POST /api/v1/developer/pwas/{id}/resubmit`** *(صلاحية: `pwas.submit`)*

---

### هـ. بوابة الإشراف (Moderator — Bearer Token + دور `moderator` أو `admin`)

#### 1) قائمة التطبيقات المعلّقة للمراجعة
- **`GET /api/v1/moderator/pwas/pending`** *(صلاحية: `pwas.view-pending`)*
- **Query Parameters:** `search` (`min:2..100`), `per_page` (`1..50`), `page` (`>= 1`).

#### 2) تفاصيل تطبيق للإشراف
- **`GET /api/v1/moderator/pwas/{id}`** *(صلاحية: `pwas.view-pending`)*

#### 3) الموافقة على تطبيق
- **`POST /api/v1/moderator/pwas/{id}/approve`** *(صلاحية: `pwas.review`)*

#### 4) رفض تطبيق
- **`POST /api/v1/moderator/pwas/{id}/reject`** *(صلاحية: `pwas.review`)*
- **Body:**
```json
{
  "reason": "رابط ملف manifest.json غير صالح ولا يحتوي على الأيقونات المطلوبة."
}
```
- **قواعد التحقق:** `reason` مطلوب (`min:10`, `max:1000`).

---

### و. المفضلة (Favorites — Bearer Token)

#### 1) قائمة مفضلات المستخدم
- **`GET /api/v1/favorites`**
- **Query Parameters:** `per_page` (`1..50`), `page` (`>= 1`).
- **Response:** قائمة صفحات بتطبيقات PWA المفضلة للمستخدم.

#### 2) قائمة معرفات المفضلات السريعة
- **`GET /api/v1/favorites/ids`**
- **Response:** مصفوفة من أرقام ID للتطبيقات المفضلة `[1, 4, 12]` لمزامنة الواجهة اللحظية.

#### 3) تبديل حالة المفضلة (إضافة / حذف)
- **`POST /api/v1/favorites/{id}`** أو **`POST /api/v1/pwas/{slug}/favorite`**
- **Response:** `{ "pwa_id": 1, "is_favorite": true }`.

---

### ز. مركز الإشعارات (Notifications — Bearer Token)

#### 1) قائمة إشعارات المستخدم مع عداد غير المقروء
- **`GET /api/v1/notifications`**
- **Response:** `{ "notifications": [...], "unread_count": 2 }`.

#### 2) تحديد إشعار فردي كمقروء
- **`PUT /api/v1/notifications/{id}/read`**

#### 3) تحديد كافة الإشعارات كمقروءة
- **`POST /api/v1/notifications/mark-all-read`**

#### 4) حذف إشعار
- **`DELETE /api/v1/notifications/{id}`**

---

### ح. تحليلات واستخدام التطبيقات (Analytics)

#### 1) تسجيل حدث تفاعلي
- **`POST /api/v1/analytics/events`** *(عام أو موثق)*
- **Body:**
```json
{
  "pwa_id": 1,
  "event_type": "detail_visit",
  "metadata": { "source": "home_hero" }
}
```
- **أنواع الأحداث المسموحة:** `view`, `detail_visit`, `install_clicked`, `installed_confirmed`, `external_opened`.

#### 2) إحصائيات التطبيق التحليلية (للمطور أو الإدارة)
- **`GET /api/v1/analytics/pwa/{id}`** *(Bearer Token)*

---

### ط. مساعد استكشاف التطبيقات بالذكاء الاصطناعي (AI Discovery)

#### 1) استكشاف وتوصيات التطبيقات الطبيعية
- **`POST /api/v1/ai/discover`** *(عام)*
- **Body:**
```json
{
  "prompt": "أريد تطبيقاً لتنظيم مهامي اليومية وإدارة الوقت",
  "history": []
}
```
- **Response:**
```json
{
  "success": true,
  "data": {
    "answerText": "عثرت لك على أفضل تطبيقات الويب التقدمية المناسبة لطلبك بدقة:",
    "recommendations": [
      {
        "app": { "id": 1, "name": "...", "slug": "..." },
        "reason": "يتميز بأدوات تنظيم المهام وترتيب الأولويات",
        "confidenceScore": 95,
        "highlightedFeatures": ["إدارة المهام اليومية", "تقنية بومودورو"]
      }
    ],
    "suggestedChips": ["أريد تطبيقاً لإدارة مصاريفي", "أبحث عن لعبة ذكاء"]
  }
}
```

---

### ي. لوحة تحكم الإدارة الشاملة (Admin Portal — Bearer Token + دور `admin`)

#### 1) إحصائيات عامة للمنصة
- **`GET /api/v1/admin/stats`**

#### 2) إدارة قائمة المستخدمين والبحث
- **`GET /api/v1/admin/users`** (يدعم `search`, `role`, `per_page`)

#### 3) تبديل توثيق هوية المطور
- **`POST /api/v1/admin/users/{id}/toggle-verify`**

#### 4) تفعيل أو تعطيل حساب المستخدم
- **`POST /api/v1/admin/users/{id}/toggle-active`**

#### 5) إضافة تصنيف جديد
- **`POST /api/v1/admin/categories`**

---

## 7. مثال سريع للبدء في الـ Frontend (TypeScript / Fetch)

```ts
const API_BASE_URL = 'https://pwa-store-api.vercel.app/api/v1';

export async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {},
  token?: string
): Promise<T> {
  const headers: HeadersInit = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...options.headers,
  };

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  const payload = await response.json();

  if (!response.ok) {
    throw payload; // يحتوي على { success: false, message, errors }
  }

  return payload;
}

// مثال: جلب التصنيفات
// const categories = await apiRequest('/categories');
```
