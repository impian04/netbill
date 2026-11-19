<?php

namespace App\Exports;

/**
 * Prevents CSV formula injection by prefixing dangerous leading characters.
 * Only triggers on: = + - @ \t \r — NOT on alphanumeric IDs like "JCN-KTU-0001".
 */
class CellSanitizer
{
    private const INJECTION_PREFIXES = ['=', '+', '@', "\t", "\r"];

    public static function sanitize(mixed $value): string
    {
        $str = (string) $value;

        if ($str === '') {
            return '';
        }

        // Only neutralize actual formula-injection prefixes (not hyphen in IDs)
        if (in_array($str[0], self::INJECTION_PREFIXES, true)) {
            return "'" . $str;
        }

        return $str;
    }

    public static function sanitizeRow(array $row): array
    {
        return array_map([self::class, 'sanitize'], $row);
    }
}
