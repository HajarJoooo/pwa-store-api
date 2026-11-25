<?php

namespace App\Enums;

enum PwaStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Pending => 'قيد المراجعة',
            self::Approved => 'مقبول',
            self::Rejected => 'مرفوض',
        };
    }
}
