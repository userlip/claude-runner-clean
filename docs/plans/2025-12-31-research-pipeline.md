# Research Pipeline Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a continuous research pipeline that uses z.ai/GLM to scan for API opportunities, SEO keywords, competitor intelligence, feature ideas, and promotion opportunities, creating Proposals and Research Reports.

**Architecture:** 6 research modules run continuously via Laravel scheduler, each creating a z.ai Task in Claude Runner. Modules query Scrappa MCP for data, analyze with AI, create Proposals for actions, and save detailed reports. Promotion directories stored in DB for reuse.

**Tech Stack:** Laravel 12, Filament v4, MySQL, z.ai integration (existing), Scrappa MCP, Telegram notifications

---

## Task 1: Create Research Module Enum

**Files:**
- Create: `app/Enums/ResearchModule.php`

**Step 1: Create the enum file**

```php
<?php

namespace App\Enums;

enum ResearchModule: string
{
    case ApiOpportunities = 'api_opportunities';
    case SeoKeywords = 'seo_keywords';
    case CompetitorIntel = 'competitor_intel';
    case FeatureIdeas = 'feature_ideas';
    case SocialListener = 'social_listener';
    case PromotionFinder = 'promotion_finder';

    public function label(): string
    {
        return match ($this) {
            self::ApiOpportunities => 'API Opportunities',
            self::SeoKeywords => 'SEO & Keywords',
            self::CompetitorIntel => 'Competitor Intelligence',
            self::FeatureIdeas => 'Feature Ideas',
            self::SocialListener => 'Social Listener',
            self::PromotionFinder => 'Promotion Finder',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ApiOpportunities => 'Scan for new API opportunities on RapidAPI and beyond',
            self::SeoKeywords => 'Analyze search rankings and keyword opportunities',
            self::CompetitorIntel => 'Track competitor pricing, features, and gaps',
            self::FeatureIdeas => 'Find feature requests from Reddit, HN, Twitter',
            self::SocialListener => 'Monitor mentions and industry news',
            self::PromotionFinder => 'Discover directories and promotion opportunities',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ApiOpportunities => 'heroicon-o-cube',
            self::SeoKeywords => 'heroicon-o-magnifying-glass',
            self::CompetitorIntel => 'heroicon-o-eye',
            self::FeatureIdeas => 'heroicon-o-light-bulb',
            self::SocialListener => 'heroicon-o-megaphone',
            self::PromotionFinder => 'heroicon-o-rocket-launch',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ApiOpportunities => 'primary',
            self::SeoKeywords => 'success',
            self::CompetitorIntel => 'warning',
            self::FeatureIdeas => 'info',
            self::SocialListener => 'danger',
            self::PromotionFinder => 'gray',
        };
    }

    public function scheduledOffsetMinutes(): int
    {
        return match ($this) {
            self::ApiOpportunities => 0,
            self::SeoKeywords => 20,
            self::CompetitorIntel => 40,
            self::FeatureIdeas => 60,
            self::SocialListener => 80,
            self::PromotionFinder => 100,
        };
    }
}
```

**Step 2: Verify file was created**

Run: `php artisan tinker --execute="echo App\Enums\ResearchModule::ApiOpportunities->label();"`
Expected: `API Opportunities`

**Step 3: Commit**

```bash
git add app/Enums/ResearchModule.php
git commit -m "feat: add ResearchModule enum for research pipeline"
```

---

## Task 2: Create Directory Category Enum

**Files:**
- Create: `app/Enums/DirectoryCategory.php`

**Step 1: Create the enum file**

```php
<?php

namespace App\Enums;

enum DirectoryCategory: string
{
    case Developer = 'developer';
    case ProductHunt = 'product_hunt';
    case Startup = 'startup';
    case Indie = 'indie';
    case Community = 'community';
    case ApiDirectory = 'api_directory';
    case Seo = 'seo';

    public function label(): string
    {
        return match ($this) {
            self::Developer => 'Developer Directories',
            self::ProductHunt => 'Product Hunt & Alternatives',
            self::Startup => 'Startup Lists',
            self::Indie => 'Indie/Maker Communities',
            self::Community => 'Technical Communities',
            self::ApiDirectory => 'API Directories',
            self::Seo => 'SEO/Backlinks',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Developer => 'primary',
            self::ProductHunt => 'warning',
            self::Startup => 'success',
            self::Indie => 'info',
            self::Community => 'danger',
            self::ApiDirectory => 'gray',
            self::Seo => 'secondary',
        };
    }
}
```

