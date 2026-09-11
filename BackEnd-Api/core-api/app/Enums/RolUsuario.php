<?php

namespace App\Enums;

enum RolUsuario: int
{
    case Cliente = 0;
    case AdminTec = 1;
    case AdminCom = 2;
    case Tecnico = 3;

    public function label(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::AdminTec => 'AdminTec',
            self::AdminCom => 'AdminCom',
            self::Tecnico => 'Tecnico',
        };
    }

    public static function fromInt(int $value): self
    {
        return self::from($value);
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::AdminTec, self::AdminCom], true);
    }

    public function isTecnicoLike(): bool
    {
        return in_array($this, [self::AdminTec, self::Tecnico], true);
    }
}
