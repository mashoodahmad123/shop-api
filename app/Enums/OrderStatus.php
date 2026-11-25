<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case SHIPPED = 'shipped';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PAID, self::CANCELLED],
            self::PAID => [self::SHIPPED, self::CANCELLED],
            self::CANCELLED, self::SHIPPED => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isCancellableByCustomer(): bool
    {
        return $this === self::PENDING;
    }
}
