let currentUserId = null;

document.addEventListener('DOMContentLoaded', async () => {
    await checkAuth();
    await loadUsers();

    document.getElementById('logoutBtn').onclick = async () => {
        await fetch('/CloudStorageApp/public/logout', { method: 'POST', credentials: 'include' });
        window.location.href = '/CloudStorageApp/public/login.html';
    };
});

async function checkAuth() {
    try {
        const res = await fetch('/CloudStorageApp/public/users/current', {
            credentials: 'include'
        });

        if (!res.ok) {
            window.location.href = '/CloudStorageApp/public/login.html';
            return;
        }

        const data = await res.json();

        if (!data.success || !data.user) {
            window.location.href = '/CloudStorageApp/public/login.html';
            return;
        }

        if (data.user.role !== 'admin') {
            showMessage('У вас нет прав доступа к панели администратора', 'danger');
            setTimeout(() => {
                window.location.href = '/CloudStorageApp/public/upload.html';
            }, 2000);
            return;
        }

        currentUserId = data.user.id;
        document.getElementById('userGreeting').innerHTML = `
            <i class="bi bi-person-circle me-2"></i>
            ${data.user.first_name} ${data.user.last_name}
        `;
    } catch (error) {
        console.error('Ошибка при проверке авторизации:', error);
        window.location.href = '/CloudStorageApp/public/login.html';
    }
}

