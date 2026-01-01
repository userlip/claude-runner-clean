<?php

namespace Database\Seeders;

use App\Models\ScrappApi;
use Illuminate\Database\Seeder;

class ScrappApiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * These are the actual Scrappa APIs from routes/api.php
     */
    public function run(): void
    {
        $apis = [
            // Google Maps API
            [
                'name' => 'Google Maps',
                'slug' => 'google-maps',
                'route_prefix' => 'api/maps',
                'rapidapi_slug' => 'google-maps-scraper',
            ],
            // Google Images
            [
                'name' => 'Google Images',
                'slug' => 'google-images',
                'route_prefix' => 'api/images',
                'rapidapi_slug' => 'google-images-scraper',
            ],
            // Google Translate
            [
                'name' => 'Google Translate',
                'slug' => 'google-translate',
                'route_prefix' => 'api/google-translate',
                'rapidapi_slug' => 'google-translate-scraper',
            ],
            // Google Jobs
            [
                'name' => 'Google Jobs',
                'slug' => 'google-jobs',
                'route_prefix' => 'api/google/jobs',
                'rapidapi_slug' => 'google-jobs-scraper',
            ],
            // Google News
            [
                'name' => 'Google News',
                'slug' => 'google-news',
                'route_prefix' => 'api/google/news',
                'rapidapi_slug' => 'google-news-scraper',
            ],
            // Google Videos
            [
                'name' => 'Google Videos',
                'slug' => 'google-videos',
                'route_prefix' => 'api/google/videos',
                'rapidapi_slug' => 'google-videos-scraper',
            ],
            // Google Lens
            [
                'name' => 'Google Lens',
                'slug' => 'google-lens',
                'route_prefix' => 'api/google/lens',
                'rapidapi_slug' => 'google-lens-scraper',
            ],
            // Google Search
            [
                'name' => 'Google Search',
                'slug' => 'google-search',
                'route_prefix' => 'api/search',
                'rapidapi_slug' => 'google-search-scraper',
            ],
            // Google Search Light
            [
                'name' => 'Google Search Light',
                'slug' => 'google-search-light',
                'route_prefix' => 'api/search-light',
                'rapidapi_slug' => 'google-search-light-scraper',
            ],
            // Brave Search
            [
                'name' => 'Brave Search',
                'slug' => 'brave-search',
                'route_prefix' => 'api/brave',
                'rapidapi_slug' => 'brave-search-scraper',
            ],
            // Startpage Search
            [
                'name' => 'Startpage Search',
                'slug' => 'startpage',
                'route_prefix' => 'api/startpage',
                'rapidapi_slug' => 'startpage-scraper',
            ],
            // Bing Search
            [
                'name' => 'Bing Search',
                'slug' => 'bing-search',
                'route_prefix' => 'api/bing',
                'rapidapi_slug' => 'bing-search-scraper',
            ],
            // Kununu
            [
                'name' => 'Kununu',
                'slug' => 'kununu',
                'route_prefix' => 'api/kununu',
                'rapidapi_slug' => 'kununu-scraper',
            ],
            // Trustpilot
            [
                'name' => 'Trustpilot',
                'slug' => 'trustpilot',
                'route_prefix' => 'api/trustpilot',
                'rapidapi_slug' => 'trustpilot-scraper',
            ],
            // TrustedShops
            [
                'name' => 'TrustedShops',
                'slug' => 'trustedshops',
                'route_prefix' => 'api/trustedshops',
                'rapidapi_slug' => 'trustedshops-scraper',
            ],
            // Vinted
            [
                'name' => 'Vinted',
                'slug' => 'vinted',
                'route_prefix' => 'api/vinted',
                'rapidapi_slug' => 'vinted-scraper',
            ],
            // LinkedIn
            [
                'name' => 'LinkedIn',
                'slug' => 'linkedin',
                'route_prefix' => 'api/linkedin',
                'rapidapi_slug' => 'linkedin-scraper',
            ],
            // Immobilienscout24
            [
                'name' => 'Immobilienscout24',
                'slug' => 'immobilienscout24',
                'route_prefix' => 'api/immobilienscout24',
                'rapidapi_slug' => 'immobilienscout24-scraper',
            ],
            // Google Flights
            [
                'name' => 'Google Flights',
                'slug' => 'google-flights',
                'route_prefix' => 'api/flights',
                'rapidapi_slug' => 'google-flights-scraper',
            ],
            // Amazon
            [
                'name' => 'Amazon',
                'slug' => 'amazon',
                'route_prefix' => 'api/amazon',
                'rapidapi_slug' => 'amazon-scraper',
            ],
            // Indeed Jobs
            [
                'name' => 'Indeed Jobs',
                'slug' => 'indeed',
                'route_prefix' => 'api/indeed',
                'rapidapi_slug' => 'indeed-scraper',
            ],
            // YouTube External
            [
                'name' => 'YouTube External',
                'slug' => 'youtube-external',
                'route_prefix' => 'api/youtube-external',
                'rapidapi_slug' => 'youtube-external-scraper',
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
