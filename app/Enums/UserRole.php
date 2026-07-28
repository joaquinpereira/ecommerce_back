<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cliente = 'cliente';
    case Proveedor = 'proveedor';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cliente => 'Cliente',
            self::Proveedor => 'Proveedor',
        };
    }
}
