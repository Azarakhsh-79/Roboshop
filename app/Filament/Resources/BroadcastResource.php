<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BroadcastResource\Pages;
use App\Models\Broadcast;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;

class BroadcastResource extends Resource
{
    protected static ?string $model = Broadcast::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'پیام همگانی';
    protected static ?string $pluralModelLabel = 'پیام‌های همگانی';
    protected static ?string $modelLabel = 'پیام همگانی';

    // برای اطمینان از اینکه هر فروشگاه فقط پیام‌های خودش را می‌بیند
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $botId = request()->route('bot_id') ?? session('bot_id');
        // بسته به نحوه مدیریت شناسه ربات در فیلامنت شما، خط بالا ممکن است نیاز به تطبیق داشته باشد.

        $query = parent::getEloquentQuery();
        if ($botId) {
            $query->where('bot_id', $botId);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('تنظیمات ارسال پیام')->schema([
                Textarea::make('message')
                    ->label('متن پیام همگانی')
                    ->required()
                    ->rows(5)
                    ->hint('پشتیبانی از تگ‌های HTML تلگرام (مثل <b>متن بولد</b>)'),

                FileUpload::make('photo_file_id')
                    ->label('تصویر ضمیمه (اختیاری)')
                    ->image()
                    ->directory('broadcasts'),

                DateTimePicker::make('scheduled_at')
                    ->label('زمان ارسال')
                    ->required()
                    ->minDate(now()) // جلوگیری از انتخاب زمان گذشته
                    ->default(now()->addMinutes(5)),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('scheduled_at')
                ->label('زمان ارسال')
                ->dateTime('Y/m/d H:i')
                ->sortable(),

            Tables\Columns\BadgeColumn::make('status')
                ->label('وضعیت')
                ->colors([
                    'warning' => 'pending',
                    'primary' => 'processing',
                    'success' => 'completed',
                    'danger' => 'failed',
                ])
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'در صف انتظار',
                    'processing' => 'در حال ارسال',
                    'completed' => 'ارسال شده',
                    'failed' => 'خطا',
                    default => $state,
                }),

            Tables\Columns\TextColumn::make('sent_count')
                ->label('ارسال موفق')
                ->sortable(),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBroadcasts::route('/'),
            'create' => Pages\CreateBroadcast::route('/create'),
            'edit' => Pages\EditBroadcast::route('/{record}/edit'),
        ];
    }
}
