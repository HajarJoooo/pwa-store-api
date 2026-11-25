# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_BEARER_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

قم بإرسال الرمز في ترويسة الطلب: `Authorization: Bearer <TOKEN>`. يمكن الحصول على الرمز عبر تسجيل الدخول `/api/v1/auth/login` أو إنشاء حساب `/api/v1/auth/register`.
