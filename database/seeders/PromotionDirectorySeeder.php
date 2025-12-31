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
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'AlternativeTo',
                'url' => 'https://alternativeto.net',
                'category' => DirectoryCategory::ProductHunt,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
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
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            // Startup Lists
            [
                'name' => 'BetaList',
                'url' => 'https://betalist.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'submission_url' => 'https://betalist.com/submit',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'Launching Next',
                'url' => 'https://www.launchingnext.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'StartupStash',
                'url' => 'https://startupstash.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'KillerStartups',
                'url' => 'https://killerstartups.com',
                'category' => DirectoryCategory::Startup,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            // Indie/Maker
            [
                'name' => 'IndieHackers',
                'url' => 'https://www.indiehackers.com',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'requirements' => ['needs_account' => true],
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'SideProjectors',
                'url' => 'https://www.sideprojectors.com',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2'],
            ],
            [
                'name' => '1000 Tools',
                'url' => 'https://1000.tools',
                'category' => DirectoryCategory::Indie,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            // Technical Communities
            [
                'name' => 'Hacker News (Show HN)',
                'url' => 'https://news.ycombinator.com/show',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'requirements' => ['needs_account' => true, 'format' => 'Show HN: Title - Description'],
                'suitable_products' => ['scrappa', 'lto2'],
            ],
            [
                'name' => 'Dev.to',
                'url' => 'https://dev.to',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2'],
            ],
            [
                'name' => 'Hashnode',
                'url' => 'https://hashnode.com',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2'],
            ],
            [
                'name' => 'Reddit r/SideProject',
                'url' => 'https://www.reddit.com/r/SideProject',
                'category' => DirectoryCategory::Community,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
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
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
            ],
            [
                'name' => 'F6S',
                'url' => 'https://www.f6s.com',
                'category' => DirectoryCategory::Seo,
                'submission_type' => 'free',
                'suitable_products' => ['scrappa', 'lto2', 'rezensionsheld'],
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
