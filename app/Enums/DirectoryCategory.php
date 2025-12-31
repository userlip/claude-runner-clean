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
