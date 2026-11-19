<?php

namespace App\Support;

class ExternalUrl
{
    /**
     * Generate an absolute external URL using config('app.url') as root.
     *
     * @param string $name
     * @param mixed $parameters
     * @return string
     */
    public static function route(string $name, mixed $parameters = []): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $relative = route($name, $parameters, false);

        return $appUrl . $relative;
    }
}

