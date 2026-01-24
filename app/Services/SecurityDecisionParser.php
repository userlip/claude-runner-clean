<?php

namespace App\Services;

class SecurityDecisionParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parse(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        if (preg_match('/```json\n(.*?)\n```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }
}
