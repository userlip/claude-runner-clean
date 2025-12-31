<?php

namespace App\Enums;

enum ProposalType: string
{
    case ImplementApi = 'implement_api';
    case FixScraper = 'fix_scraper';
    case ResearchOpportunity = 'research_opportunity';
    case SeoImprovement = 'seo_improvement';
    case PublishApi = 'publish_api';
    case DeployEndpoint = 'deploy_endpoint';
    case FeatureRequest = 'feature_request';
    case BugFix = 'bug_fix';
    case Refactor = 'refactor';
    case Documentation = 'documentation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ImplementApi => 'Implement API',
            self::FixScraper => 'Fix Scraper',
            self::ResearchOpportunity => 'Research Opportunity',
            self::SeoImprovement => 'SEO Improvement',
            self::PublishApi => 'Publish API',
            self::DeployEndpoint => 'Deploy Endpoint',
            self::FeatureRequest => 'Feature Request',
            self::BugFix => 'Bug Fix',
            self::Refactor => 'Refactor',
            self::Documentation => 'Documentation',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ImplementApi => 'heroicon-o-code-bracket',
            self::FixScraper => 'heroicon-o-wrench-screwdriver',
            self::ResearchOpportunity => 'heroicon-o-magnifying-glass',
            self::SeoImprovement => 'heroicon-o-chart-bar',
            self::PublishApi => 'heroicon-o-cloud-arrow-up',
            self::DeployEndpoint => 'heroicon-o-rocket-launch',
            self::FeatureRequest => 'heroicon-o-sparkles',
            self::BugFix => 'heroicon-o-bug-ant',
            self::Refactor => 'heroicon-o-arrow-path',
            self::Documentation => 'heroicon-o-document-text',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ImplementApi => 'primary',
            self::FixScraper => 'danger',
            self::ResearchOpportunity => 'info',
            self::SeoImprovement => 'success',
            self::PublishApi => 'warning',
            self::DeployEndpoint => 'primary',
            self::FeatureRequest => 'info',
            self::BugFix => 'danger',
            self::Refactor => 'gray',
            self::Documentation => 'gray',
            self::Other => 'gray',
        };
    }

    /**
     * @return array<string>
     */
    public function getRequiredSkills(): array
    {
        return match ($this) {
            self::ImplementApi => [
                'api-discovery',
                'github-api-research',
                'proxy-viability-test',
                'superpowers:writing-plans',
                'scrappa-deploy-and-test',
                'scrappa-endpoint-testing',
            ],
            self::FixScraper => [
                'proxy-viability-test',
                'superpowers:systematic-debugging',
            ],
            self::ResearchOpportunity => [
                'market-research',
                'api-discovery',
            ],
            self::SeoImprovement => [
                'market-research',
            ],
            self::PublishApi => [
                'rapidapi-publishing',
                'scrappa-endpoint-testing',
            ],
            self::DeployEndpoint => [
                'scrappa-deploy-and-test',
                'scrappa-endpoint-testing',
            ],
            self::FeatureRequest => [
                'superpowers:brainstorming',
                'superpowers:writing-plans',
            ],
            self::BugFix => [
                'superpowers:systematic-debugging',
            ],
            self::Refactor => [
                'superpowers:writing-plans',
            ],
            self::Documentation => [],
            self::Other => [],
        };
    }
}
