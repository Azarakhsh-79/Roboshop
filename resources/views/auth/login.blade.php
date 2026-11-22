<x-layouts.main title="ورود یا ثبت‌نام | RoboShop">


    <div class="auth-wrapper">
        <div class="auth-card">

            <div class="auth-logo">
                RoboShop<i class="fa-solid fa-cube"></i>
            </div>

            <div class="auth-tabs">
                <button class="auth-tab active" onclick="switchTab('login')">ورود</button>
                <button class="auth-tab" onclick="switchTab('register')">ثبت‌نام</button>
            </div>

            <div id="generalError" style="display: none; margin-bottom: 15px;">
                <x-alert type="error" id="generalErrorText"></x-alert>
            </div>

            <div class="auth-forms-container">

                <div id="login-form" class="tab-content active">
                    <h2 class="auth-title">خوش آمدید!</h2>
                    <p class="auth-subtitle">شماره موبایل خود را برای ورود وارد کنید</p>

                    <form action="{{ route('login.send') }}" method="POST" class="auth-form ajax-form">
                        @csrf
                        <input type="hidden" name="type" value="login">

                        <div class="floating-group">
                            <input type="tel" id="login_phone" name="phone" placeholder=" " autocomplete="off" style="letter-spacing: 2px;" required>
                            <label for="login_phone">شماره موبایل</label>

                            <span class="text-danger error-msg" id="error_login_phone" style="display: none; font-size: 0.85rem; margin-top: 5px;"></span>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg submit-btn" style="margin-top: 10px;">
                            <span class="btn-text">ارسال کد تایید <i class="fa-solid fa-arrow-left" style="margin-right: 8px;"></i></span>
                            <i class="fa-solid fa-spinner fa-spin loading-icon" style="display: none;"></i>
                        </button>
                    </form>
                </div>

                <div id="register-form" class="tab-content">
                    <h2 class="auth-title">حساب جدید</h2>
                    <p class="auth-subtitle">برای ساخت فروشگاه، ثبت‌نام کنید</p>

                    <form action="{{ route('login.send') }}" method="POST" class="auth-form ajax-form">
                        @csrf
                        <input type="hidden" name="type" value="register">

                        <div class="floating-group">
                            <input type="text" id="name" name="name" placeholder=" " autocomplete="off">
                            <label for="name">نام و نام خانوادگی (اختیاری)</label>

                            <span class="text-danger error-msg" id="error_register_name" style="display: none; font-size: 0.85rem; margin-top: 5px;"></span>
                        </div>

                        <div class="floating-group">
                            <input type="tel" id="register_phone" name="phone" placeholder=" " autocomplete="off" required style="letter-spacing: 2px;">
                            <label for="register_phone">شماره موبایل</label>

                            <span class="text-danger error-msg" id="error_register_phone" style="display: none; font-size: 0.85rem; margin-top: 5px;"></span>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg submit-btn" style="margin-top: 10px;">
                            <span class="btn-text">عضویت و دریافت کد <i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i></span>
                            <i class="fa-solid fa-spinner fa-spin loading-icon" style="display: none;"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="auth-footer">
                <a href="{{ route('home') }}" class="auth-back">
                    بازگشت به صفحه اصلی <i class="fa-solid fa-home"></i>
                </a>
            </div>

        </div>
    </div>

    <script>
        // تغییر تب‌ها
        function switchTab(tabName) {
            document.querySelectorAll('.auth-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // پاک کردن خطاهای قبلی در زمان تغییر تب
            document.querySelectorAll('.error-msg').forEach(el => { el.style.display = 'none'; el.innerHTML = ''; });
            document.getElementById('generalError').style.display = 'none';

            if (tabName === 'login') {
                document.querySelectorAll('.auth-tab')[0].classList.add('active');
                document.getElementById('login-form').classList.add('active');
            } else {
                document.querySelectorAll('.auth-tab')[1].classList.add('active');
                document.getElementById('register-form').classList.add('active');
            }
        }

        // پردازش ارسال فرم با AJAX
        document.querySelectorAll('.ajax-form').forEach(form => {
            form.addEventListener('submit', async function (e) {
                e.preventDefault(); // جلوگیری از رفرش صفحه

                let btn = this.querySelector('.submit-btn');
                let btnText = btn.querySelector('.btn-text');
                let loadingIcon = btn.querySelector('.loading-icon');
                let formType = this.querySelector('input[name="type"]').value; // 'login' یا 'register'

                // پاکسازی خطاهای قبلی
                document.querySelectorAll('.error-msg').forEach(el => { el.style.display = 'none'; });
                document.getElementById('generalError').style.display = 'none';

                // تغییر حالت دکمه به درحال پردازش
                btn.disabled = true;
                btnText.style.display = 'none';
                loadingIcon.style.display = 'inline-block';

                try {
                    let response = await fetch(this.action, {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json', // این هدر به لاراول می‌فهماند که جواب را JSON بدهد
                        }
                    });

                    let data = await response.json();

                    if (response.ok) {
                        // عملیات موفق: رفتن به صفحه تایید کد
                        window.location.href = data.redirect;
                    }
                    else if (response.status === 422) {
                        // خطای اعتبارسنجی فرم (Validation)
                        for (let field in data.errors) {
                            // ساختن آیدی تگ ارور مربوطه (مثلاً error_login_phone یا error_register_name)
                            let errorElementId = `error_${formType}_${field}`;
                            let errorElement = document.getElementById(errorElementId);

                            if (errorElement) {
                                errorElement.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${data.errors[field][0]}`;
                                errorElement.style.display = 'block';
                            }
                        }
                    }
                    else {
                        // خطاهای سرور 500
                        document.getElementById('generalErrorText').innerText = data.message || 'خطایی در ارتباط با سرور رخ داد.';
                        document.getElementById('generalError').style.display = 'block';
                    }

                } catch (error) {
                    document.getElementById('generalErrorText').innerText = 'مشکل در برقراری ارتباط با شبکه.';
                    document.getElementById('generalError').style.display = 'block';
                } finally {
                    // برگرداندن دکمه به حالت عادی
                    btn.disabled = false;
                    btnText.style.display = 'inline-block';
                    loadingIcon.style.display = 'none';
                }
            });
        });
    </script>
</x-layouts.main>
