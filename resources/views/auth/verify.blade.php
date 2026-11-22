<x-layouts.main title="تایید شماره موبایل | RoboShop">

    <style>
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
            direction: ltr;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            font-size: 1.8rem;
            text-align: center;
            border: 2px solid var(--border-color, #ccc);
            border-radius: 8px;
            background: var(--bg-card, #fff);
            color: var(--text-main, #333);
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .otp-input:focus {
            border-color: var(--c-green, #28a745);
            outline: none;
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.2);
        }
        .otp-input.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 8px rgba(220, 53, 69, 0.2);
        }
        .otp-input::-webkit-outer-spin-button,
        .otp-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .otp-input[type=number] {
            -moz-appearance: textfield;
        }
        /* انیمیشن لرزش برای خطای کد */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }
        .shake-animation {
            animation: shake 0.4s ease-in-out;
        }
    </style>

    <div class="auth-wrapper">
        <div class="auth-card">

            <div class="auth-logo">
                <i class="fa-solid fa-shield-check" style="color: var(--c-green);"></i>
            </div>

            <h1 class="auth-title">تایید شماره موبایل</h1>
            <p class="auth-subtitle">
                کد ۵ رقمی پیامک شده به شماره
                <span style="font-weight:bold; color:var(--text-main);" dir="ltr">{{ session('auth_phone') }}</span>
                را وارد کنید.<br>
                <a href="{{ route('login') }}" class="edit-phone">تغییر شماره</a>
            </p>

            <div id="generalError" style="display: none; margin-bottom: 15px; background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; text-align: center; font-size: 0.9rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <span id="generalErrorText"></span>
            </div>

            <form action="{{ route('verify.check') }}" method="POST" class="auth-form" id="otp-form">
                @csrf

                <div class="form-group" style="text-align: center;">
                    <label>کد تایید</label>

                    <div class="otp-container" id="otp-inputs-wrapper">
                        <input type="number" class="otp-input" min="0" max="9" autocomplete="one-time-code" autofocus>
                        <input type="number" class="otp-input" min="0" max="9">
                        <input type="number" class="otp-input" min="0" max="9">
                        <input type="number" class="otp-input" min="0" max="9">
                        <input type="number" class="otp-input" min="0" max="9">
                    </div>

                    <input type="hidden" name="code" id="final_code" required>

                    <span class="text-danger error-msg" id="error_verify_code" style="display: none; margin-top: 10px; font-size: 0.85rem; color: #dc3545;"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg submit-btn" style="margin-top: 10px;">
                    <span class="btn-text">تایید و ورود <i class="fa-solid fa-check" style="margin-right: 8px;"></i></span>
                    <i class="fa-solid fa-spinner fa-spin loading-icon" style="display: none;"></i>
                </button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('home') }}" class="auth-back">
                    بازگشت به صفحه اصلی
                </a>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const inputs = document.querySelectorAll('.otp-input');
            const hiddenInput = document.getElementById('final_code');
            const form = document.getElementById('otp-form');
            const errorMsg = document.getElementById('error_verify_code');
            const otpWrapper = document.getElementById('otp-inputs-wrapper');

            // پاک کردن استایل‌های خطا
            function clearErrors() {
                errorMsg.style.display = 'none';
                errorMsg.innerHTML = '';
                document.getElementById('generalError').style.display = 'none';
                inputs.forEach(input => input.classList.remove('is-invalid'));
                otpWrapper.classList.remove('shake-animation');
            }

            function updateHiddenInput() {
                let otp = '';
                inputs.forEach(input => {
                    otp += input.value;
                });
                hiddenInput.value = otp;
            }

            inputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    clearErrors(); // با تایپ کردن کاربر، خطاها پاک شوند

                    if (e.target.value.length > 1) {
                        e.target.value = e.target.value.slice(0, 1);
                    }

                    if (e.target.value !== '') {
                        if (index < inputs.length - 1) {
                            inputs[index + 1].focus();
                        } else if (index === inputs.length - 1) {
                            // اگر به باکس آخر رسید و پر شد، فرم را خودکار ارسال کن (اختیاری برای تجربه کاربری بهتر)
                            updateHiddenInput();
                            if(hiddenInput.value.length === 5) {
                                form.dispatchEvent(new Event('submit'));
                            }
                        }
                    }
                    updateHiddenInput();
                });

                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && e.target.value === '') {
                        if (index > 0) {
                            inputs[index - 1].focus();
                        }
                    }
                });

                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    clearErrors();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text');
                    const numbers = pastedData.replace(/\D/g, '').slice(0, 5);

                    if (numbers.length > 0) {
                        numbers.split('').forEach((num, i) => {
                            if (i < inputs.length) {
                                inputs[i].value = num;
                            }
                        });

                        const focusIndex = Math.min(numbers.length, inputs.length) - 1;
                        inputs[focusIndex].focus();

                        updateHiddenInput();
                        // ارسال خودکار بعد از Paste کامل
                        if(hiddenInput.value.length === 5) {
                            form.dispatchEvent(new Event('submit'));
                        }
                    }
                });
            });

            // پردازش ارسال فرم با AJAX
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                updateHiddenInput();
                clearErrors();

                if (hiddenInput.value.length !== 5) {
                    errorMsg.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> لطفاً کد ۵ رقمی را به صورت کامل وارد کنید.`;
                    errorMsg.style.display = 'block';
                    inputs.forEach(input => input.classList.add('is-invalid'));
                    otpWrapper.classList.add('shake-animation');
                    return;
                }

                let btn = this.querySelector('.submit-btn');
                let btnText = btn.querySelector('.btn-text');
                let loadingIcon = btn.querySelector('.loading-icon');

                // نمایش لودینگ
                btn.disabled = true;
                btnText.style.display = 'none';
                loadingIcon.style.display = 'inline-block';

                try {
                    let response = await fetch(this.action, {
                        method: 'POST',
                        body: new FormData(this),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    let data = await response.json();

                    if (response.ok) {
                        // عملیات موفق: انتقال به پنل یا صفحه اصلی
                        window.location.href = data.redirect;
                    }
                    else if (response.status === 422) {
                        // خطای Validation (کد اشتباه است)
                        errorMsg.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${data.errors.code[0]}`;
                        errorMsg.style.display = 'block';
                        inputs.forEach(input => input.classList.add('is-invalid'));

                        // اضافه کردن لرزش، پاک کردن کد و فوکوس روی خانه اول
                        otpWrapper.classList.add('shake-animation');
                        inputs.forEach(input => input.value = '');
                        inputs[0].focus();
                        updateHiddenInput();
                    }
                    else if (response.status === 400) {
                        // خطای منطقی (مثل سشن منقضی شده)
                        // در کنترلر تنظیم کردیم که ریدایرکت کند، پس می‌فرستیمش به صفحه لاگین
                        window.location.href = data.redirect;
                    }
                    else {
                        document.getElementById('generalErrorText').innerText = data.message || 'خطایی در ارتباط با سرور رخ داد.';
                        document.getElementById('generalError').style.display = 'block';
                    }

                } catch (error) {
                    document.getElementById('generalErrorText').innerText = 'مشکل در برقراری ارتباط با شبکه.';
                    document.getElementById('generalError').style.display = 'block';
                } finally {
                    btn.disabled = false;
                    btnText.style.display = 'inline-block';
                    loadingIcon.style.display = 'none';
                    // حذف کلاس انیمیشن تا در دفعات بعدی دوباره کار کند
                    setTimeout(() => otpWrapper.classList.remove('shake-animation'), 400);
                }
            });
        });
    </script>

</x-layouts.main>
