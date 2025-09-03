document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.login-container');

    function getApiBase() {
        return '';
    }

    function toggleForms(show, hide1, hide2) {
        const showForm = document.getElementById(show);
        const hideForm1 = document.getElementById(hide1);
        const hideForm2 = document.getElementById(hide2);

        if (showForm) {
            showForm.classList.remove('d-none');
        } else {
            console.error(`Форма ${show} не найдена`);
        }

        if (hideForm1) {
            hideForm1.classList.add('d-none');
        }

        if (hideForm2) {
            hideForm2.classList.add('d-none');
        }

        clearAllMessages();

        const container = document.querySelector('.login-container');
        if (container) {
            if (show === 'registerForm') {
                container.classList.add('wide');
            } else {
                container.classList.remove('wide');
            }
        }
    }

    window.toggleForms = toggleForms;

    const showRegisterBtn = document.getElementById('showRegister');
    if (showRegisterBtn) {
        showRegisterBtn.onclick = function (e) {
            e.preventDefault();
            toggleForms('registerForm', 'loginForm', 'resetForm');
        };
    }

    const showLogin1Btn = document.getElementById('showLogin1');
    if (showLogin1Btn) {
        showLogin1Btn.onclick = function (e) {
            e.preventDefault();
            toggleForms('loginForm', 'registerForm', 'resetForm');
        };
    }

    const showLogin2Btn = document.getElementById('showLogin2');
    if (showLogin2Btn) {
        showLogin2Btn.onclick = function (e) {
            e.preventDefault();
            toggleForms('loginForm', 'resetForm', 'registerForm');
        };
    }

    const showResetBtn = document.getElementById('showReset');
    if (showResetBtn) {
        showResetBtn.onclick = function (e) {
            e.preventDefault();
            toggleForms('resetForm', 'loginForm', 'registerForm');
        };
    }

    const twoFactorToggle = document.getElementById('enable_two_factor');
    if (twoFactorToggle) {
        twoFactorToggle.addEventListener('change', function () {
            const infoDiv = document.getElementById('twoFactorInfo');
            if (infoDiv) {
                if (this.checked) {
                    infoDiv.classList.remove('d-none');
                    infoDiv.style.animation = 'fadeInUp 0.3s ease-out';
                } else {
                    infoDiv.classList.add('d-none');
                }
            }
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.onsubmit = async function (e) {
            e.preventDefault();

            const email = document.getElementById('email')?.value.trim();
            const password = document.getElementById('password')?.value.trim();

            if (!email || !password) {
                const msg = document.getElementById('loginFormMsg');
                if (msg) {
                    msg.innerHTML = '<div class="alert alert-danger">Заполните все поля</div>';
                    msg.classList.remove('d-none');
                }
                return;
            }

            await handleFormSubmit('loginForm', '/users/login', {
                email: email,
                password: password
            });
        };
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.onsubmit = async function (e) {
            e.preventDefault();

            const formData = {
                first_name: document.getElementById('first_name')?.value.trim(),
                last_name: document.getElementById('last_name')?.value.trim(),
                middle_name: document.getElementById('middle_name')?.value.trim(),
                email: document.getElementById('register_email')?.value.trim(),
                password: document.getElementById('register_password')?.value.trim(),
                confirm_password: document.getElementById('confirm_password')?.value.trim(),
                age: document.getElementById('age')?.value,
                gender: document.getElementById('gender')?.value,
                enable_two_factor: document.getElementById('enable_two_factor')?.checked || false
            };

            if (!formData.first_name || !formData.last_name || !formData.email || !formData.password) {
                const msg = document.getElementById('registerFormMsg');
                if (msg) {
                    msg.innerHTML = '<div class="alert alert-danger">Заполните все обязательные поля (отмечены *)</div>';
                    msg.classList.remove('d-none');
                }
                return;
            }

            if (formData.password !== formData.confirm_password) {
                const msg = document.getElementById('registerFormMsg');
                if (msg) {
                    msg.innerHTML = '<div class="alert alert-danger">Пароли не совпадают</div>';
                    msg.classList.remove('d-none');
                }
                return;
            }

            delete formData.confirm_password;

            await handleFormSubmit('registerForm', '/users/register', formData);
        };
    }

    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
        resetForm.onsubmit = async function (e) {
            e.preventDefault();

            const email = document.getElementById('reset_email')?.value.trim();

            if (!email) {
                const msg = document.getElementById('resetFormMsg');
                if (msg) {
                    msg.innerHTML = '<div class="alert alert-danger">Введите email</div>';
                    msg.classList.remove('d-none');
                }
                return;
            }

            await handleFormSubmit('resetForm', '/users/reset_password', {
                email: email
            });
        };
    }

    function autoHideError(element, isInline = false) {
        if (isInline) {
            element.classList.add('auto-hide-inline');
            setTimeout(() => {
                if (element.parentNode) {
                    element.style.display = 'none';
                    element.classList.remove('auto-hide-inline');
                }
            }, 3500);
        } else {
            element.classList.add('auto-hide');
            setTimeout(() => {
                if (element.parentNode) {
                    element.style.display = 'none';
                    element.classList.remove('auto-hide');
                }
            }, 3500);
        }
    }

    function showInlineError(input, message) {

        const originalPlaceholder = input.getAttribute('placeholder') || '';

        if (!input.hasAttribute('data-original-placeholder')) {
            input.setAttribute('data-original-placeholder', originalPlaceholder);
        }

        input.setAttribute('placeholder', message);
        input.classList.add('has-inline-error');

        setTimeout(() => {
            if (input.classList.contains('has-inline-error')) {
                input.setAttribute('placeholder', input.getAttribute('data-original-placeholder') || '');
                input.classList.remove('has-inline-error');
            }
        }, 3000);

        const focusHandler = () => {
            input.setAttribute('placeholder', input.getAttribute('data-original-placeholder') || '');
            input.classList.remove('has-inline-error');
            input.removeEventListener('focus', focusHandler);
        };
        input.addEventListener('focus', focusHandler);
    }

    function shouldUseInlineError(input, message) {

        const tempSpan = document.createElement('span');
        tempSpan.style.visibility = 'hidden';
        tempSpan.style.position = 'absolute';
        tempSpan.style.fontSize = window.getComputedStyle(input).fontSize;
        tempSpan.style.fontFamily = window.getComputedStyle(input).fontFamily;
        tempSpan.textContent = message;
        document.body.appendChild(tempSpan);

        const messageWidth = tempSpan.offsetWidth;
        const inputWidth = input.offsetWidth - 60;

        document.body.removeChild(tempSpan);

        return messageWidth <= inputWidth;
    }

    const firstNameInput = document.getElementById('reg_firstname');
    if (firstNameInput) {
        firstNameInput.addEventListener('blur', validateRequired);
        firstNameInput.addEventListener('input', clearInvalidOnInput);
    }

    const lastNameInput = document.getElementById('reg_lastname');
    if (lastNameInput) {
        lastNameInput.addEventListener('blur', validateRequired);
        lastNameInput.addEventListener('input', clearInvalidOnInput);
    }

    const emailInput = document.getElementById('reg_email');
    if (emailInput) {
        emailInput.addEventListener('blur', validateEmail);
        emailInput.addEventListener('input', clearInvalidOnInput);
    }

    const ageInput = document.getElementById('reg_age');
    if (ageInput) {
        ageInput.addEventListener('blur', validateAge);
        ageInput.addEventListener('input', clearInvalidOnInput);
    }

    const passwordInput = document.getElementById('reg_password');
    if (passwordInput) {
        passwordInput.addEventListener('input', validatePassword);
        passwordInput.addEventListener('blur', validatePassword);
    }

    const password2Input = document.getElementById('reg_password2');
    if (password2Input) {
        password2Input.addEventListener('input', validatePasswordMatch);
        password2Input.addEventListener('blur', validatePasswordMatch);
    }

    function validateRequired() {
        const errorElement = this.parentNode.querySelector('.invalid-feedback');
        const errorMessage = 'Поле обязательно для заполнения';

        if (this.value.trim() === '') {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, errorMessage)) {
                showInlineError(this, errorMessage);
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else {
            this.classList.remove('is-invalid', 'has-inline-error');
            this.classList.add('is-valid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        }
    }

    function validateEmail() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const errorElement = this.parentNode.querySelector('.invalid-feedback');
        let errorMessage = '';

        if (this.value.trim() === '') {
            errorMessage = 'Email обязателен';
        } else if (!emailRegex.test(this.value)) {
            errorMessage = 'Введите корректный email';
        }

        if (errorMessage) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, errorMessage)) {
                showInlineError(this, errorMessage);
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else {
            this.classList.remove('is-invalid', 'has-inline-error');
            this.classList.add('is-valid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        }
    }

    function validateAge() {
        const age = parseInt(this.value);
        const errorElement = this.parentNode.querySelector('.invalid-feedback');
        const errorMessage = 'Возраст: 1-120 лет';

        if (this.value && (isNaN(age) || age < 1 || age > 120)) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, errorMessage)) {
                showInlineError(this, errorMessage);
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else if (this.value) {
            this.classList.remove('is-invalid', 'has-inline-error');
            this.classList.add('is-valid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        } else {
            this.classList.remove('is-invalid', 'is-valid', 'has-inline-error');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        }
    }

    function validatePassword() {
        const password = this.value;
        const strengthDiv = document.getElementById('passwordStrength');
        const errorElement = this.parentNode.querySelector('.invalid-feedback');
        const errorMessage = 'Минимум 6 символов';

        if (password.length === 0) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, 'Пароль обязателен')) {
                showInlineError(this, 'Пароль обязателен');
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = 'Пароль обязателен';
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            strengthDiv.style.display = 'none';
            return false;
        } else if (password.length < 6) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, errorMessage)) {
                showInlineError(this, errorMessage);
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            strengthDiv.textContent = 'Слишком короткий пароль';
            strengthDiv.className = 'password-strength weak';
            strengthDiv.style.display = 'block';
            autoHideError(strengthDiv, true);
            return false;
        } else {
            this.classList.remove('is-invalid', 'has-inline-error');
            this.classList.add('is-valid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            if (strength <= 2) {
                strengthDiv.textContent = 'Слабый пароль';
                strengthDiv.className = 'password-strength weak';
            } else if (strength <= 3) {
                strengthDiv.textContent = 'Средний пароль';
                strengthDiv.className = 'password-strength medium';
            } else {
                strengthDiv.textContent = 'Сильный пароль';
                strengthDiv.className = 'password-strength strong';
            }
            strengthDiv.style.display = 'block';
            autoHideError(strengthDiv, true);

            const password2Input = document.getElementById('reg_password2');
            if (password2Input && password2Input.value) {
                validatePasswordMatch.call(password2Input);
            }

            return true;
        }
    }

    function validatePasswordMatch() {
        const password = document.getElementById('reg_password').value;
        const password2 = this.value;
        const errorElement = this.parentNode.querySelector('.invalid-feedback');
        const errorMessage = 'Пароли не совпадают';

        if (password2.length === 0) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, 'Повторите пароль')) {
                showInlineError(this, 'Повторите пароль');
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = 'Повторите пароль';
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else if (password2.length < 6) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, 'Минимум 6 символов')) {
                showInlineError(this, 'Минимум 6 символов');
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = 'Минимум 6 символов';
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else if (password !== password2) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');

            if (shouldUseInlineError(this, errorMessage)) {
                showInlineError(this, errorMessage);
                if (errorElement) errorElement.style.display = 'none';
            } else {
                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                    autoHideError(errorElement);
                }
            }
            return false;
        } else {
            this.classList.remove('is-invalid', 'has-inline-error');
            this.classList.add('is-valid');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
            return true;
        }
    }

    function clearInvalidOnInput() {
        if (this.classList.contains('is-invalid') && this.value.trim() !== '') {
            this.classList.remove('is-invalid');
            const errorElement = this.parentNode.querySelector('.invalid-feedback');
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }
        if (this.classList.contains('has-inline-error')) {
            this.classList.remove('has-inline-error');
            this.setAttribute('placeholder', this.getAttribute('data-original-placeholder') || '');
        }
    }

    async function handleFormSubmit(formId, endpoint, body) {
        const apiBase = getApiBase();
        const url = `${apiBase}${endpoint}`;
        let msg;
        if (formId === 'resetForm') {
            msg = document.getElementById('resetFormMsg');
        } else if (formId === 'loginForm') {
            msg = document.getElementById('loginFormMsg');
        } else if (formId === 'registerForm') {
            msg = document.getElementById('registerFormMsg');
        }

        if (!msg) {
            console.error(`Элемент сообщения не найден для формы: ${formId}`);
            alert(`Ошибка: элемент для отображения сообщений не найден для формы ${formId}`);
            return;
        }

        msg.classList.add('d-none');
        msg.innerHTML = '';

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            const responseText = await res.text();

            if (!res.ok) {
                let errorData;
                try {
                    errorData = JSON.parse(responseText);
                    msg.innerHTML = `<div class="alert alert-danger">${errorData.error || `Ошибка сервера: ${res.status}`}</div>`;
                    msg.classList.remove("d-none");
                    return;
                } catch (parseError) {
                    msg.innerHTML = `<div class="alert alert-danger">Ошибка сервера: ${res.status}</div>`;
                    msg.classList.remove("d-none");
                    return;
                }
            }

            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                msg.innerHTML = '<div class="alert alert-danger">Ошибка: Сервер вернул некорректный формат данных.</div>';
                msg.classList.remove("d-none");
                return;
            }

            let data;
            try {
                data = JSON.parse(responseText);
                console.log('Успешный ответ:', data);
            } catch (parseError) {
                msg.innerHTML = '<div class="alert alert-danger">Ошибка: Некорректный формат ответа сервера.</div>';
                msg.classList.remove("d-none");
                return;
            }

            if (formId === 'resetForm') {
                if (data.success) {
                    msg.innerHTML = `
                        <div class="alert alert-success">
                            <div class="mb-3">
                                <strong>✅ ${data.message}</strong>
                            </div>
                            <div class="mb-3">
                                <p><small class="text-muted">
                                    Проверьте папку "Входящие" и "Спам".<br>
                                    Ссылка действительна 1 час.
                                </small></p>
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-success w-100" onclick="toggleForms('loginForm', 'resetForm', 'registerForm')">
                                    Перейти к входу
                                </button>
                            </div>
                        </div>
                    `;
                    msg.classList.remove("d-none");
                    clearResetFields();
                    return;
                } else {
                    msg.innerHTML = `<div class="alert alert-danger">${data.error || "Ошибка при сбросе пароля"}</div>`;
                    msg.classList.remove("d-none");
                    return;
                }
            }

            if (data.success) {

                if (formId === 'loginForm') {
                    if (data.requires_2fa_verification) {
                        show2FAForm(data.two_factor_method, data.user_email);
                        return;
                    }

                    if (data.requires_2fa_setup) {
                        if (data.user && data.user.is_admin == 1) {
                            msg.innerHTML = `
                                <div class="alert alert-success">
                                    <div class="mb-3">
                                        <strong>✅ Вход выполнен!</strong>
                                    </div>
                                    <div class="mb-3">
                                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                        Перенаправление в админ-панель...
                                    </div>
                                </div>
                            `;
                            msg.classList.remove("d-none");
                            setTimeout(() => window.location.href = 'Admins.html', 1500);
                            return;
                        }

                        msg.innerHTML = `
                            <div class="alert alert-info">
                                <div class="mb-3">
                                    <strong>🔒 Требуется настройка двухфакторной аутентификации</strong>
                                </div>
                                <div class="mb-3">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Перенаправление на настройку...
                                </div>
                            </div>
                        `;
                        msg.classList.remove("d-none");
                        setTimeout(() => window.location.href = data.redirect, 1500);
                        return;
                    }

                    if (data.user && data.user.is_admin == 1) {
                        msg.innerHTML = `
                            <div class="alert alert-success">
                                <div class="mb-3">
                                    <strong>✅ Вход выполнен!</strong>
                                </div>
                                <div class="mb-3">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Перенаправление в админ-панель...
                                </div>
                            </div>
                        `;
                        msg.classList.remove("d-none");
                        setTimeout(() => window.location.href = 'Admins.html', 1500);
                        return;
                    }

                    msg.innerHTML = `
                        <div class="alert alert-success">
                            <div class="mb-3">
                                <strong>✅ Вход выполнен!</strong>
                            </div>
                            <div class="mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Перенаправление...
                            </div>
                        </div>
                    `;
                    msg.classList.remove("d-none");
                    setTimeout(() => window.location.href = 'upload.html', 1500);
                    return;
                }

                if (formId === 'registerForm') {
                    msg.innerHTML = `
                        <div class="alert alert-success">
                            <div class="mb-3">
                                <strong>✅ ${data.message || 'Регистрация успешна!'}</strong>
                            </div>
                            <div class="mb-3">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Переход к форме входа через <span id="countdown">3</span> сек...
                            </div>
                        </div>
                    `;
                    msg.classList.remove("d-none");
                    clearRegisterFields();

                    let countdown = 3;
                    const countdownElement = document.getElementById('countdown');
                    const countdownInterval = setInterval(() => {
                        countdown--;
                        if (countdownElement) {
                            countdownElement.textContent = countdown;
                        }
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            toggleForms('loginForm', 'registerForm', 'resetForm');
                        }
                    }, 1000);

                    return;
                }

            } else {

                msg.innerHTML = `<div class="alert alert-danger">${data.error || "Неизвестная ошибка!"}</div>`;
                msg.classList.remove("d-none");
                setTimeout(() => msg.classList.add("d-none"), 5000);
            }

        } catch (error) {
            console.error("Ошибка запроса:", error);
            msg.innerHTML = `<div class="alert alert-danger">Произошла ошибка: ${error.message}</div>`;
            msg.classList.remove("d-none");
            setTimeout(() => msg.classList.add("d-none"), 5000);
        }
    }

    function clearRegisterFields() {
        const registerFields = [
            'first_name',
            'last_name',
            'register_email',
            'register_password',
            'confirm_password',
            'middle_name',
            'age',
            'gender'
        ];

        registerFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });

        const twoFactorToggle = document.getElementById('enable_two_factor');
        if (twoFactorToggle) {
            twoFactorToggle.checked = false;
            const infoDiv = document.getElementById('twoFactorInfo');
            if (infoDiv) {
                infoDiv.classList.add('d-none');
            }
        }
    }

    function clearResetFields() {
        const resetEmailField = document.getElementById('reset_email');
        if (resetEmailField) {
            resetEmailField.value = '';
        }
    }

    function clearAllMessages() {
        const messageElements = [
            'loginFormMsg',
            'registerFormMsg',
            'resetFormMsg'
        ];

        messageElements.forEach(msgId => {
            const msgElement = document.getElementById(msgId);
            if (msgElement) {
                msgElement.classList.add('d-none');
                msgElement.innerHTML = '';
            }
        });
    }

    // ===== ФУНКЦИИ ДВУХФАКТОРНОЙ АУТЕНТИФИКАЦИИ =====

    let currentTwoFactorMethod = 'email';
    let currentUserEmail = '';

    function show2FAForm(method, userEmail) {
        currentTwoFactorMethod = method;
        currentUserEmail = userEmail;

        toggleForms('twoFactorForm', 'loginForm', 'registerForm');

        const methodIcon = document.getElementById('methodIcon');
        const methodTitle = document.getElementById('methodTitle');
        const methodDescription = document.getElementById('methodDescription');
        const resendBtn = document.getElementById('resend2FABtn');

        if (method === 'email') {
            methodIcon.className = 'bi bi-envelope me-2';
            methodTitle.textContent = 'Email код';
            methodDescription.textContent = `Код отправлен на ${userEmail}`;
            resendBtn.style.display = 'block';
        } else if (method === 'totp') {
            methodIcon.className = 'bi bi-phone me-2';
            methodTitle.textContent = 'Authenticator код';
            methodDescription.textContent = 'Введите код из приложения Authenticator';
            resendBtn.style.display = 'none';
        }

        document.getElementById('verification_code').value = '';

        if (method === 'email') {
            send2FACode();
        }
    }

    async function send2FACode() {
        try {
            const response = await fetch(`${getApiBase()}/api/2fa/send-email-code`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();
            if (!data.success) {
                show2FAMessage('Ошибка отправки кода на email', 'danger');
            }
        } catch (error) {
            show2FAMessage('Ошибка соединения с сервером', 'danger');
        }
    }

    async function verify2FACode(code, isBackupCode = false) {
        try {
            let endpoint, body;

            if (isBackupCode) {
                endpoint = '/api/2fa/verify-backup-code';
                body = { code: code };
            } else if (currentTwoFactorMethod === 'email') {
                endpoint = '/api/2fa/verify-email-login';
                body = { code: code };
            } else if (currentTwoFactorMethod === 'totp') {
                endpoint = '/api/2fa/verify-totp-login';
                body = { code: code };
            }

            const response = await fetch(`${getApiBase()}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            const data = await response.json();

            if (data.success) {
                show2FAMessage('Код подтвержден! Вход выполнен.', 'success');

                setTimeout(() => {
                    if (data.user && data.user.is_admin == 1) {
                        window.location.href = 'Admins.html';
                    } else {
                        window.location.href = 'upload.html';
                    }
                }, 1500);
            } else {
                show2FAMessage(data.message || 'Неверный код', 'danger');
            }
        } catch (error) {
            show2FAMessage('Ошибка проверки кода', 'danger');
        }
    }

    function show2FAMessage(message, type) {
        const container = document.getElementById('twoFactorFormMsg');
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        container.classList.remove('d-none');

        if (type === 'success') {
            setTimeout(() => {
                container.classList.add('d-none');
            }, 3000);
        }
    }

    const twoFactorForm = document.getElementById('twoFactorForm');
    if (twoFactorForm) {
        twoFactorForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const code = document.getElementById('verification_code').value.trim();
            if (code.length === 6) {
                verify2FACode(code);
            } else {
                show2FAMessage('Введите 6-значный код', 'danger');
            }
        });
    }

    const codeInput = document.getElementById('verification_code');
    if (codeInput) {
        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                verify2FACode(this.value);
            }
        });
    }

    const resendBtn = document.getElementById('resend2FABtn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function () {
            if (currentTwoFactorMethod === 'email') {
                send2FACode();
                show2FAMessage('Код отправлен повторно', 'info');
            }
        });
    }

    const cancelBtn = document.getElementById('cancel2FABtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            toggleForms('loginForm', 'twoFactorForm', 'registerForm');
            document.getElementById('verification_code').value = '';
            document.getElementById('twoFactorFormMsg').classList.add('d-none');
        });
    }

    const useBackupBtn = document.getElementById('useBackupCodeBtn');
    if (useBackupBtn) {
        useBackupBtn.addEventListener('click', function () {
            const backupForm = document.getElementById('backupCodeForm');
            backupForm.classList.toggle('d-none');
        });
    }

    const verifyBackupBtn = document.getElementById('verifyBackupBtn');
    if (verifyBackupBtn) {
        verifyBackupBtn.addEventListener('click', function () {
            const code = document.getElementById('backup_code').value.trim();
            if (code.length === 8) {
                verify2FACode(code, true);
            } else {
                show2FAMessage('Введите 8-значный резервный код', 'danger');
            }
        });
    }
});
