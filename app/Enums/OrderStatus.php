<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'En Proceso',
            self::Paid => 'Pagado',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }
}
