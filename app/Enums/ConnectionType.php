<?php

namespace App\Enums;

enum ConnectionType: string
{
    case GitHub = 'github';
    case GoogleAnalytics = 'google_analytics';
    case SearchConsole = 'search_console';
    case Asana = 'asana';

    public function label(): string
    {
        return match ($this) {
            self::GitHub => 'GitHub',
            self::GoogleAnalytics => 'Google Analytics',
            self::SearchConsole => 'Search Console',
            self::Asana => 'Asana',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GitHub => 'heroicon-o-code-bracket',
            self::GoogleAnalytics => 'heroicon-o-chart-bar',
            self::SearchConsole => 'heroicon-o-magnifying-glass',
            self::Asana => 'heroicon-o-clipboard-document-list',
        };
    }
}
