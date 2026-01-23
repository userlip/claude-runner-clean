<?php

namespace App\Services;

class SecurityDecisionParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        if (preg_match('/```json\n(.*?)\n```/s', $content, $matches)) {
            $data = json_decode($matches[1], true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'merge_allowed' => false,
            'risk_level' => 'unknown',
        ];
    }
}
