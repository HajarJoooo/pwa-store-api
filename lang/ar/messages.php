<?php

return [
    'success' => 'تمت العملية بنجاح.',
    'error' => 'حدث خطأ أثناء معالجة الطلب.',
    'created' => 'تم الإنشاء بنجاح.',
    'updated' => 'تم التحديث بنجاح.',
    'deleted' => 'تم الحذف بنجاح.',

    // Auth specific
    'registered_successfully' => 'تم إنشاء الحساب بنجاح.',
    'logged_in_successfully' => 'تم تسجيل الدخول بنجاح.',
    'logged_out_successfully' => 'تم تسجيل الخروج بنجاح.',
    'account_inactive' => 'تم تعطيل هذا الحساب، يرجى التواصل مع إدارة المتجر.',
    'invalid_credentials' => 'بيانات الاعتماد المدخلة غير صحيحة.',
    'unauthenticated' => 'يجب تسجيل الدخول للوصول إلى هذا المورد.',
    'unauthorized' => 'ليس لديك الصلاحية الكافية لتنفيذ هذا الإجراء.',
    'not_found' => 'المورد المطلوب غير موجود.',
    'validation_failed' => 'بيانات الإدخال غير صالحة، يرجى تصحيح الأخطاء.',
    'too_many_requests' => 'تجاوزت الحد المسموح به من المحاولات، يرجى الانتظار قليلاً والمحاولة لاحقاً.',
    'server_error' => 'حدث خطأ غير متوقع في الخادم، يرجى المحاولة لاحقاً.',

    // Installations & Reviews
    'installed_successfully' => 'تم تثبيت التطبيق بنجاح.',
    'already_installed' => 'التطبيق مثبت لديك مسبقاً.',
    'review_added_successfully' => 'تم إضافة تقييمك بنجاح.',
    'review_updated_successfully' => 'تم تعديل تقييمك بنجاح.',
    'review_deleted_successfully' => 'تم حذف التقييم بنجاح.',
    'cannot_review_own_pwa' => 'لا يمكنك تقييم تطبيقك الخاص.',
    'already_reviewed' => 'لقد قمت بتقييم هذا التطبيق مسبقاً.',
    'install_required_for_review' => 'يجب تثبيت التطبيق أولاً لتتمكن من تقييمه.',

    // Developer specific
    'pwa_submitted_successfully' => 'تم تقديم التطبيق للمراجعة بنجاح.',
    'pwa_updated_successfully' => 'تم تحديث بيانات التطبيق بنجاح.',
    'pwa_resubmitted_successfully' => 'تمت إعادة إرسال التطبيق للمراجعة بنجاح.',
    'pwa_manifest_exists' => 'لديك تطبيق مسجل بالفعل بهذا الرابط (Manifest URL).',
    'max_pending_pwas_reached' => 'لقد وصلت للحد الأقصى المسموح به من التطبيقات المعلقة قيد المراجعة (:max).',
    'cannot_update_approved_pwa' => 'لا يمكن تعديل التطبيقات المعتمدة في الإصدار الحالي.',
    'cannot_update_pending_pwa' => 'لا يمكن تعديل التطبيق أثناء وجوده قيد المراجعة.',
    'cannot_resubmit_pwa' => 'يمكن إعادة إرسال التطبيقات المرفوضة أو المسودات فقط للمراجعة.',
    'dashboard_retrieved_successfully' => 'تم جلب بيانات لوحة التحكم بنجاح.',

    // Moderator specific
    'pwa_approved_successfully' => 'تمت الموافقة على التطبيق ونشره بنجاح.',
    'pwa_rejected_successfully' => 'تم رفض التطبيق بنجاح وتسجيل السبب.',
    'pwa_not_pending' => 'هذا التطبيق ليس قيد المراجعة حالياً.',
    'cannot_review_own_pwa_as_moderator' => 'لا يمكنك مراجعة أو تغيير حالة تطبيق تملكه.',
];