async function loadUsers() {
    try {
        const res = await fetch('/CloudStorageApp/public/admin/users/list', {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error('Ошибка при загрузке пользователей');
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка при загрузке пользователей');
        }

        renderUsersTable(data.users);
    } catch (error) {
        console.error('Ошибка при загрузке пользователей:', error);
        showMessage('Ошибка при загрузке списка пользователей', 'danger');
    }
}

function renderUsersTable(users) {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = '';

    if (!users || users.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">
                    <div>
                        <i class="bi bi-people"></i>
                        <h5>Пользователи не найдены</h5>
                        <p>Добавьте первого пользователя, нажав кнопку выше</p>
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    users.forEach(user => {
        const row = document.createElement('tr');

        const roleText = user.role === 'admin' ? 'Админ' : 'Юзер'; // Сокращаем текст
        const roleBadgeClass = user.role === 'admin' ? 'badge-admin' : 'badge-user';

        const genderText = user.gender === 'male' ? 'М' :
            user.gender === 'female' ? 'Ж' : '-';

        const isCurrentUser = Number(user.id) === Number(currentUserId);

        const truncateText = (text, maxLength = 15) => {
            if (!text) return '-';
            return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
        };

        row.innerHTML = `
            <td title="ID: ${user.id}">
                <div class="text-center">
                    <span class="badge bg-primary text-white" style="font-size: 0.7rem;">${user.id}</span>
                </div>
            </td>
            <td title="${user.email}">
                <div class="text-truncate-custom">
                    <i class="bi bi-envelope me-1" style="font-size: 0.8rem;"></i>
                    <span>${truncateText(user.email, 20)}</span>
                </div>
            </td>
            <td title="${user.first_name || 'Не указано'}">
                <span class="text-truncate-custom">${truncateText(user.first_name)}</span>
            </td>
            <td title="${user.last_name || 'Не указано'}">
                <span class="text-truncate-custom">${truncateText(user.last_name)}</span>
            </td>
            <td title="${user.middle_name || 'Не указано'}" class="d-none d-lg-table-cell">
                <span class="text-truncate-custom text-muted">${truncateText(user.middle_name)}</span>
            </td>
            <td class="text-center d-none d-lg-table-cell" title="Пол: ${user.gender === 'male' ? 'Мужской' : user.gender === 'female' ? 'Женский' : 'Не указан'}">
                <span class="badge bg-light text-dark border" style="font-size: 0.65rem;">${genderText}</span>
            </td>
            <td class="text-center d-none d-lg-table-cell" title="Возраст: ${user.age || 'Не указан'}">
                <span>${user.age || '-'}</span>
            </td>
            <td title="Роль: ${user.role === 'admin' ? 'Администратор' : 'Пользователь'}">
                <span class="badge-modern ${roleBadgeClass}" style="font-size: 0.65rem;">
                    <i class="bi ${user.role === 'admin' ? 'bi-shield-fill' : 'bi-person-fill'}"></i>
                    <span class="d-none d-xl-inline ms-1">${roleText}</span>
                </span>
            </td>
            <td>
                <div class="action-buttons">
                    <button class="btn-modern btn-primary-modern btn-sm-modern" onclick="editUser(${user.id})" title="Редактировать пользователя">
                        <i class="bi bi-pencil"></i>
                        <span class="d-none d-xxl-inline">Изм</span>
                    </button>
                    ${!isCurrentUser ? `
                        <button class="btn-modern btn-danger-modern btn-sm-modern" onclick="deleteUser(${user.id})" title="Удалить пользователя">
                            <i class="bi bi-trash3"></i>
                            <span class="d-none d-xxl-inline">Уд</span>
                        </button>
                    ` : `
                        <span class="badge-modern" style="background: linear-gradient(135deg, #10b981, #059669); color: white; font-size: 0.6rem; padding: 0.2rem 0.4rem;" title="Это ваш аккаунт">
                            <i class="bi bi-person-check"></i>
                        </span>
                    `}
                </div>
            </td>
        `;

        tbody.appendChild(row);
    });
}

async function editUser(userId) {
    try {
        const res = await fetch(`/CloudStorageApp/public/admin/users/get/${userId}`, {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error('Ошибка при получении данных пользователя');
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка при получении данных пользователя');
        }

        const user = data.user;

        document.getElementById('editUserId').value = user.id;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editFirstName').value = user.first_name || '';
        document.getElementById('editLastName').value = user.last_name || '';
        document.getElementById('editMiddleName').value = user.middle_name || '';
        document.getElementById('editRole').value = user.role || 'user';
        document.getElementById('editGender').value = user.gender || '';
        document.getElementById('editAge').value = user.age || '';
        document.getElementById('editPassword').value = '';

        document.getElementById('passwordRequired').style.display = 'none';

        document.querySelector('#editUserModal .modal-title').innerHTML = `
            <i class="bi bi-person-gear me-2"></i>
            Редактировать пользователя
        `;

        const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    } catch (error) {
        console.error('Ошибка при загрузке данных пользователя:', error);
        showMessage('Ошибка при загрузке данных пользователя', 'danger');
    }
}

async function updateUser() {
    try {
        const userId = document.getElementById('editUserId').value;
        const email = document.getElementById('editEmail').value.trim();
        const firstName = document.getElementById('editFirstName').value.trim();
        const lastName = document.getElementById('editLastName').value.trim();
        const middleName = document.getElementById('editMiddleName').value.trim();
        const role = document.getElementById('editRole').value;
        const gender = document.getElementById('editGender').value;
        const age = document.getElementById('editAge').value;
        const password = document.getElementById('editPassword').value;

        if (!email || !firstName || !lastName) {
            showMessage('Заполните все обязательные поля', 'warning');
            return;
        }

        const updateData = {
            email: email,
            first_name: firstName,
            last_name: lastName,
            role: role
        };

        if (middleName) updateData.middle_name = middleName;
        if (gender) updateData.gender = gender;
        if (age) updateData.age = parseInt(age);

        if (password.trim()) {
            updateData.password = password;
        }

        let url, method;

        if (!userId) {
            url = '/CloudStorageApp/public/admin/users/create';
            method = 'POST';

            if (!password.trim()) {
                showMessage('При создании пользователя пароль обязателен', 'warning');
                return;
            }
        } else {
            url = `/CloudStorageApp/public/admin/users/update/${userId}`;
            method = 'PUT';
        }

        const res = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updateData),
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error('Ошибка при обновлении пользователя');
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка при обновлении пользователя');
        }

        showMessage(userId ? 'Пользователь успешно обновлен' : 'Пользователь успешно создан', 'success');

        const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
        modal.hide();

        await loadUsers();
    } catch (error) {
        console.error('Ошибка при обновлении пользователя:', error);
        showMessage('Ошибка при обновлении пользователя', 'danger');
    }
}

async function deleteUser(userId) {

    const confirmModal = document.createElement('div');
    confirmModal.className = 'modal fade';
    confirmModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Подтверждение удаления
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="text-center py-3">
                        <i class="bi bi-person-x text-danger mb-3" style="font-size: 3rem;"></i>
                        <h5>Вы уверены, что хотите удалить этого пользователя?</h5>
                        <p class="text-muted">Это действие нельзя отменить. Все данные пользователя будут удалены навсегда.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>
                        Отмена
                    </button>
                    <button type="button" class="btn-modern btn-danger-modern" onclick="confirmDeleteUser(${userId})">
                        <i class="bi bi-trash3 me-1"></i>
                        Удалить пользователя
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(confirmModal);
    const modal = new bootstrap.Modal(confirmModal);
    modal.show();

    confirmModal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(confirmModal);
    });
}

