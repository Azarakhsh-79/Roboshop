@php
    $botId = session('current_shop_id');
    $bot = $botId ? \App\Models\ManagedBot::find($botId) : null;
@endphp

@if($bot)
    <div class="flex items-center gap-2 px-3 py-1.5 bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 rounded-lg ring-1 ring-primary-600/10 dark:ring-primary-500/20 shadow-sm transition-all duration-200 hover:bg-primary-100 dark:hover:bg-primary-500/20">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Z" />
        </svg>
        <span class="text-sm font-medium opacity-80 hidden sm:block">فروشگاه فعال:</span>
        <span dir="ltr" class="text-sm font-bold tracking-wide">
            {{ '@' . $bot->bot_username }}
        </span>
    </div>
@endif
