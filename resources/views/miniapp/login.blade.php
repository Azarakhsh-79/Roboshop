<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="false">

    <div class="min-h-screen bg-[#121214] flex flex-col justify-center items-center px-6 relative overflow-hidden"
         x-data="loginFlow('{{ $bot->bot_username }}')">
        
        <!-- افکت نوری پس‌زمینه -->
        <div class="absolute top-[-10%] right-[-10%] w-64 h-64 bg-primary/20 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute bottom-[-10%] left-[-10%] w-64 h-64 bg-primary/10 rounded-full blur-[100px] pointer-events-none"></div>

        <!-- آواتار و نام فروشگاه -->
        <div class="z-10 flex flex-col items-center mb-10">
            <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-primary to-primary-light p-0.5 mb-4 shadow-xl shadow-primary/20">
                <div class="w-full h-full rounded-full bg-[#1E1E24] flex items-center justify-center font-black text-primary text-2xl">
                    {{ mb_substr($bot->bot_name, 0, 1) }}
                </div>
            </div>
            <h1 class="text-white font-extrabold text-[20px]">{{ $bot->bot_name }}</h1>
            <p class="text-gray-400 text-[12px] mt-1">جهت ورود یا ثبت‌نام، شماره خود را وارد کنید</p>
        </div>

        <!-- فرم دریافت شماره (مرحله اول) -->
        <div x-show="step === 1" x-transition class="w-full max-w-sm bg-[#1E1E24]/60 backdrop-blur-xl border border-white/5 p-6 rounded-3xl z-10 shadow-lg">
            <label class="block text-gray-300 text-[12px] font-bold mb-3">شماره موبایل:</label>
            <div class="relative">
                <input type="tel" x-model="phone" placeholder="09123456789" class="w-full bg-[#121214] border border-white/10 text-white rounded-xl h-14 px-4 text-left dir-ltr focus:outline-none focus:border-primary transition-colors tracking-widest text-lg">
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
            </div>
            
            <p class="text-red-400 text-[11px] mt-2 h-4" x-text="errorMsg"></p>

            <button @click="sendOtp" :disabled="loading" class="w-full bg-primary hover:bg-primary-light text-white font-bold rounded-xl h-14 mt-4 transition-all active:scale-95 flex items-center justify-center shadow-lg shadow-primary/30 disabled:opacity-50">
                <span x-show="!loading">ارسال کد تایید</span>
                <svg x-show="loading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>
        </div>

        <!-- فرم دریافت کد (مرحله دوم) -->
        <div x-show="step === 2" x-transition style="display: none;" class="w-full max-w-sm bg-[#1E1E24]/60 backdrop-blur-xl border border-white/5 p-6 rounded-3xl z-10 shadow-lg text-center">
            <h3 class="text-white font-bold mb-1">کد تایید را وارد کنید</h3>
            <p class="text-gray-400 text-[11px] mb-5">کد ۵ رقمی ارسال شده به <span class="font-bold text-white dir-ltr inline-block" x-text="phone"></span> را وارد کنید.</p>
            
            <input type="number" x-model="code" placeholder="- - - - -" class="w-full bg-[#121214] border border-white/10 text-white rounded-xl h-14 px-4 text-center dir-ltr focus:outline-none focus:border-primary transition-colors tracking-[1em] text-xl font-bold">
            
            <p class="text-red-400 text-[11px] mt-2 h-4" x-text="errorMsg"></p>

            <button @click="verifyOtp" :disabled="loading" class="w-full bg-primary hover:bg-primary-light text-white font-bold rounded-xl h-14 mt-4 transition-all active:scale-95 flex items-center justify-center shadow-lg shadow-primary/30 disabled:opacity-50">
                <span x-show="!loading">تایید و ورود</span>
                <svg x-show="loading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </button>

            <button @click="step = 1; code = ''; errorMsg = ''" class="text-gray-400 text-[11px] mt-4 hover:text-white transition-colors">
                ویرایش شماره موبایل
            </button>
        </div>

    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginFlow', (botUsername) => ({
                step: 1,
                phone: '',
                code: '',
                loading: false,
                errorMsg: '',

                async sendOtp() {
                    this.errorMsg = '';
                    if(!this.phone.match(/^09[0-9]{9}$/)) {
                        this.errorMsg = 'شماره موبایل نامعتبر است'; return;
                    }
                    this.loading = true;
                    try {
                        let res = await fetch(`/app/${botUsername}/send-otp`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ phone: this.phone })
                        });
                        let data = await res.json();
                        if(data.success) { this.step = 2; } else { this.errorMsg = data.message || 'خطا در ارسال کد'; }
                    } catch (e) { this.errorMsg = 'خطای ارتباط با سرور'; }
                    this.loading = false;
                },

                async verifyOtp() {
                    this.errorMsg = '';
                    if(!this.code) { this.errorMsg = 'کد را وارد کنید'; return; }
                    this.loading = true;
                    try {
                        let res = await fetch(`/app/${botUsername}/verify-otp`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ phone: this.phone, code: this.code })
                        });
                        let data = await res.json();
                        if(data.success) { window.location.href = `/app/${botUsername}`; } 
                        else { this.errorMsg = data.message; }
                    } catch (e) { this.errorMsg = 'خطای ارتباط با سرور'; }
                    this.loading = false;
                }
            }))
        })
    </script>
</x-miniapp.layouts.master>