**Step 2: Commit**

```bash
git add app/Enums/DirectoryCategory.php
git commit -m "feat: add DirectoryCategory enum"
```

---

## Task 3: Create Submission Status Enum

**Files:**
- Create: `app/Enums/SubmissionStatus.php`

**Step 1: Create the enum file**

```php
<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case Listed = 'listed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Not Submitted',
            self::Pending => 'Pending Review',
            self::Listed => 'Listed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotSubmitted => 'gray',
            self::Pending => 'warning',
            self::Listed => 'success',
            self::Rejected => 'danger',
        };
    }
}
```

**Step 2: Commit**

```bash
git add app/Enums/SubmissionStatus.php
git commit -m "feat: add SubmissionStatus enum"
```

---

## Task 4: Create PromotionDirectory Migration

**Files:**
- Create: `database/migrations/2025_12_31_200000_create_promotion_directories_table.php`

**Step 1: Create migration**

```php
<?php

use App\Enums\DirectoryCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_directories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('url');
            $table->string('category');
            $table->string('submission_type')->default('free'); // free, paid, invite_only
            $table->string('submission_url')->nullable();
            $table->json('requirements')->nullable(); // {needs_account, review_time, etc}
            $table->json('suitable_products')->nullable(); // [scrappa, rezensionsheld]
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('submission_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_directories');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrating... DONE`

**Step 3: Commit**

```bash
git add database/migrations/2025_12_31_200000_create_promotion_directories_table.php
git commit -m "feat: add promotion_directories table migration"
```

---

## Task 5: Create DirectorySubmission Migration

**Files:**
- Create: `database/migrations/2025_12_31_200001_create_directory_submissions_table.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('directory_id')->constrained('promotion_directories')->cascadeOnDelete();
            $table->string('product'); // scrappa, rezensionsheld
            $table->string('status')->default('not_submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->string('listing_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['directory_id', 'product']);
            $table->index('status');
            $table->index('product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_submissions');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrating... DONE`

**Step 3: Commit**

```bash
git add database/migrations/2025_12_31_200001_create_directory_submissions_table.php
git commit -m "feat: add directory_submissions table migration"
```

---

## Task 6: Create ResearchReport Migration

