<?php

namespace App\Enums;

enum RegionLevel: string
{
    case Province = 'provinsi';
    case City = 'kota';
    case Regency = 'kabupaten';
    case District = 'kecamatan';
    case Village = 'kelurahan';

    /** @return list<string> */
    public function allowedParentLevels(): array
    {
        return match ($this) {
            self::Province => [],
            self::City, self::Regency => [self::Province->value],
            self::District => [self::City->value, self::Regency->value],
            self::Village => [self::District->value],
        };
    }
}
