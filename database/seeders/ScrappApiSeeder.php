<?php

namespace Database\Seeders;

use App\Models\ScrappApi;
use Illuminate\Database\Seeder;

class ScrappApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apis = [
            [
                'name' => 'Google Maps',
                'slug' => 'google-maps',
                'route_prefix' => 'api/google-maps',
                'rapidapi_slug' => 'google-maps-scraper',
            ],
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'route_prefix' => 'api/amazon',
                'rapidapi_slug' => 'amazon-scraper',
            ],
            [
                'name' => 'TrustedShops',
                'slug' => 'trustedshops',
                'route_prefix' => 'api/trustedshops',
                'rapidapi_slug' => 'trustedshops-scraper',
            ],
            [
                'name' => 'YouTube',
                'slug' => 'youtube',
                'route_prefix' => 'api/youtube',
                'rapidapi_slug' => 'youtube-scraper',
            ],
            [
                'name' => 'LinkedIn',
                'slug' => 'linkedin',
                'route_prefix' => 'api/linkedin',
                'rapidapi_slug' => 'linkedin-scraper',
            ],
            [
                'name' => 'Instagram',
                'slug' => 'instagram',
                'route_prefix' => 'api/instagram',
                'rapidapi_slug' => 'instagram-scraper',
            ],
            [
                'name' => 'Twitter',
                'slug' => 'twitter',
                'route_prefix' => 'api/twitter',
                'rapidapi_slug' => 'twitter-scraper',
            ],
            [
                'name' => 'TikTok',
                'slug' => 'tiktok',
                'route_prefix' => 'api/tiktok',
                'rapidapi_slug' => 'tiktok-scraper',
            ],
            [
                'name' => 'Facebook',
                'slug' => 'facebook',
                'route_prefix' => 'api/facebook',
                'rapidapi_slug' => 'facebook-scraper',
            ],
            [
                'name' => 'Yelp',
                'slug' => 'yelp',
                'route_prefix' => 'api/yelp',
                'rapidapi_slug' => 'yelp-scraper',
            ],
        ];

        foreach ($apis as $api) {
            ScrappApi::updateOrCreate(
                ['slug' => $api['slug']],
                $api + ['is_active' => true]
            );
        }
    }
}
