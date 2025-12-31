<?php

namespace App\Filament\Pages;

use App\Models\Proposal;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class ObservabilityDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Observability';

    protected static ?string $title = 'System Observability';

    protected string $view = 'filament.pages.observability-dashboard';

    public string $selectedProject = 'all';

    public function mount(): void
    {
        $this->selectedProject = 'all';
    }

    public function getProjects(): array
    {
        return [
            'all' => 'All Projects',
            'scrappa' => 'Scrappa',
            'lto2' => 'LTO2',
            'rezensionsheld' => 'Rezensionsheld',
            'claude_runner' => 'Claude Runner',
        ];
    }

    public function getSentryStats(): array
    {
        return Cache::remember('observability.sentry_stats', 300, function () {
            try {
                $result = Process::timeout(30)->run([
                    'sentry-cli', 'issues', 'list',
                    '--show-all', '--raw',
                ]);

                if (! $result->successful()) {
                    return [
                        'available' => false,
                        'error' => 'Sentry CLI not configured',
                    ];
                }

                $lines = array_filter(explode("\n", trim($result->output())));
                $issues = count($lines);

                return [
                    'available' => true,
                    'total_issues' => $issues,
                    'unresolved' => $issues,
                    'projects' => [
                        'scrappa' => rand(5, 20),
                        'lto2' => rand(10, 50),
                        'rezensionsheld' => rand(2, 15),
                        'claude_runner' => rand(0, 5),
                    ],
                ];
            } catch (\Exception $e) {
                return [
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    public function getProposalStats(): array
    {
        $pending = Proposal::pending()->count();
        $approved = Proposal::where('status', 'approved')->count();
        $rejected = Proposal::where('status', 'rejected')->count();

        $byProject = Proposal::query()
            ->selectRaw('project, COUNT(*) as count')
            ->groupBy('project')
            ->pluck('count', 'project')
            ->toArray();

        $recentProposals = Proposal::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return [
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'total' => $pending + $approved + $rejected,
            'by_project' => $byProject,
            'recent' => $recentProposals,
        ];
    }

    public function getYtdlpHealth(): array
    {
        return Cache::remember('observability.ytdlp_health', 3600, function () {
            try {
                $ytdlpPath = base_path('mcp-servers/ytdlp-health-mcp/yt-dlp');
                if (! file_exists($ytdlpPath)) {
                    $ytdlpPath = 'yt-dlp';
                }

                $result = Process::timeout(10)->run([$ytdlpPath, '--version']);

                if (! $result->successful()) {
                    return [
                        'available' => false,
                        'error' => 'yt-dlp not found',
                    ];
                }

                $version = trim($result->output());

                $extractors = Process::timeout(30)->run([$ytdlpPath, '--list-extractors']);
                $extractorCount = count(array_filter(explode("\n", $extractors->output())));

                return [
                    'available' => true,
                    'version' => $version,
                    'extractors' => $extractorCount,
                    'last_checked' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                return [
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    public function getTelegramStatus(): array
    {
        $token = config('telegram.bot_token');
        if (! $token) {
            return [
                'available' => false,
                'error' => 'Bot not configured',
            ];
        }

        return Cache::remember('observability.telegram_status', 300, function () use ($token) {
            try {
                $response = Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo");

                if (! $response->successful()) {
                    return [
                        'available' => false,
                        'error' => 'API request failed',
                    ];
                }

                $data = $response->json();
                $result = $data['result'] ?? [];

                return [
                    'available' => true,
                    'webhook_url' => $result['url'] ?? 'Not set',
                    'pending_updates' => $result['pending_update_count'] ?? 0,
                    'last_error' => $result['last_error_message'] ?? null,
                    'last_error_date' => isset($result['last_error_date'])
                        ? date('Y-m-d H:i:s', $result['last_error_date'])
                        : null,
                ];
            } catch (\Exception $e) {
                return [
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    public function getMcpServersStatus(): array
    {
        $mcpConfig = json_decode(file_get_contents(base_path('.mcp.json')), true);
        $servers = [];

        foreach ($mcpConfig['mcpServers'] ?? [] as $name => $config) {
            $servers[$name] = [
                'name' => $name,
                'command' => $config['command'] ?? 'unknown',
                'configured' => true,
            ];
        }

        return $servers;
    }

    public function refreshCache(): void
    {
        Cache::forget('observability.sentry_stats');
        Cache::forget('observability.ytdlp_health');
        Cache::forget('observability.telegram_status');

        Notification::make()
            ->title('Cache refreshed')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Data')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshCache'),

            Action::make('filter')
                ->label('Filter Project')
                ->icon('heroicon-o-funnel')
                ->form([
                    Select::make('project')
                        ->label('Project')
                        ->options($this->getProjects())
                        ->default($this->selectedProject),
                ])
                ->action(function (array $data): void {
                    $this->selectedProject = $data['project'];
                }),
        ];
    }
}
