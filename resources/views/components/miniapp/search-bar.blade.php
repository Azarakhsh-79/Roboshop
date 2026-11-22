<div class="px-4 pt-3 pb-2" x-data="{ searchQuery: '', isFocused: false }">
    <!-- کادر جستجو -->
    <div class="relative flex items-center w-full h-12 rounded-2xl bg-[#1E1E24] border transition-all duration-300 shadow-inner"
         :class="isFocused ? 'border-primary/50 shadow-[0_0_10px_rgba(var(--color-primary-rgb),0.2)]' : 'border-white/5'">
        
        <!-- آیکون ذره‌بین (سمت راست) -->
        <div class="absolute right-3 text-gray-400 transition-colors" :class="isFocused ? 'text-primary' : 'text-gray-400'">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <!-- اینپوت جستجو -->
        <input 
            type="text" 
            x-model="searchQuery"
            @focus="isFocused = true"
            @blur="isFocused = false"
            placeholder="جستجوی محصولات..." 
            class="w-full h-full py-0 pr-10 pl-10 bg-transparent text-gray-100 text-[13px] placeholder-gray-500 border-none focus:outline-none focus:ring-0"
        >

        <!-- دکمه پاک کردن (X) - فقط وقتی متنی تایپ شده باشد ظاهر می‌شود -->
        <button 
            x-show="searchQuery.length > 0" 
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-75"
            @click="searchQuery = ''; $refs.searchInput.focus()" 
            class="absolute left-3 w-5 h-5 flex items-center justify-center bg-gray-700 hover:bg-gray-600 rounded-full text-white transition-colors"
        >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>