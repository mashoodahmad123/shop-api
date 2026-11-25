<?php

namespace App\Enums;

enum PaymentResult: string
{
    case APPLIED = 'applied';
    case IGNORED = 'ignored';
    case REJECTED = 'rejected';
}
