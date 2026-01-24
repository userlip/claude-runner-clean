<?php

namespace App\Services;

class SecurityDecisionParser
{
    /**
     * Parse orchestrator decision from AI response content.
     * Specifically looks for JSON blocks containing 'merge_allowed' field
     * to distinguish orchestrator decisions from CI fixer responses.
     *
     * @return array<string, mixed>|null
     */
    public function parse(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        // Find ALL JSON blocks in the content
        if (preg_match_all('/```json\n(.*?)\n```/s', $content, $matches)) {
            foreach ($matches[1] as $jsonBlock) {
                $data = json_decode($jsonBlock, true);
                // Only return orchestrator decisions (must have merge_allowed field)
                if (is_array($data) && array_key_exists('merge_allowed', $data)) {
                    return $data;
                }
            }
        }

        return null;
    }

    /**
     * Parse CI fixer response from AI content.
     * Looks for JSON blocks containing 'ci_fixed' field.
     *
     * @return array<string, mixed>|null
     */
    public function parseCiFixerResponse(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        // Find ALL JSON blocks in the content
        if (preg_match_all('/```json\n(.*?)\n```/s', $content, $matches)) {
            foreach ($matches[1] as $jsonBlock) {
                $data = json_decode($jsonBlock, true);
                // Only return CI fixer responses (must have ci_fixed field)
                if (is_array($data) && array_key_exists('ci_fixed', $data)) {
                    return $data;
                }
            }
        }

        return null;
    }
}
