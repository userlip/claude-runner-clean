<?php

namespace App\Models;

use App\Enums\ProposalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Playbook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'proposal_type',
        'project',
        'description',
        'prompt_template',
        'skills',
        'is_active',
        'times_used',
        'success_count',
    ];

    protected function casts(): array
    {
        return [
            'proposal_type' => ProposalType::class,
            'skills' => 'array',
            'is_active' => 'boolean',
            'times_used' => 'integer',
            'success_count' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, ProposalType $type): Builder
    {
        return $query->where('proposal_type', $type->value);
    }

    public function scopeForProject(Builder $query, ?string $project): Builder
    {
        return $query->where(function ($q) use ($project) {
            $q->whereNull('project')
                ->orWhere('project', $project);
        });
    }

    public static function findBestMatch(ProposalType $type, ?string $project = null): ?self
    {
        // First try project-specific playbook
        if ($project) {
            $specific = static::active()
                ->forType($type)
                ->where('project', $project)
                ->orderByDesc('success_count')
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        // Fall back to generic playbook for this type
        return static::active()
            ->forType($type)
            ->whereNull('project')
            ->orderByDesc('success_count')
            ->first();
    }

    public function getSuccessRate(): float
    {
        if ($this->times_used === 0) {
            return 0;
        }

        return round(($this->success_count / $this->times_used) * 100, 1);
    }

    public function recordUsage(bool $success): void
    {
        $this->increment('times_used');
        if ($success) {
            $this->increment('success_count');
        }
    }

    public function getSkillsForPrompt(): string
    {
        $skills = $this->skills ?? $this->proposal_type?->getRequiredSkills() ?? [];

        if (empty($skills)) {
            return '';
        }

        $skillList = implode(', ', array_map(fn ($s) => "`/{$s}`", $skills));

        return "\n\n**Required Skills:** Use these skills during execution: {$skillList}";
    }

    public function buildPrompt(Proposal $proposal): string
    {
        $prompt = $this->prompt_template;

        // Replace placeholders
        $prompt = str_replace('{{TITLE}}', $proposal->title, $prompt);
        $prompt = str_replace('{{DESCRIPTION}}', $proposal->description ?? '', $prompt);
        $prompt = str_replace('{{PROJECT}}', $proposal->project ?? '', $prompt);
        $prompt = str_replace('{{TYPE}}', $proposal->type?->label() ?? '', $prompt);

        // Append skills
        $prompt .= $this->getSkillsForPrompt();

        return $prompt;
    }
}
