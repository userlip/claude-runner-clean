<?php

namespace App\Filament\Resources\TaskSchedules;

use App\Filament\Resources\TaskSchedules\Pages\CreateTaskSchedule;
use App\Filament\Resources\TaskSchedules\Pages\EditTaskSchedule;
use App\Filament\Resources\TaskSchedules\Pages\ListTaskSchedules;
use App\Jobs\RunScheduledTaskJob;
use App\Models\AiProvider;
use App\Models\Repository;
use App\Models\TaskSchedule;
use App\Models\User;
use BackedEnum;
use Cron\CronExpression;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaskScheduleResource extends Resource
{
    protected static ?string $model = TaskSchedule::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Schedules';

    protected static ?string $pluralModelLabel = 'Schedules';

    protected static ?int $navigationSort = 4;

    protected static UnitEnum|string|null $navigationGroup = 'Automation';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Schedule Details')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('repository_id')
                                    ->label('Repository')
                                    ->options(fn () => Repository::where('user_id', Auth::id())
                                        ->pluck('full_name', 'id'))
                                    ->required()
                                    ->searchable(),

                                Forms\Components\Select::make('user_id')
                                    ->label('Owner')
                                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),

                                Forms\Components\Select::make('ai_provider_id')
                                    ->label('AI Provider')
                                    ->options(fn () => AiProvider::where('is_active', true)
                                        ->pluck('display_name', 'id'))
                                    ->required()
                                    ->searchable(),

                                Forms\Components\Textarea::make('prompt')
                                    ->required()
                                    ->rows(6)
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('builder_preset')
                                    ->label('Schedule Preset')
                                    ->options([
                                        'every_minute' => 'Every minute',
                                        'every_5_minutes' => 'Every 5 minutes',
                                        'hourly' => 'Hourly',
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        'custom' => 'Custom (builder)',
                                    ])
                                    ->default('custom')
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if (! $state) {
                                            return;
                                        }

                                        $presets = [
                                            'every_minute' => '* * * * *',
                                            'every_5_minutes' => '*/5 * * * *',
                                            'hourly' => '0 * * * *',
                                            'daily' => '0 3 * * *',
                                            'weekly' => '0 3 * * 1',
                                            'monthly' => '0 3 1 * *',
                                        ];

                                        if ($state === 'custom') {
                                            return;
                                        }

                                        $set('cron_expression', $presets[$state] ?? '0 * * * *');
                                    }),

                                Section::make('Builder')
                                    ->schema([
                                        Forms\Components\Select::make('builder_config.minute')
                                            ->label('Minute')
                                            ->options(self::minuteOptions())
                                            ->default('0')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCronFromBuilder($get, $set)),

                                        Forms\Components\Select::make('builder_config.hour')
                                            ->label('Hour')
                                            ->options(self::hourOptions())
                                            ->default('*')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCronFromBuilder($get, $set)),

                                        Forms\Components\Select::make('builder_config.day_of_month')
                                            ->label('Day of Month')
                                            ->options(self::dayOfMonthOptions())
                                            ->default('*')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCronFromBuilder($get, $set)),

                                        Forms\Components\Select::make('builder_config.month')
                                            ->label('Month')
                                            ->options(self::monthOptions())
                                            ->default('*')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCronFromBuilder($get, $set)),

                                        Forms\Components\Select::make('builder_config.day_of_week')
                                            ->label('Day of Week')
                                            ->options(self::dayOfWeekOptions())
                                            ->default('*')
                                            ->live()
                                            ->afterStateUpdated(fn (Get $get, Set $set) => self::syncCronFromBuilder($get, $set)),
                                    ])
                                    ->columns(5)
                                    ->visible(fn (Get $get) => $get('builder_preset') === 'custom'),

                                Forms\Components\TextInput::make('cron_expression')
                                    ->label('Cron Expression')
                                    ->required()
                                    ->helperText('Server timezone')
                                    ->rules([
                                        'string',
                                        'max:255',
                                        function (string $attribute, $value, $fail) {
                                            try {
                                                new CronExpression((string) $value);
                                            } catch (\Throwable $e) {
                                                $fail('Invalid cron expression.');
                                            }
                                        },
                                    ]),

                                Forms\Components\TextInput::make('delete_after_minutes')
                                    ->label('Delete After (minutes)')
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                            ])
                            ->columns(2),
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
                Tables\Columns\TextColumn::make('repository.full_name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cron_expression')
                    ->label('Cron')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_run')
                    ->label('Next Run')
                    ->getStateUsing(fn (TaskSchedule $record) => self::nextRunLabel($record))
                    ->sortable(false),
                Tables\Columns\TextColumn::make('last_run_status')
                    ->label('Last Status')
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->default(true),
            ])
            ->recordActions([
                Actions\Action::make('run_now')
                    ->label('Run now')
                    ->icon('heroicon-o-play')
                    ->action(fn (TaskSchedule $record) => RunScheduledTaskJob::dispatch($record->id)),
                Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Auth::id());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskSchedules::route('/'),
            'create' => CreateTaskSchedule::route('/create'),
            'edit' => EditTaskSchedule::route('/{record}/edit'),
        ];
    }

    protected static function syncCronFromBuilder(Get $get, Set $set): void
    {
        $builder = $get('builder_config') ?? [];

        $minute = $builder['minute'] ?? '0';
        $hour = $builder['hour'] ?? '*';
        $dayOfMonth = $builder['day_of_month'] ?? '*';
        $month = $builder['month'] ?? '*';
        $dayOfWeek = $builder['day_of_week'] ?? '*';

        $set('cron_expression', "{$minute} {$hour} {$dayOfMonth} {$month} {$dayOfWeek}");
    }

    protected static function nextRunLabel(TaskSchedule $record): string
    {
        try {
            $expression = new CronExpression($record->cron_expression);

            return $expression->getNextRunDate()->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    }

    protected static function minuteOptions(): array
    {
        return self::numericOptions(0, 59, ['*' => 'Every minute', '*/5' => 'Every 5 min', '*/15' => 'Every 15 min', '*/30' => 'Every 30 min']);
    }

    protected static function hourOptions(): array
    {
        return self::numericOptions(0, 23, ['*' => 'Every hour', '*/2' => 'Every 2 hours', '*/6' => 'Every 6 hours']);
    }

    protected static function dayOfMonthOptions(): array
    {
        return self::numericOptions(1, 31, ['*' => 'Every day']);
    }

    protected static function monthOptions(): array
    {
        return self::numericOptions(1, 12, ['*' => 'Every month']);
    }

    protected static function dayOfWeekOptions(): array
    {
        return [
            '*' => 'Any day',
            '1' => 'Monday',
            '2' => 'Tuesday',
            '3' => 'Wednesday',
            '4' => 'Thursday',
            '5' => 'Friday',
            '6' => 'Saturday',
            '0' => 'Sunday',
        ];
    }

    protected static function numericOptions(int $start, int $end, array $prefix): array
    {
        $options = $prefix;

        for ($i = $start; $i <= $end; $i++) {
            $options[(string) $i] = (string) $i;
        }

        return $options;
    }
}
