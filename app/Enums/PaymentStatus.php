<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