**Files:**
- Create: `database/migrations/2025_12_31_200002_create_research_reports_table.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('module'); // ResearchModule enum value
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content'); // Full markdown report
            $table->unsignedInteger('findings_count')->default(0);
            $table->unsignedInteger('proposals_created')->default(0);
            $table->string('file_path')->nullable(); // docs/research/...
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('module');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_reports');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: `Migrating... DONE`

**Step 3: Commit**

```bash
git add database/migrations/2025_12_31_200002_create_research_reports_table.php
git commit -m "feat: add research_reports table migration"
```

---

## Task 7: Create PromotionDirectory Model

**Files:**
- Create: `app/Models/PromotionDirectory.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use App\Enums\DirectoryCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PromotionDirectory extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'url',
        'category',
        'submission_type',
        'submission_url',
        'requirements',
        'suitable_products',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => DirectoryCategory::class,
            'requirements' => 'array',
            'suitable_products' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PromotionDirectory $directory) {
            $directory->uuid ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DirectorySubmission::class, 'directory_id');
    }

    public function isFree(): bool
    {
        return $this->submission_type === 'free';
    }

    public function isPaid(): bool
    {
        return $this->submission_type === 'paid';
    }

    public function isInviteOnly(): bool
    {
        return $this->submission_type === 'invite_only';
    }

    public function isSuitableFor(string $product): bool
    {
        if (empty($this->suitable_products)) {
            return true; // No restrictions
        }

        return in_array($product, $this->suitable_products);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/PromotionDirectory.php
git commit -m "feat: add PromotionDirectory model"
```

---

## Task 8: Create DirectorySubmission Model

**Files:**
- Create: `app/Models/DirectorySubmission.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DirectorySubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'directory_id',
        'product',
        'status',
        'submitted_at',
        'listed_at',
        'listing_url',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'listed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DirectorySubmission $submission) {
            $submission->uuid ??= Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function directory(): BelongsTo
    {
        return $this->belongsTo(PromotionDirectory::class, 'directory_id');
    }

    public function markAsSubmitted(): void
    {
        $this->update([
            'status' => SubmissionStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function markAsListed(string $listingUrl = null): void
    {
        $this->update([
            'status' => SubmissionStatus::Listed,
            'listed_at' => now(),
            'listing_url' => $listingUrl,
        ]);
    }

    public function markAsRejected(string $reason = null): void
    {
        $this->update([
            'status' => SubmissionStatus::Rejected,
            'notes' => $reason,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/DirectorySubmission.php
git commit -m "feat: add DirectorySubmission model"
```

---

## Task 9: Create ResearchReport Model

**Files:**
- Create: `app/Models/ResearchReport.php`

**Step 1: Create model**

```php
<?php

namespace App\Models;

use App\Enums\ResearchModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ResearchReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'module',
        'title',
        'summary',
        'content',
        'findings_count',
        'proposals_created',
        'file_path',
        'task_id',
    ];

    protected function casts(): array
    {
        return [
            'module' => ResearchModule::class,
            'findings_count' => 'integer',
            'proposals_created' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ResearchReport $report) {
            $report->uuid ??= Str::uuid();
        });

        static::created(function (ResearchReport $report) {
            $report->saveToFile();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function getDefaultFilePath(): string
    {
        $date = $this->created_at?->format('Y-m-d') ?? now()->format('Y-m-d');
        $slug = Str::slug($this->module->value);

        return "docs/research/{$date}/{$slug}.md";
    }

    public function saveToFile(): void
    {
        $path = $this->file_path ?? $this->getDefaultFilePath();
        $fullPath = base_path($path);

        $directory = dirname($fullPath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = $this->formatAsMarkdown();
        File::put($fullPath, $content);

        if (! $this->file_path) {
            $this->updateQuietly(['file_path' => $path]);
        }
    }

    public function formatAsMarkdown(): string
    {
        $module = $this->module->label();
        $date = $this->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');

        return <<<MARKDOWN
# {$this->title}

**Module:** {$module}
**Date:** {$date}
**Findings:** {$this->findings_count}
**Proposals Created:** {$this->proposals_created}

---

## Summary

{$this->summary}

---

## Full Report

{$this->content}
MARKDOWN;
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/ResearchReport.php
git commit -m "feat: add ResearchReport model"
```

---

## Task 10: Create PromotionDirectory Seeder

**Files:**
- Create: `database/seeders/PromotionDirectorySeeder.php`

**Step 1: Create seeder**

```php
<?php

namespace Database\Seeders;

use App\Enums\DirectoryCategory;
use App\Models\PromotionDirectory;
use Illuminate\Database\Seeder;

class PromotionDirectorySeeder extends Seeder
{
    public function run(): void
    {
        $directories = [
            // Developer/API Directories
            [
                'name' => 'RapidAPI Hub',
                'url' => 'https://rapidapi.com/hub',
                'category' => DirectoryCategory::ApiDirectory,
                'submission_type' => 'free',
                'submission_url' => 'https://rapidapi.com/provider',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'Public APIs',
                'url' => 'https://github.com/public-apis/public-apis',
                'category' => DirectoryCategory::ApiDirectory,
                'submission_type' => 'free',
                'submission_url' => 'https://github.com/public-apis/public-apis/blob/master/CONTRIBUTING.md',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'API List',
                'url' => 'https://apilist.fun',
                'category' => DirectoryCategory::ApiDirectory,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'ProgrammableWeb',
                'url' => 'https://www.programmableweb.com',
                'category' => DirectoryCategory::ApiDirectory,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],

            // Product Hunt & Alternatives
            [
                'name' => 'Product Hunt',
                'url' => 'https://www.producthunt.com',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'submission_url' => 'https://www.producthunt.com/posts/new',
                'requirements' => ['needs_account' => true, 'review_time' => '1-2 days'],
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'AlternativeTo',
                'url' => 'https://alternativeto.net',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'G2',
                'url' => 'https://www.g2.com',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'Capterra',
                'url' => 'https://www.capterra.com',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'SaaSHub',
                'url' => 'https://www.saashub.com',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],

            // Startup Lists
            [
                'name' => 'BetaList',
                'url' => 'https://betalist.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'submission_url' => 'https://betalist.com/submit',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'Launching Next',
                'url' => 'https://www.launchingnext.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'StartupStash',
                'url' => 'https://startupstash.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'KillerStartups',
                'url' => 'https://killerstartups.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],

            // Indie/Maker
            [
                'name' => 'IndieHackers',
                'url' => 'https://www.indiehackers.com',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'requirements' => ['needs_account' => true],
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'SideProjectors',
                'url' => 'https://www.sideprojectors.com',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => '1000 Tools',
                'url' => 'https://1000.tools',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],

            // Technical Communities
            [
                'name' => 'Hacker News (Show HN)',
                'url' => 'https://news.ycombinator.com/show',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'requirements' => ['needs_account' => true, 'format' => 'Show HN: Title - Description'],
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'Dev.to',
                'url' => 'https://dev.to',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'Hashnode',
                'url' => 'https://hashnode.com',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'Reddit r/SideProject',
                'url' => 'https://www.reddit.com/r/SideProject',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'Reddit r/webdev',
                'url' => 'https://www.reddit.com/r/webdev',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'Reddit r/SaaS',
                'url' => 'https://www.reddit.com/r/SaaS',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],

            // SEO/Backlinks
            [
                'name' => 'Crunchbase',
                'url' => 'https://www.crunchbase.com',
                'category' => DirectoryCategory::Seo,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],
            [
                'name' => 'F6S',
                'url' => 'https://www.f6s.com',
                'category' => DirectoryCategory::Seo,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'rezensionsheld'],
            ],

            // Free Tool Directories
            [
                'name' => 'Free for Developers',
                'url' => 'https://free-for.dev',
                'category' => DirectoryCategory::Developer,
                'submission_type' => 'free',
                'submission_url' => 'https://github.com/ripienaar/free-for-dev',
                'suitable_products' => ['scrappa'],
            ],
            [
                'name' => 'LibHunt',
                'url' => 'https://www.libhunt.com',
                'category' => DirectoryCategory::Developer,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa'],
            ],
        ];

        foreach ($directories as $directory) {
            PromotionDirectory::updateOrCreate(
                ['url' => $directory['url']],
                $directory
            );
        }
    }
}
```

**Step 2: Run seeder**

Run: `php artisan db:seed --class=PromotionDirectorySeeder`
Expected: Seeding completed

**Step 3: Commit**

```bash
git add database/seeders/PromotionDirectorySeeder.php
git commit -m "feat: add PromotionDirectorySeeder with 25+ directories"
```

---

## Task 11: Create Research Prompt Templates

**Files:**
- Create: `resources/prompts/research/api-opportunities.md`
- Create: `resources/prompts/research/seo-keywords.md`
- Create: `resources/prompts/research/competitor-intel.md`
- Create: `resources/prompts/research/feature-ideas.md`
- Create: `resources/prompts/research/social-listener.md`
- Create: `resources/prompts/research/promotion-finder.md`

**Step 1: Create prompts directory and api-opportunities prompt**

```bash
mkdir -p resources/prompts/research
```

Create `resources/prompts/research/api-opportunities.md`:

```markdown
# Research Task: API Opportunities

You are researching new API opportunities for Scrappa (scrappa.co), an API marketplace on RapidAPI.

## Your Tools
Use Scrappa MCP tools:
- `mcp__scrappa__search` - Google search for API demand
- `mcp__scrappa__google_search_autocomplete` - Search suggestions
- Playwright MCP for browsing RapidAPI

## Research Steps

1. **Search for API demand signals:**
   - "api for [topic]" searches
   - "how to scrape [platform]" searches
   - "[platform] api alternative" searches

2. **Check RapidAPI trending:**
   - Browse trending/new sections
   - Look for gaps in our coverage

3. **Analyze competitor APIs:**
   - What do competitors offer that we don't?
   - What's their pricing?

## Current Scrappa APIs
{{CURRENT_APIS}}

## Output Requirements

For each opportunity found, create a Proposal:
- title: "New API: [Name]"
- description: What it does, market demand evidence
- priority: based on demand signals
- project: "scrappa"
- proposed_action: {"type": "new_api", "platform": "[name]", "endpoints": [...]}

Save a detailed report with all findings.

## Quality Bar
Only propose APIs that:
- Have clear demand (search volume, forum requests)
- Are technically feasible
- Don't duplicate existing offerings
```

**Step 2: Create seo-keywords prompt**

Create `resources/prompts/research/seo-keywords.md`:

```markdown
# Research Task: SEO & Keywords

You are analyzing SEO opportunities for our products.

## Products
- **Scrappa** (scrappa.co) - API marketplace
- **LTO2** - Online video downloader
- **Rezensionsheld** - Review management

## Your Tools
- `mcp__scrappa__search` - Check current rankings
- `mcp__scrappa__google_search_autocomplete` - Keyword ideas

## Research Steps

1. **Check current rankings:**
   - Search our brand names
   - Search our key product terms

2. **Find keyword opportunities:**
   - Long-tail keywords in our niches
   - Questions people ask (how to, what is, etc.)
   - Competitor brand + alternative searches

3. **Content gaps:**
   - Topics we should write about
   - Pages we should create

## Output Requirements

Create Proposals for:
- New landing pages needed
- Blog posts to write
- Meta/title improvements

Save report with keyword data and recommendations.
```

**Step 3: Create competitor-intel prompt**

Create `resources/prompts/research/competitor-intel.md`:

```markdown
# Research Task: Competitor Intelligence

Monitor our competitors for changes, new features, and opportunities.

## Our Products & Competitors

### Scrappa
- ScrapingBee, Apify, Bright Data, ScraperAPI

### LTO2
- savefrom.net, y2mate, ssyoutube

### Rezensionsheld
- ReviewTrackers, Birdeye, Podium

## Your Tools
- `mcp__scrappa__search` - Search for competitor news
- `mcp__scrappa__similarweb` - Traffic analytics
- `mcp__scrappa__google_news` - Recent news

## Research Steps

1. **Pricing changes** - Check competitor pricing pages
2. **New features** - Search "[competitor] new feature"
3. **Market positioning** - How are they marketing?
4. **Gaps** - What do they NOT offer that we could?

## Output Requirements

Create Proposals for:
- Pricing adjustments
- Features to add
- Marketing angles to try

Save report with competitor analysis.
```

**Step 4: Create feature-ideas prompt**

Create `resources/prompts/research/feature-ideas.md`:

```markdown
# Research Task: Feature Ideas

Find feature requests and pain points from user communities.

## Your Tools
- `mcp__scrappa__search` - Search Reddit, forums
- `mcp__scrappa__google_news` - Industry news

## Sources to Check

1. **Reddit:**
   - r/webdev, r/SaaS, r/Entrepreneur
   - r/youtube (for LTO2)
   - Search: "[product type] feature request"

2. **Hacker News:**
   - Search for discussions about our product types

3. **Twitter/X:**
   - Complaints about competitors
   - Feature requests

## Output Requirements

Create Proposals for new features with:
- Source (where you found the request)
- Demand evidence (upvotes, comments)
- Implementation complexity estimate

Save report with all feature ideas found.
```

**Step 5: Create social-listener prompt**

Create `resources/prompts/research/social-listener.md`:

```markdown
# Research Task: Social Listener

Monitor mentions of our products and relevant industry news.

## Monitor For

1. **Brand mentions:**
   - Scrappa, scrappa.co
   - LTO2
   - Rezensionsheld

2. **Industry news:**
   - API/scraping news
   - Video downloading regulations
   - Review platform changes

3. **Competitor mentions:**
   - What are people saying about competitors?

## Your Tools
- `mcp__scrappa__search` - Search social platforms
- `mcp__scrappa__google_news` - Industry news

## Output Requirements

Create Proposals for:
- Responding to mentions
- Addressing complaints
- Capitalizing on competitor issues

Save report with all mentions and sentiment analysis.
```

**Step 6: Create promotion-finder prompt**

Create `resources/prompts/research/promotion-finder.md`:

```markdown
# Research Task: Promotion Finder

Find new directories and platforms to promote our products.

## Your Tools
- `mcp__scrappa__search` - Find directories
- Database: Check existing directories in promotion_directories table

## Research Steps

1. **Search for directories:**
   - "submit startup"
   - "api directory"
   - "saas directory"
   - "free tools list"
   - "product hunt alternatives"

2. **Check existing directories:**
   - Query promotion_directories table
   - Find ones we haven't submitted to

3. **Evaluate new directories:**
   - Domain authority
   - Relevance to our products
   - Free vs paid

## Output Requirements

Create Proposals for:
- New directories to add to database
- Submissions to make

Save report with directory analysis.
```

**Step 7: Commit**

```bash
git add resources/prompts/research/
git commit -m "feat: add research module prompt templates"
```

---

## Task 12: Create ResearchService

**Files:**
- Create: `app/Services/ResearchService.php`

**Step 1: Create service**

```php
<?php

namespace App\Services;

use App\Enums\ResearchModule;
use App\Models\Proposal;
use App\Models\ResearchReport;
use App\Models\Task;
use Illuminate\Support\Facades\File;

class ResearchService
{
    public function getPromptForModule(ResearchModule $module): string
    {
        $promptPath = resource_path("prompts/research/{$module->value}.md");

        if (! File::exists($promptPath)) {
            throw new \RuntimeException("Prompt not found for module: {$module->value}");
        }

        $prompt = File::get($promptPath);

        // Inject dynamic data based on module
        return $this->injectPromptData($module, $prompt);
    }

    protected function injectPromptData(ResearchModule $module, string $prompt): string
    {
        return match ($module) {
            ResearchModule::ApiOpportunities => $this->injectApiData($prompt),
            ResearchModule::PromotionFinder => $this->injectDirectoryData($prompt),
            default => $prompt,
        };
    }

    protected function injectApiData(string $prompt): string
    {
        // TODO: Inject current Scrappa API list from database or config
        $currentApis = implode("\n", [
            '- Google Maps (search, reviews, details)',
            '- YouTube (videos, channels, comments)',
            '- Amazon (search, products, reviews)',
            '- LinkedIn (profiles, companies)',
            '- Trustpilot (reviews)',
            '- Kununu (reviews)',
            '- Indeed Jobs',
            '- Google Flights',
            '- Vinted',
            '- And more...',
        ]);

        return str_replace('{{CURRENT_APIS}}', $currentApis, $prompt);
    }

    protected function injectDirectoryData(string $prompt): string
    {
        // Could inject list of already-submitted directories
        return $prompt;
    }

    public function createResearchTask(ResearchModule $module): Task
    {
        $prompt = $this->getPromptForModule($module);

        $task = Task::create([
            'title' => "Research: {$module->label()}",
            'status' => \App\Enums\TaskStatus::Pending,
        ]);

        // The task will be picked up by z.ai with this prompt
        // Store the prompt in task metadata or first message
        $task->messages()->create([
            'role' => \App\Enums\MessageRole::User,
            'content' => $prompt,
        ]);

        return $task;
    }

    public function saveReport(
        ResearchModule $module,
        string $title,
        string $summary,
        string $content,
        int $findingsCount,
        int $proposalsCreated,
        ?Task $task = null
    ): ResearchReport {
        return ResearchReport::create([
            'module' => $module,
            'title' => $title,
            'summary' => $summary,
            'content' => $content,
            'findings_count' => $findingsCount,
            'proposals_created' => $proposalsCreated,
            'task_id' => $task?->id,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/ResearchService.php
git commit -m "feat: add ResearchService for managing research tasks"
```

---

## Task 13: Create Research Run Command

**Files:**
- Create: `app/Console/Commands/ResearchRunCommand.php`

**Step 1: Create command**

```php
<?php

namespace App\Console\Commands;

use App\Enums\ResearchModule;
use App\Services\ResearchService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ResearchRunCommand extends Command
{
    protected $signature = 'research:run
                            {module? : The research module to run (api_opportunities, seo_keywords, etc.)}
                            {--all : Run all research modules}';

    protected $description = 'Run a research module to find opportunities and create proposals';

    public function handle(ResearchService $researchService, TelegramService $telegramService): int
    {
        if ($this->option('all')) {
            return $this->runAllModules($researchService, $telegramService);
        }

        $moduleName = $this->argument('module');

        if (! $moduleName) {
            $moduleName = $this->choice(
                'Which research module would you like to run?',
                array_map(fn ($m) => $m->value, ResearchModule::cases()),
                0
            );
        }

        $module = ResearchModule::tryFrom($moduleName);

        if (! $module) {
            $this->error("Invalid module: {$moduleName}");
            $this->info('Available modules: ' . implode(', ', array_map(fn ($m) => $m->value, ResearchModule::cases())));

            return self::FAILURE;
        }

        return $this->runModule($module, $researchService, $telegramService);
    }

    protected function runModule(
        ResearchModule $module,
        ResearchService $researchService,
        TelegramService $telegramService
    ): int {
        $this->info("Starting research: {$module->label()}");

        try {
            $task = $researchService->createResearchTask($module);

            $this->info("Created research task: {$task->uuid}");
            $this->info("Task will be processed by z.ai");

            // Notify via Telegram
            try {
                $telegramService->sendMessage(
                    "🔬 *Research Started*\n\n" .
                    "*Module:* {$module->label()}\n" .
                    "*Task ID:* `{$task->uuid}`\n\n" .
                    "Research is now running..."
                );
            } catch (\Exception $e) {
                $this->warn("Could not send Telegram notification: {$e->getMessage()}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to start research: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function runAllModules(
        ResearchService $researchService,
        TelegramService $telegramService
    ): int {
        $this->info('Running all research modules...');

        $results = [];
        foreach (ResearchModule::cases() as $module) {
            $result = $this->runModule($module, $researchService, $telegramService);
            $results[$module->value] = $result === self::SUCCESS;

            // Small delay between modules
            sleep(2);
        }

        $successful = count(array_filter($results));
        $total = count($results);

        $this->info("Completed: {$successful}/{$total} modules started successfully");

        return $successful === $total ? self::SUCCESS : self::FAILURE;
    }
}
```

**Step 2: Test command is registered**

Run: `php artisan list research`
Expected: Shows `research:run` command

**Step 3: Commit**

```bash
git add app/Console/Commands/ResearchRunCommand.php
git commit -m "feat: add research:run artisan command"
```

---

## Task 14: Configure Scheduler for Continuous Research

**Files:**
- Modify: `routes/console.php`

**Step 1: Add scheduled research tasks**

Add to `routes/console.php`:

```php
use App\Enums\ResearchModule;
use Illuminate\Support\Facades\Schedule;

// Research Pipeline - Continuous research every 2 hours, staggered by module
foreach (ResearchModule::cases() as $module) {
    Schedule::command("research:run {$module->value}")
        ->everyTwoHours()
        ->at(sprintf('00:%02d', $module->scheduledOffsetMinutes()))
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/research.log'));
}
```

**Step 2: Verify schedule**

Run: `php artisan schedule:list`
Expected: Shows 6 research:run commands scheduled

**Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat: configure scheduler for continuous research pipeline"
```

---

## Task 15: Create Filament Resources for Research

**Files:**
- Create: `app/Filament/Resources/PromotionDirectoryResource.php`
- Create: `app/Filament/Resources/PromotionDirectoryResource/Pages/ListPromotionDirectories.php`
- Create: `app/Filament/Resources/PromotionDirectoryResource/Pages/CreatePromotionDirectory.php`
- Create: `app/Filament/Resources/PromotionDirectoryResource/Pages/EditPromotionDirectory.php`
- Create: `app/Filament/Resources/ResearchReportResource.php`
- Create: `app/Filament/Resources/ResearchReportResource/Pages/ListResearchReports.php`
- Create: `app/Filament/Resources/ResearchReportResource/Pages/ViewResearchReport.php`

**Step 1: Create PromotionDirectoryResource**

```php
<?php

namespace App\Filament\Resources;

use App\Enums\DirectoryCategory;
use App\Filament\Resources\PromotionDirectoryResource\Pages;
use App\Models\PromotionDirectory;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PromotionDirectoryResource extends Resource
{
    protected static ?string $model = PromotionDirectory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->columnSpanFull()->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('url')
                        ->required()
                        ->url()
                        ->maxLength(255),

                    Forms\Components\Select::make('category')
                        ->options(DirectoryCategory::class)
                        ->required(),

                    Forms\Components\Select::make('submission_type')
                        ->options([
                            'free' => 'Free',
                            'paid' => 'Paid',
                            'invite_only' => 'Invite Only',
                        ])
                        ->required(),

                    Forms\Components\TextInput::make('submission_url')
                        ->url()
                        ->maxLength(255),

                    Forms\Components\CheckboxList::make('suitable_products')
                        ->options([
                            'scrappa' => 'Scrappa',
                            'rezensionsheld' => 'Rezensionsheld',
                        ]),

                    Forms\Components\Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('submission_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'free' => 'success',
                        'paid' => 'warning',
                        'invite_only' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Submissions'),

                Tables\Columns\TextColumn::make('url')
                    ->limit(30)
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(DirectoryCategory::class),

                Tables\Filters\SelectFilter::make('submission_type')
                    ->options([
                        'free' => 'Free',
                        'paid' => 'Paid',
                        'invite_only' => 'Invite Only',
                    ]),
            ])
            ->recordActions([
                Actions\EditAction::make(),
                Actions\Action::make('visit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn ($record) => $record->url, shouldOpenInNewTab: true),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotionDirectories::route('/'),
            'create' => Pages\CreatePromotionDirectory::route('/create'),
            'edit' => Pages\EditPromotionDirectory::route('/{record}/edit'),
        ];
    }
}
```

**Step 2: Create resource pages**

Create `app/Filament/Resources/PromotionDirectoryResource/Pages/ListPromotionDirectories.php`:

```php
<?php

namespace App\Filament\Resources\PromotionDirectoryResource\Pages;

use App\Filament\Resources\PromotionDirectoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPromotionDirectories extends ListRecords
{
    protected static string $resource = PromotionDirectoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/Resources/PromotionDirectoryResource/Pages/CreatePromotionDirectory.php`:

```php
<?php

namespace App\Filament\Resources\PromotionDirectoryResource\Pages;

use App\Filament\Resources\PromotionDirectoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotionDirectory extends CreateRecord
{
    protected static string $resource = PromotionDirectoryResource::class;
}
```

Create `app/Filament/Resources/PromotionDirectoryResource/Pages/EditPromotionDirectory.php`:

```php
<?php

namespace App\Filament\Resources\PromotionDirectoryResource\Pages;

use App\Filament\Resources\PromotionDirectoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotionDirectory extends EditRecord
{
    protected static string $resource = PromotionDirectoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
```

**Step 3: Create ResearchReportResource**

```php
<?php

namespace App\Filament\Resources;

use App\Enums\ResearchModule;
use App\Filament\Resources\ResearchReportResource\Pages;
use App\Models\ResearchReport;
use BackedEnum;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class ResearchReportResource extends Resource
{
    protected static ?string $model = ResearchReport::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('module')
                    ->badge()
                    ->formatStateUsing(fn (ResearchModule $state) => $state->label())
                    ->color(fn (ResearchModule $state) => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('findings_count')
                    ->label('Findings')
                    ->sortable(),

                Tables\Columns\TextColumn::make('proposals_created')
                    ->label('Proposals')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('module')
                    ->options(ResearchModule::class),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResearchReports::route('/'),
            'view' => Pages\ViewResearchReport::route('/{record}'),
        ];
    }
}
```

**Step 4: Create ResearchReport pages**

Create `app/Filament/Resources/ResearchReportResource/Pages/ListResearchReports.php`:

```php
<?php

namespace App\Filament\Resources\ResearchReportResource\Pages;

use App\Filament\Resources\ResearchReportResource;
use Filament\Resources\Pages\ListRecords;

class ListResearchReports extends ListRecords
{
    protected static string $resource = ResearchReportResource::class;
}
```

Create `app/Filament/Resources/ResearchReportResource/Pages/ViewResearchReport.php`:

```php
<?php

namespace App\Filament\Resources\ResearchReportResource\Pages;

use App\Filament\Resources\ResearchReportResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ViewResearchReport extends ViewRecord
{
    protected static string $resource = ResearchReportResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Report Details')
                    ->schema([
                        TextEntry::make('module')
                            ->badge(),
                        TextEntry::make('title'),
                        TextEntry::make('findings_count')
                            ->label('Findings'),
                        TextEntry::make('proposals_created')
                            ->label('Proposals Created'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(3),

                Section::make('Summary')
                    ->schema([
                        TextEntry::make('summary')
                            ->prose()
                            ->columnSpanFull(),
                    ]),

                Section::make('Full Report')
                    ->schema([
                        TextEntry::make('content')
                            ->prose()
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
```

**Step 5: Commit**

```bash
git add app/Filament/Resources/PromotionDirectoryResource.php
git add app/Filament/Resources/PromotionDirectoryResource/
git add app/Filament/Resources/ResearchReportResource.php
git add app/Filament/Resources/ResearchReportResource/
git commit -m "feat: add Filament resources for directories and research reports"
```

---

## Task 16: Run All Migrations and Seeders

**Step 1: Run migrations**

Run: `php artisan migrate`

**Step 2: Run seeder**

Run: `php artisan db:seed --class=PromotionDirectorySeeder`

**Step 3: Verify**

Run: `php artisan tinker --execute="echo App\Models\PromotionDirectory::count() . ' directories seeded'"`
Expected: `25+ directories seeded`

**Step 4: Final commit**

```bash
git add -A
git commit -m "feat: complete research pipeline implementation"
```

---

## Summary

This implementation creates:

1. **3 Enums** - ResearchModule, DirectoryCategory, SubmissionStatus
2. **3 Models** - PromotionDirectory, DirectorySubmission, ResearchReport
3. **3 Migrations** - For new tables
4. **1 Seeder** - 25+ promotion directories
5. **6 Prompt Templates** - One per research module
6. **1 Service** - ResearchService for task management
7. **1 Artisan Command** - `research:run {module}`
8. **Scheduler Config** - Continuous research every 2 hours
9. **2 Filament Resources** - Directory and Report management

Research runs continuously via z.ai tasks, creates Proposals for your approval, and saves detailed reports to the database and `docs/research/` directory.
