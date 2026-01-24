<?php

namespace App\Support;

class DeployScriptHelper
{
    public static function ensureCleanup(string $script, string $cleanupCommand): string
    {
        if (str_contains($script, $cleanupCommand)) {
            return $script;
        }

        $lines = preg_split('/\r\n|\r|\n/', $script) ?: [];
        $insertAt = null;

        foreach ($lines as $index => $line) {
            if (preg_match('/\bgit\b/i', $line)) {
                $insertAt = $index;
                break;
            }
        }

        if ($insertAt === null) {
            array_unshift($lines, $cleanupCommand);
        } else {
            array_splice($lines, $insertAt, 0, [$cleanupCommand]);
        }

        return implode("\n", $lines);
    }
}
