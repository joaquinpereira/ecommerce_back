<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activo',
            self::Archived => 'Archivado',
        };
    }
}
