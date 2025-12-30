<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class UuidHelper
{
    /**
     * Generate a unique 32-character hexadecimal UUID (without dashes).
     *
     * @return string
     */
    public static function generate()
    {
        return str_replace('-', '', (string) Str::uuid());
    }
}