async function confirmDeleteUser(userId) {
    try {
        const res = await fetch(`/CloudStorageApp/public/admin/users/delete/${userId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error('Ошибка при удалении пользователя');
        }

        const data = await res.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка при удалении пользователя');
        }

        showMessage('Пользователь успешно удален', 'success');

        const modals = document.querySelectorAll('.modal.show');
        modals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });

        await loadUsers();
    } catch (error) {
        console.error('Ошибка при удалении пользователя:', error);
        showMessage('Ошибка при удалении пользователя', 'danger');
    }
}

function showUsersSection() {
    document.getElementById('usersSection').style.display = 'block';
}

function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) {
        alert(message);
        return;
    }

    messageContainer.innerHTML = '';

    const alertDiv = document.createElement('div');

    let icon, bgClass, textClass;
    switch (type) {
        case 'success':
            icon = 'bi-check-circle-fill';
            bgClass = 'alert-success';
            textClass = 'text-success';
            break;
        case 'danger':
            icon = 'bi-exclamation-triangle-fill';
            bgClass = 'alert-danger';
            textClass = 'text-danger';
            break;
        case 'warning':
            icon = 'bi-exclamation-circle-fill';
            bgClass = 'alert-warning';
            textClass = 'text-warning';
            break;
        default:
            icon = 'bi-info-circle-fill';
            bgClass = 'alert-info';
            textClass = 'text-info';
    }

    alertDiv.className = `alert ${bgClass} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bi ${icon} me-2" style="font-size: 1.2rem;"></i>
            <div class="flex-grow-1">
                <strong>${message}</strong>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    messageContainer.appendChild(alertDiv);

    setTimeout(() => {
        if (alertDiv.parentNode) {
            const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
            alert.close();
        }
    }, 5000);
}

function showCreateUserModal() {

    document.getElementById('editUserId').value = '';
    document.getElementById('editEmail').value = '';
    document.getElementById('editFirstName').value = '';
    document.getElementById('editLastName').value = '';
    document.getElementById('editMiddleName').value = '';
    document.getElementById('editRole').value = 'user';
    document.getElementById('editGender').value = '';
    document.getElementById('editAge').value = '';
    document.getElementById('editPassword').value = '';

    document.getElementById('passwordRequired').style.display = 'inline';

    document.querySelector('#editUserModal .modal-title').innerHTML = `
        <i class="bi bi-person-plus me-2"></i>
        Создать пользователя
    `;

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function () {
    document.querySelector('#editUserModal .modal-title').innerHTML = `
        <i class="bi bi-person-gear me-2"></i>
        Редактировать пользователя
    `;
});

document.addEventListener('DOMContentLoaded', function () {

    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-modern')) {
            e.target.style.transform = 'scale(0.95)';
            setTimeout(() => {
                e.target.style.transform = '';
            }, 150);
        }
    });

    const form = document.getElementById('editUserForm');
    if (form) {
        const inputs = form.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function () {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });

            input.addEventListener('input', function () {
                if (this.classList.contains('is-invalid') && this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
    }

    const emailInput = document.getElementById('editEmail');
    if (emailInput) {
        emailInput.addEventListener('blur', function () {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            }
        });
    }

    const ageInput = document.getElementById('editAge');
    if (ageInput) {
        ageInput.addEventListener('input', function () {
            const age = parseInt(this.value);
            if (this.value && (age < 1 || age > 120)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    }
});

function searchUsers(query) {
    const rows = document.querySelectorAll('#usersTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function exportUsers() {

    console.log('Экспорт пользователей...');
}

document.addEventListener('keydown', function (e) {

    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        showCreateUserModal();
    }

    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });
    }
});
