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
