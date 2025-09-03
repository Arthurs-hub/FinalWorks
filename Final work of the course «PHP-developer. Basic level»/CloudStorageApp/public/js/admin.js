let currentUserId = null;
let currentFiles = [];


async function checkAuth() {
    try {

        const response = await fetch('/users/current', {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Ошибка авторизации');
        }

        if (!data.user) {
            throw new Error('Данные пользователя не получены');
        }

        if (data.user.is_admin != 1) {
            throw new Error('Недостаточно прав доступа');
        }

        currentUser = data.user;
        window.currentUser = data.user;
        updateUserInfo(data.user);

        return data.user;

    } catch (error) {
        console.error('Ошибка авторизации:', error);
        showMessage('danger', 'Ошибка при инициализации панели администратора: ' + error.message);

        setTimeout(() => {
            window.location.href = '/login.html';
        }, 2000);

        throw error;
    }
}

function loadCurrentAdminName() {
    if (!currentUser) return;

    const adminNameElem = document.getElementById('adminName');
    if (adminNameElem) {
        const firstName = currentUser.first_name || '';
        const lastName = currentUser.last_name || '';
        const displayName = (firstName || lastName) ? `${firstName} ${lastName}`.trim() : 'Администратор';
        adminNameElem.textContent = displayName;
    }
}

async function showVideoViewerAdmin(fileId) {
    try {
        let file = currentFiles.find(f => f.id == fileId);
        if (!file) {
            const res = await fetch(`/files/info/${fileId}`, { credentials: 'include' });
            if (!res.ok) throw new Error('Не удалось получить информацию о файле');
            const data = await res.json();
            if (!data.success || !data.file) throw new Error('Файл не найден');
            file = data.file.file || data.file;
        }

        const modalElement = document.getElementById('viewFileModal');
        if (!modalElement) {
            alert('Модальное окно предпросмотра не найдено');
            return;
        }

        const fileDetailsContent = document.getElementById('fileDetailsContent');
        if (!fileDetailsContent) return;

        showVideoPreviewInModal(file);

        const modal = new bootstrap.Modal(modalElement);
        modal.show();

    } catch (error) {
        console.error('Ошибка при открытии видео предпросмотра:', error);
        showMessage('danger', 'Не удалось открыть видео предпросмотр');
    }
}

function showVideoPreviewInModal(file) {
    const filePreviewDiv = document.getElementById('fileDetailsContent');
    if (!filePreviewDiv) return;

    filePreviewDiv.innerHTML = `
        <div class="video-preview-container" style="position: relative; max-width: 100%; background: black; border-radius: 8px; overflow: hidden; display: flex; justify-content: center;">
            <canvas id="modalVideoCanvas" width="640" height="360" style="border-radius: 8px;"></canvas>
            <video id="modalVideoElement" muted playsinline style="display: none;">
                <source src="/files/preview/${file.id}" type="${file.mime_type}">
            </video>
            <div class="video-controls d-flex align-items-center gap-2 p-2" style="position:absolute; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5);">
                <button id="modalPlayPauseBtn" class="btn btn-light btn-sm" type="button" title="Воспроизвести/Пауза">
                    <i class="bi bi-play-fill"></i>
                </button>
                <input id="modalSeekBar" type="range" min="0" max="100" step="0.1" value="0" class="form-range flex-grow-1" style="accent-color:#0d6efd;">
                <span id="modalTimeLabel" class="text-white small" style="min-width: 90px; text-align:right;">0:00 / 0:00</span>
                <button id="modalMuteBtn" class="btn btn-light btn-sm" type="button" title="Звук вкл/выкл">
                    <i class="bi bi-volume-mute"></i>
                </button>
            </div>
        </div>
    `;

    const video = document.getElementById('modalVideoElement');
    const canvas = document.getElementById('modalVideoCanvas');
    const ctx = canvas.getContext('2d');

    const playBtn = document.getElementById('modalPlayPauseBtn');
    const seekBar = document.getElementById('modalSeekBar');
    const timeLabel = document.getElementById('modalTimeLabel');
    const muteBtn = document.getElementById('modalMuteBtn');

    const formatTime = (seconds) => {
        if (!isFinite(seconds)) return '0:00';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    const updateTimeLabel = () => {
        const cur = isFinite(video.currentTime) ? video.currentTime : 0;
        const dur = isFinite(video.duration) ? video.duration : 0;
        timeLabel.textContent = `${formatTime(cur)} / ${formatTime(dur)}`;
    };

    const updatePlayIcon = () => {
        const icon = playBtn.querySelector('i');
        if (!icon) return;
        icon.className = video.paused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
    };

    const updateMuteIcon = () => {
        const icon = muteBtn.querySelector('i');
        if (!icon) return;
        icon.className = (video.muted || video.volume === 0) ? 'bi bi-volume-mute' : 'bi bi-volume-up';
    };

    updatePlayIcon();
    updateMuteIcon();

    playBtn.addEventListener('click', () => {
        if (video.paused) {
            video.play().catch(e => console.warn('Play error:', e.name));
        } else {
            video.pause();
        }
    });

    muteBtn.addEventListener('click', () => {
        video.muted = !video.muted;
        updateMuteIcon();
    });

    video.addEventListener('volumechange', () => {
        updateMuteIcon();
    });

    seekBar.addEventListener('input', () => {
        const t = parseFloat(seekBar.value) || 0;
        if (isFinite(video.duration)) {
            video.currentTime = Math.min(Math.max(0, t), video.duration);
        } else {
            video.currentTime = t;
        }
        if (video.paused) {
            drawFrame();
        }
        updateTimeLabel();
    });

    let animationFrameId = null;

    const drawFrame = () => {
        if (video.paused || video.ended) {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
            return;
        }

        const videoAspect = video.videoWidth / video.videoHeight;
        const canvasAspect = canvas.width / canvas.height;
        let drawWidth, drawHeight, drawX, drawY;

        if (videoAspect > canvasAspect) {
            drawWidth = canvas.width;
            drawHeight = canvas.width / videoAspect;
            drawX = 0;
            drawY = (canvas.height - drawHeight) / 2;
        } else {
            drawHeight = canvas.height;
            drawWidth = canvas.height * videoAspect;
            drawY = 0;
            drawX = (canvas.width - drawWidth) / 2;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(video, drawX, drawY, drawWidth, drawHeight);

        animationFrameId = requestAnimationFrame(drawFrame);
    };

    video.addEventListener('loadedmetadata', () => {
        const maxWidth = filePreviewDiv.clientWidth;
        const maxHeight = window.innerHeight * 0.6; // 60% высоты окна

        let videoWidth = video.videoWidth;
        let videoHeight = video.videoHeight;

        let scale = Math.min(maxWidth / videoWidth, maxHeight / videoHeight, 1);

        canvas.width = videoWidth * scale;
        canvas.height = videoHeight * scale;

        canvas.style.margin = '0 auto';

        if (isFinite(video.duration)) {
            seekBar.max = video.duration;
        }
        updateTimeLabel();
    });

    video.addEventListener('loadeddata', () => {
        video.currentTime = 0.1;
    });

    video.addEventListener('timeupdate', () => {
        if (isFinite(video.currentTime)) {
            seekBar.value = video.currentTime;
        }
        updateTimeLabel();
    });

    video.addEventListener('seeked', () => {
        drawFrame();
    });

    video.addEventListener('play', () => {
        updatePlayIcon();
        drawFrame();
    });

    video.addEventListener('pause', () => {
        updatePlayIcon();
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
    });

    video.muted = true;
    video.play().then(() => {
        video.muted = false;
    }).catch(e => {
        console.warn("Modal video autoplay was prevented:", e.name);
    });

    window.currentPreviewVideo = video;
    window.currentPreviewCanvasAnimationId = animationFrameId;
}


const modalElement = document.getElementById('viewFileModal');
if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', () => {
        const video = modalElement.querySelector('video');
        if (video) {
            video.pause();
            video.currentTime = 0;
            video.removeAttribute('src');
            video.load();
        }

        if (window.currentPreviewVideo) {
            try {
                window.currentPreviewVideo.pause();
                window.currentPreviewVideo.removeAttribute('src');
                window.currentPreviewVideo.load();
                if (window.currentPreviewVideo.parentNode) {
                    window.currentPreviewVideo.parentNode.removeChild(window.currentPreviewVideo);
                }
            } catch (_) { }
            window.currentPreviewVideo = null;
        }

        if (window.currentPreviewCanvasAnimationId) {
            cancelAnimationFrame(window.currentPreviewCanvasAnimationId);
            window.currentPreviewCanvasAnimationId = null;
        }

        const fileDetailsContent = document.getElementById('fileDetailsContent');
        if (fileDetailsContent) {
            fileDetailsContent.innerHTML = '';
        }
    });
};


let currentUser = null;

let usersLoaded = false;
let filesLoaded = false;
let logsLoaded = false;


document.addEventListener('DOMContentLoaded', async () => {
    try {
        await checkAuth();
        loadCurrentAdminName();
        await loadStats();

        setupEventListeners();

        handleHashChange();

        const currentHash = window.location.hash;
        if (!currentHash || currentHash === '' || currentHash === '#dashboard' || currentHash === '#system') {
            await refreshSystemHealth();
        }
    } catch (error) {
        console.error('Ошибка инициализации:', error);
        showMessage('danger', 'Ошибка при инициализации панели администратора');
    }
});

function showSection(name) {
    const sections = {
        dashboard: document.getElementById('dashboardSection'),
        users: document.getElementById('usersSection'),
        files: document.getElementById('filesSection'),
        logs: document.getElementById('logsSection'),
        system: document.getElementById('systemSection'),
        security: document.getElementById('securitySection')
    };

    const navLinks = document.querySelectorAll('nav.sidebar .nav-link');
    const searchContainer = document.getElementById('searchContainer');
    const refreshBtn = document.getElementById('refreshBtn');
    const exportBtn = document.getElementById('exportBtn');

    Object.entries(sections).forEach(([key, section]) => {
        if (section) {
            section.style.display = (key === name) ? 'block' : 'none';
        }
    });

    navLinks.forEach(link => {
        const linkHash = link.getAttribute('href') || link.getAttribute('data-section');
        if (linkHash) {
            const cleanHash = linkHash.replace('#', '');
            if (cleanHash === name) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        }
    });


    if (name === 'users') {
        searchContainer.style.display = 'block';
    } else {
        searchContainer.style.display = 'none';
    }

    if (refreshBtn && exportBtn) {
        if (name === 'dashboard' || name === 'users') {
            refreshBtn.style.display = 'inline-flex';
            exportBtn.style.display = 'inline-flex';
        } else {
            refreshBtn.style.display = 'none';
            exportBtn.style.display = 'none';
        }
    }

    if (name === 'users' && !usersLoaded) {
        loadUsers();
        usersLoaded = true;
    }
    if (name === 'files' && !filesLoaded) {
        loadFiles();
        filesLoaded = true;
    }
    if (name === 'logs' && !logsLoaded) {
        loadLogs();
        logsLoaded = true;
    }

    if (name === 'dashboard' || name === 'system') {
        if (typeof refreshSystemHealth === 'function') {
            console.log('refreshSystemHealth called for section:', name);
            refreshSystemHealth();
        }
    }
    if (name === 'security') {
        if (typeof refreshSecurityReport === 'function') {
            refreshSecurityReport();
        }
    }
}

function handleHashChange() {
    let hash = window.location.hash.substring(1);
    const validSections = ['dashboard', 'users', 'files', 'logs', 'system', 'security'];
    if (!hash || !validSections.includes(hash)) {
        hash = 'dashboard';
    }
    showSection(hash);
}


window.addEventListener('hashchange', handleHashChange);

window.addEventListener('load', () => {
    handleHashChange();
});


let currentUsers = [];

function showMessage(type, text) {
    const container = document.getElementById('messageContainer');
    if (!container) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        ${text}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    container.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.classList.remove('show');
        alertDiv.classList.add('hide');
        alertDiv.addEventListener('transitionend', () => alertDiv.remove());
    }, 5000);
}

function formatFileSize(bytes) {
    if (bytes === 0 || bytes === '0') return '0 B';

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getCurrentUserId() {
    return window.currentUserId || null;
}

function loadCurrentAdminName() {
    if (!currentUser) return;

    const adminNameElem = document.getElementById('adminName');
    if (adminNameElem) {
        const firstName = currentUser.first_name || '';
        const lastName = currentUser.last_name || '';
        const displayName = (firstName || lastName) ? `${firstName} ${lastName}`.trim() : 'Администратор';
        adminNameElem.textContent = displayName;
    }
}

function updateStatsDisplay(stats) {
    const elements = {
        'totalUsers': stats.users?.total || 0,
        'totalAdmins': stats.users?.admins || 0,
        'activeUsers30': stats.users?.active_30_days || 0,
        'activeUsers7': stats.users?.active_7_days || 0,
        'totalFiles': stats.files?.total_count || 0,
        'totalSize': stats.files?.total_size_formatted || '0 B',
        'totalDirectories': stats.files?.total_directories || 0,
        'totalShares': stats.files?.total_shares || 0,
        'phpVersion': stats.system?.php_version || 'Н/Д',
        'systemLoad': (stats.system?.memory_usage_percent !== undefined && stats.system.memory_usage_percent !== null)
            ? `${stats.system.memory_usage_percent}%`
            : stats.system?.memory_usage_formatted || 'Н/Д',

    };

    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            console.log(`Updating element #${id} with value: ${value}`);
            element.textContent = value;
            element.style.color = '';
        } else {
            console.warn(`Element with id="${id}" not found in DOM`);
        }
    });

}

function showStatsError() {
    const elements = [
        'totalUsers', 'totalAdmins', 'activeUsers30', 'activeUsers7',
        'totalFiles', 'totalSize', 'totalDirectories', 'totalShares',
        'phpVersion', 'systemLoad'

    ];

    elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = 'Н/Д';
            element.style.color = '#dc3545';
        }
    });
}

async function loadUsers() {
    try {
        const response = await fetch('/admin/users', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const data = await response.json();
        if (data.success) {
            currentUsers = data.users;
            displayUsers(currentUsers);
        } else {
            const errors = data.errors || [data.error] || ['Неизвестная ошибка'];
            showMessage('danger', errors.join(', '));
        }
    } catch (err) {
        console.error('Failed to load users:', err);
        showMessage('danger', 'Ошибка загрузки пользователей: ' + err.message);
    }
}

function displayUsers(users) {
    const tbody = document.querySelector('#usersTable tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    users.forEach(user => {
        const row = document.createElement('tr');

        const statusBadge = user.is_banned ?
            '<span class="badge bg-danger">Заблокирован</span>' :
            '<span class="badge bg-success">Активен</span>';

        const roleBadge = user.is_admin ?
            '<span class="badge bg-primary">Администратор</span>' :
            '<span class="badge bg-secondary">Пользователь</span>';

        const lastLogin = user.last_login ?
            new Date(user.last_login).toLocaleString('ru-RU') :
            'Никогда';

        row.innerHTML = `
            <td>${user.id}</td>
            <td>${user.email}</td>
            <td>${user.first_name} ${user.last_name}</td>
            <td>${roleBadge}</td>
            <td>${statusBadge}</td>
            <td>${user.files_count || 0}</td>
            <td>${formatFileSize(user.total_size || 0)}</td>
            <td>${lastLogin}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editUser(${user.id})" title="Редактировать">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-info" onclick="viewUser(${user.id})" title="Просмотр">
                        <i class="bi bi-eye"></i>
                    </button>
                    ${user.id !== getCurrentUserId() ? `
                        <button class="btn btn-outline-danger" onclick="deleteUser(${user.id})" title="Удалить">
                            <i class="bi bi-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        `;

        tbody.appendChild(row);
    });
}

async function refreshData() {
    const refreshBtn = document.getElementById('refreshBtn');
    if (!refreshBtn) return;

    const originalContent = refreshBtn.innerHTML;

    try {
        refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> <span>Обновление...</span>';
        refreshBtn.disabled = true;

        const activeSection = document.querySelector('nav.sidebar .nav-link.active');
        if (!activeSection) return;
        const sectionName = (activeSection.getAttribute('href') || activeSection.getAttribute('data-section')).replace('#', '');

        switch (sectionName) {
            case 'users':
                await loadUsers();
                break;
            case 'files':
                await loadFiles();
                break;
            case 'logs':
                await loadLogs();
                break;
            case 'dashboard':
                await loadStats();
                break;
            case 'system':
                if (typeof refreshSystemHealth === 'function') {
                    await refreshSystemHealth();
                }
                break;
            case 'security':
                if (typeof refreshSecurityReport === 'function') {
                    await refreshSecurityReport();
                }
                break;
        }

        showMessage('success', 'Данные обновлены');

    } catch (error) {
        console.error('Ошибка обновления:', error);
        showMessage('danger', 'Ошибка при обновлении данных');
    } finally {
        refreshBtn.innerHTML = originalContent;
        refreshBtn.disabled = false;
    }
}

function updateUserInfo(user) {
    const userNameElement = document.getElementById('userName');
    if (userNameElement) {
        userNameElement.textContent = `${user.first_name} ${user.last_name}`;
    }

    const userEmailElement = document.getElementById('userEmail');
    if (userEmailElement) {
        userEmailElement.textContent = user.email;
    }
}

function showCreateUserModal() {

    const form = document.getElementById('createUserForm');
    if (form) {
        form.reset();
    }


    const existingModals = document.querySelectorAll('.modal.show');
    existingModals.forEach(modal => {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    });


    const modalElement = document.getElementById('createUserModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();
    }
}

async function createUser() {
    try {

        const password = document.getElementById('createUserPassword').value;
        const confirmPassword = document.getElementById('createUserConfirmPassword').value;

        if (password !== confirmPassword) {
            showMessage('danger', 'Пароли не совпадают');
            return;
        }

        const userData = {
            email: document.getElementById('createUserEmail').value.trim(),
            first_name: document.getElementById('createUserFirstName').value.trim(),
            last_name: document.getElementById('createUserLastName').value.trim(),
            middle_name: document.getElementById('createUserMiddleName').value.trim(),
            password: password,
            age: document.getElementById('createUserAge').value,
            gender: document.getElementById('createUserGender').value,
            is_admin: document.getElementById('createUserIsAdmin').checked ? 1 : 0,
        };

        const response = await fetch('/admin/users/create', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(userData)
        });

        const data = await response.json();

        if (data.success) {
            showMessage('success', data.message || 'Пользователь успешно создан');

            const modalElement = document.getElementById('createUserModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            await loadUsers();
            await loadStats();
        } else {

            const errors = data.errors || [data.error] || ['Неизвестная ошибка'];
            showMessage('danger', errors.join(', '));
        }
    } catch (error) {
        console.error('Ошибка при создании пользователя:', error);
        showMessage('danger', 'Ошибка при создании пользователя');
    }
}

async function editUser(userId) {
    try {

        const user = currentUsers.find(u => u.id === userId);
        if (!user) {
            showMessage('danger', 'Пользователь не найден');
            return;
        }


        const existingModals = document.querySelectorAll('.modal.show');
        existingModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });


        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUserEmail').value = user.email;
        document.getElementById('editUserFirstName').value = user.first_name || '';
        document.getElementById('editUserLastName').value = user.last_name || '';
        document.getElementById('editUserIsAdmin').checked = user.is_admin == 1;
        document.getElementById('editUserIsBanned').checked = user.is_banned == 1;


        const modalElement = document.getElementById('editUserModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });
            modal.show();
        }

    } catch (error) {
        console.error('Ошибка при открытии формы редактирования:', error);
        showMessage('danger', 'Ошибка при открытии формы редактирования');
    }
}

async function saveUserChanges() {
    try {
        const userId = document.getElementById('editUserId').value;
        const email = document.getElementById('editUserEmail').value.trim();
        const firstName = document.getElementById('editUserFirstName').value.trim();
        const lastName = document.getElementById('editUserLastName').value.trim();
        const isAdmin = document.getElementById('editUserIsAdmin').checked;
        const isBanned = document.getElementById('editUserIsBanned').checked;
        const password = document.getElementById('editUserPassword').value;

        if (!email || !firstName || !lastName) {
            showMessage('warning', 'Заполните все обязательные поля');
            return;
        }

        const updateData = {
            email: email,
            first_name: firstName,
            last_name: lastName,
            is_admin: isAdmin ? 1 : 0,
            is_banned: isBanned ? 1 : 0
        };

        if (password && password.trim() !== '') {
            updateData.password = password.trim();
        }

        const response = await fetch(`/admin/users/update/${userId}`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(updateData)
        });

        const data = await response.json();

        if (data.success) {
            showMessage('success', 'Данные успешно обновлены');

            const modalElement = document.getElementById('editUserModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }

            await loadUsers();
        } else {

            const errorMsg = data.error || 'Ошибка при обновлении пользователя';
            showMessage('danger', errorMsg);
        }
    } catch (error) {
        console.error('Ошибка при сохранении изменений:', error);
        showMessage('danger', 'Ошибка при сохранении изменений');
    }
}

async function viewUser(userId) {
    try {

        const user = currentUsers.find(u => u.id === userId);
        if (!user) {
            showMessage('danger', 'Пользователь не найден');
            return;
        }


        const existingModals = document.querySelectorAll('.modal.show');
        existingModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        });


        const userDetailsContent = document.getElementById('userDetailsContent');
        userDetailsContent.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Основная информация</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>ID:</strong></td>
                            <td>${user.id}</td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td>${user.email}</td>
                        </tr>
                        <tr>
                            <td><strong>Имя:</strong></td>
                            <td>${user.first_name || 'Не указано'}</td>
                        </tr>
                        <tr>
                            <td><strong>Фамилия:</strong></td>
                            <td>${user.last_name || 'Не указано'}</td>
                        </tr>
                        <tr>
                            <td><strong>Отчество:</strong></td>
                            <td>${user.middle_name || 'Не указано'}</td>
                        </tr>
                        <tr>
                            <td><strong>Возраст:</strong></td>
                            <td>${user.age || 'Не указан'}</td>
                        </tr>
                        <tr>
                            <td><strong>Пол:</strong></td>
                            <td>${formatGender(user.gender)}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Статистика и статус</h6>
                    <table class="table table-sm">
                        <tr>
                            <td><strong>Роль:</strong></td>
                            <td>${user.is_admin ? '<span class="badge bg-primary">Администратор</span>' : '<span class="badge bg-secondary">Пользователь</span>'}</td>
                        </tr>
                        <tr>
                            <td><strong>Статус:</strong></td>
                            <td>${user.is_banned ? '<span class="badge bg-danger">Заблокирован</span>' : '<span class="badge bg-success">Активен</span>'}</td>
                        </tr>
                        <tr>
                            <td><strong>Дата регистрации:</strong></td>
                            <td>${user.created_at ? new Date(user.created_at).toLocaleString('ru-RU') : 'Не указана'}</td>
                        </tr>
                        <tr>
                            <td><strong>Последний вход:</strong></td>
                            <td>${user.last_login ? new Date(user.last_login).toLocaleString('ru-RU') : 'Никогда'}</td>
                        </tr>
                        <tr>
                            <td><strong>Количество файлов:</strong></td>
                            <td>${user.files_count || 0}</td>
                        </tr>
                        <tr>
                            <td><strong>Общий размер файлов:</strong></td>
                            <td>${formatFileSize(user.total_size || 0)}</td>
                        </tr>
                        <tr>
                            <td><strong>Количество папок:</strong></td>
                            <td>${user.directories_count || 0}</td>
                        </tr>
                        <tr>
                            <td><strong>Расшариваний:</strong></td>
                            <td>${user.shared_files_count || 0}</td>
                        </tr>
                        <tr>
                            <td><strong>Получено расшариваний:</strong></td>
                            <td>${user.received_shares_count || 0}</td>
                        </tr>
                    </table>
                </div>
            </div>
        `;


        const modalElement = document.getElementById('viewUserModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }

    } catch (error) {
        console.error('Ошибка при просмотри пользователя:', error);
        showMessage('danger', 'Ошибка при просмотри пользователя');
    }
}

function formatGender(gender) {
    switch (gender) {
        case 'male':
            return 'Мужской';
        case 'female':
            return 'Женский';
        default:
            return 'Не указан';
    }
}

async function deleteUser(userId) {
    if (!confirm('Вы уверены, что хотите удалить этого пользователя?')) {
        return;
    }

    try {
        const response = await fetch(`/admin/users/delete/${userId}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });
        if (!response.ok) {
            const text = await response.text();
            console.error('Delete user error response:', text);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            console.log("User deleted by admin", {
                'deleted_user_id': userId,
            });
            showMessage('success', data.message || 'Пользователь успешно удален');
            await loadUsers();
        } else {
            console.error("AdminRepository::deleteUserById error", {
                'user_id': userId,
                'error': data.error || 'Unknown error',
            });

            const errorMsg = data.error || 'Ошибка при удалении пользователя';
            showMessage('danger', errorMsg);
        }
    } catch (error) {
        console.error('Ошибка удаления пользователя:', error);
        showMessage('danger', 'Ошибка при удалении пользователя');
    }
}

async function clearLogs() {
    try {
        const response = await fetch('/admin/logs/clear', {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            showMessage('success', data.message || 'Логи успешно очищены');
            await loadLogs();
        } else {
            showMessage('danger', 'Ошибка при очистке логов: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка очистки логов:', error);
        showMessage('danger', 'Ошибка при очистке логов');
    }
}

async function exportUsers() {
    try {

        const link = document.createElement('a');
        link.href = '/admin/users/export/download';
        link.download = `users_export_${new Date().toISOString().slice(0, 10)}.csv`;

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

    } catch (error) {
        console.error('Ошибка экспорта:', error);
        showMessage('danger', 'Ошибка при экспорте пользователей: ' + error.message);
    }
}

function searchUsers() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    const query = searchInput.value.toLowerCase().trim();

    if (!query) {
        displayUsers(currentUsers);
        return;
    }

    const filteredUsers = currentUsers.filter(user => {
        return user.email.toLowerCase().includes(query) ||
            (user.first_name && user.first_name.toLowerCase().includes(query)) ||
            (user.last_name && user.last_name.toLowerCase().includes(query)) ||
            ((user.first_name || '') + ' ' + (user.last_name || '')).toLowerCase().includes(query);
    });

    displayUsers(filteredUsers);
}

function filterByRole(role) {
    let filteredUsers;

    if (role === 'all') {
        filteredUsers = currentUsers;
    } else if (role === 'admin') {
        filteredUsers = currentUsers.filter(user => user.is_admin);
    } else if (role === 'user') {
        filteredUsers = currentUsers.filter(user => !user.is_admin);
    } else if (role === 'banned') {
        filteredUsers = currentUsers.filter(user => user.is_banned);
    } else {
        filteredUsers = currentUsers;
    }

    displayUsers(filteredUsers);
}

function setupEventListeners() {

    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {

            const activeSection = document.querySelector('nav.sidebar .nav-link.active');
            if (!activeSection) return;
            const sectionName = (activeSection.getAttribute('href') || activeSection.getAttribute('data-section')).replace('#', '');

            switch (sectionName) {
                case 'users':
                    loadUsers();
                    break;
                case 'files':
                    loadFiles();
                    break;
                case 'logs':
                    loadLogs();
                    break;
                case 'dashboard':
                    loadStats();
                    break;
                case 'system':
                    if (typeof refreshSystemHealth === 'function') {
                        refreshSystemHealth();
                    }
                    break;
                case 'security':
                    if (typeof refreshSecurityReport === 'function') {
                        refreshSecurityReport();
                    }
                    break;
            }
        });
    }


    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', exportUsers);
    }


    const searchInput = document.getElementById('searchInput');
    if (searchInput) {

        searchInput.removeAttribute('onkeyup');
        searchInput.addEventListener('input', searchUsers);
    }


    const roleFilters = document.querySelectorAll('[data-role-filter]');
    roleFilters.forEach(filter => {
        filter.addEventListener('click', function (e) {
            e.preventDefault();
            const role = this.getAttribute('data-role-filter');
            filterByRole(role);

            roleFilters.forEach(f => f.classList.remove('active'));
            this.classList.add('active');
        });
    });


    const saveUserBtn = document.getElementById('saveUserBtn');
    if (saveUserBtn) {
        saveUserBtn.addEventListener('click', saveUserChanges);
    }


    const clearLogsBtn = document.getElementById('clearLogsBtn');
    if (clearLogsBtn) {
        clearLogsBtn.addEventListener('click', clearLogs);
    }


    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function () {

            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());


            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });

        modal.addEventListener('show.bs.modal', function () {

            const otherModals = document.querySelectorAll('.modal.show');
            otherModals.forEach(otherModal => {
                if (otherModal !== modal) {
                    const modalInstance = bootstrap.Modal.getInstance(otherModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            });
        });
    });
}

function generatePassword() {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";

    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }

    document.getElementById('createUserPassword').value = password;
    document.getElementById('createUserConfirmPassword').value = password;


    showMessage('info', `Сгенерированный пароль: ${password}`);
}

function cleanupModals() {

    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
        modal.style.display = 'none';
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        modal.removeAttribute('role');
    });


    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => backdrop.remove());


    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
}

async function logout(event) {
    event.preventDefault();
    try {
        const response = await fetch('/logout', {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });
        if (response.ok) {
            window.location.href = '/login.html';
        } else {
            showMessage('danger', 'Ошибка при выходе из системы');
        }
    } catch (error) {
        console.error('Ошибка при выходе:', error);
        showMessage('danger', 'Ошибка при выходе из системы');
    }
}


document.addEventListener('DOMContentLoaded', () => {
    const logoutLink = document.querySelector('a.nav-link[onclick^="logout"]');
    if (logoutLink) {

        logoutLink.removeAttribute('onclick');

        logoutLink.addEventListener('click', logout);
    }
});


document.addEventListener('DOMContentLoaded', () => {
    const logLevelSelect = document.getElementById('logLevel');
    if (logLevelSelect) {
        logLevelSelect.addEventListener('change', () => {
            loadLogs();
        });
    }
});


window.addEventListener('error', function (e) {
    if (e.message && e.message.includes('modal')) {
        console.warn('Modal error detected, cleaning up...', e);
        cleanupModals();
    }
});

function escapeHtml(text) {
    return text.replace(/[&<>"']/g, function (m) {
        switch (m) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            case "'": return '&#39;';
            default: return m;
        }
    });
}

async function loadLogs() {
    const logsContainer = document.getElementById('logsContainer');
    const logLevelSelect = document.getElementById('logLevel');
    const level = logLevelSelect ? logLevelSelect.value : 'all';

    logsContainer.innerHTML = `<div class="text-center">
        <div class="loading-spinner me-2"></div>
        Загрузка логов...
    </div>`;

    try {
        const response = await fetch(`/admin/logs?level=${encodeURIComponent(level)}`, {
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) {
            throw new Error('Ошибка при загрузке логов');
        }
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Ошибка при загрузке логов');
        }

        const logs = data.logs || [];
        if (logs.length === 0) {
            logsContainer.innerHTML = '<div class="text-center text-secondary">Логи отсутствуют</div>';
            return;
        }

        logsContainer.innerHTML = '';

        logs.forEach(log => {
            const logEntry = document.createElement('div');
            logEntry.classList.add('log-entry');
            if (log.level) {
                logEntry.classList.add(log.level.toLowerCase());
            }

            let logTime = '';
            if (log.timestamp) {

                let isoString = log.timestamp.replace(' ', 'T') + 'Z';
                const date = new Date(isoString);
                if (isNaN(date.getTime())) {
                    logTime = log.timestamp;
                } else {
                    logTime = date.toLocaleString();
                }
            } else {
                logTime = new Date().toLocaleString();
            }

            logEntry.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div class="log-level">${log.level ? log.level.toUpperCase() : 'INFO'}</div>
                    <div class="log-time">${logTime}</div>
                </div>
                <div class="log-message">${escapeHtml(log.message || '')}</div>
            `;

            logsContainer.appendChild(logEntry);
        });
    } catch (error) {
        logsContainer.innerHTML = `<div class="text-danger text-center">Ошибка при загрузке логов: ${escapeHtml(error.message)}</div>`;
    }
}

async function loadFiles() {
    try {
        const response = await fetch('/admin/files', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error(`HTTP error ${response.status}`);
        const data = await response.json();
        if (data.success) {
            currentFiles = data.files;
            displayFiles(currentFiles);
        } else {
            throw new Error(data.error || 'Unknown error');
        }
    } catch (err) {
        console.error('Failed to load files:', err);
        showMessage('danger', 'Ошибка загрузки файлов: ' + err.message);
    }
}

function displayFiles(files) {
    const tbody = document.querySelector('#filesTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!files.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Файлы не найдены</td></tr>';
        return;
    }
    files.forEach(file => {
        const fileName = file.name || file.filename || 'Неизвестно';
        const fileType = file.type || file.mime_type || 'Неизвестно';

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${file.id}</td>
            <td class="text-truncate-custom" title="${fileName}">${fileName}</td>
            <td class="text-truncate-custom" title="${file.owner_email}">${file.owner_email}</td>
            <td>${file.size_formatted || formatFileSize(file.size)}</td>
            <td class="text-truncate-custom" title="${fileType}">${fileType}</td>
            <td>${file.created_at ? new Date(file.created_at).toLocaleString('ru-RU') : 'Неизвестно'}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="viewFile(${file.id})" title="Просмотр">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteFile(${file.id})" title="Удалить">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function displayLogs(logs) {
    const container = document.getElementById('logsContainer');
    if (!container) return;
    container.innerHTML = '';
    if (!logs.length) {
        container.innerHTML = '<div class="text-center">Логи не найдены</div>';
        return;
    }
    logs.forEach(log => {
        const div = document.createElement('div');
        div.className = `log-entry ${log.level || ''}`;
        div.innerHTML = `
            <div><strong class="log-level">${log.level || 'INFO'}</strong> <span class="log-time">${new Date(log.timestamp).toLocaleString('ru-RU')}</span></div>
            <div class="log-message">${log.message}</div>
        `;
        container.appendChild(div);
    });
}

function renderFilesTable(files) {
    const tbody = document.querySelector('#filesTable tbody');
    tbody.innerHTML = '';

    if (!files.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center">Файлы не найдены</td></tr>`;
        return;
    }

    files.forEach(file => {
        const tr = document.createElement('tr');

        const createdAt = new Date(file.created_at);
        const formattedDate = createdAt.toLocaleString('ru-RU', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });

        tr.innerHTML = `
            <td>${file.id}</td>
            <td class="text-truncate-custom" title="${file.name}">${file.name}</td>
            <td class="text-truncate-custom" title="${file.owner_email}">${file.owner_email}</td>
            <td>${file.size_formatted}</td>
            <td class="text-truncate-custom" title="${file.type}">${file.type}</td>
            <td>${formattedDate}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" title="Просмотреть файл" onclick="viewFile('${file.id}')">
                    <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" title="Удалить файл" onclick="deleteFile('${file.id}')">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}


function viewFile(fileId) {

    const file = currentFiles.find(f => f.id == fileId);
    if (!file) {
        showMessage('danger', 'Файл не найден');
        return;
    }


    showFileModal(file);
}

function showFileModal(file) {
    const existingModals = document.querySelectorAll('.modal.show');
    existingModals.forEach(modal => {
        const modalInstance = bootstrap.Modal.getInstance(modal);
        if (modalInstance) {
            modalInstance.hide();
        }
    });

    const isImage = file.mime_type && file.mime_type.startsWith('image/');
    const isPdf = file.mime_type && file.mime_type === 'application/pdf';
    const isVideo = file.mime_type && file.mime_type.startsWith('video/');
    const isPreviewable = isImage || isPdf || isVideo;

    const fileDetailsContent = document.getElementById('fileDetailsContent');
    if (!fileDetailsContent) {
        createFileModal(file);
        return;
    }

    if (isVideo) {
        showVideoPreviewInModal(file);

        const modalElement = document.getElementById('viewFileModal');
        if (modalElement) {
            const modalDialog = modalElement.querySelector('.modal-dialog');
            modalDialog.classList.remove('modal-xl');
            modalDialog.classList.add('modal-lg');

            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
        return;
    }

    fileDetailsContent.innerHTML = `
        <div class="row">
            ${isPreviewable ? `
            <div class="col-12 mb-3">
                <div class="text-center">
                    ${isImage ? `
                        <img src="/files/download/${file.id}" 
                             alt="${file.filename || 'Изображение'}" 
                             class="img-fluid" 
                             style="max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <div style="display: none;" class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Не удалось загрузить изображение
                            <br><small>Файл: ${file.filename || file.name}</small>
                        </div>
                    ` : ''}
                    ${isPdf ? `
                        <div class="pdf-preview-container" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
                            <div id="pdfViewer-${file.id}" style="height: 400px; background: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                <div class="text-center">
                                    <div class="spinner-border text-primary mb-3" role="status">
                                        <span class="visually-hidden">Загрузка...</span>
                                    </div>
                                    <div>Загрузка PDF...</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 d-flex justify-content-center">
                            <button class="btn btn-outline-primary btn-sm" onclick="openPdfInNewTab(${file.id})">
                                <i class="bi bi-box-arrow-up-right"></i> Открыть в новой вкладке
                            </button>
                        </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}
            <div class="col-md-6">
                <h6>Информация о файле</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID:</strong></td>
                        <td>${file.id}</td>
                    </tr>
                    <tr>
                        <td><strong>Имя файла:</strong></td>
                        <td>${file.filename || file.name || 'Неизвестно'}</td>
                    </tr>
                    <tr>
                        <td><strong>Размер:</strong></td>
                        <td>${formatFileSize(file.size)}</td>
                    </tr>
                    <tr>
                        <td><strong>Тип файла:</strong></td>
                        <td>${file.mime_type || file.type || 'Неизвестно'}</td>
                    </tr>
                    <tr>
                        <td><strong>Владелец:</strong></td>
                        <td>${file.owner_email || 'Неизвестно'}</td>
                    </tr>
                    <tr>
                        <td><strong>Дата создания:</strong></td>
                        <td>${file.created_at ? new Date(file.created_at).toLocaleString('ru-RU') : 'Неизвестно'}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Дополнительная информация</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>Путь:</strong></td>
                        <td>${file.path || 'Неизвестно'}</td>
                    </tr>
                    <tr>
                        <td><strong>Статус:</strong></td>
                        <td><span class="badge bg-success">Активен</span></td>
                    </tr>
                    <tr>
                        <td><strong>Расширение:</strong></td>
                        <td>${getFileExtension(file.filename || file.name || '')}</td>
                    </tr>
                    <tr>
                        <td><strong>Тип:</strong></td>
                        <td>
                            ${isImage ? '<span class="badge bg-info">Изображение</span>' : ''}
                            ${isPdf ? '<span class="badge bg-danger">PDF документ</span>' : ''}
                            ${!isPreviewable ? '<span class="badge bg-secondary">Файл</span>' : ''}
                        </td>
                    </tr>
                    ${isPdf ? `
                    <tr>
                        <td><strong>Предпросмотр:</strong></td>
                        <td><span class="badge bg-warning">Ограниченный</span></td>
                    </tr>
                    ` : isImage ? `
                    <tr>
                        <td><strong>Предпросмотр:</strong></td>
                        <td><span class="badge bg-success">Доступен</span></td>
                    </tr>
                    ` : `
                    <tr>
                        <td><strong>Предпросмотр:</strong></td>
                        <td><span class="badge bg-secondary">Недоступен</span></td>
                    </tr>
                    `}
                </table>
            </div>
        </div>
    `;

    const modalElement = document.getElementById('viewFileModal');
    if (modalElement) {
        modalElement.setAttribute('data-file-id', file.id);

        const modalDialog = modalElement.querySelector('.modal-dialog');
        if (isPdf) {
            modalDialog.classList.remove('modal-lg');
            modalDialog.classList.add('modal-xl');
        } else {
            modalDialog.classList.remove('modal-xl');
            modalDialog.classList.add('modal-lg');
        }

        const modal = new bootstrap.Modal(modalElement);
        modal.show();

        if (isPdf) {
            loadPdfPreview(file.id);
        }
    }
}

async function loadPdfPreview(fileId) {
    try {
        const response = await fetch(`/files/download/${fileId}`, {
            method: 'GET',
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Не удалось загрузить PDF');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);

        const viewer = document.getElementById(`pdfViewer-${fileId}`);
        if (viewer) {
            viewer.innerHTML = `
                <iframe src="${url}" 
                        width="100%" 
                        height="400px" 
                        style="border: none;">
                </iframe>
            `;
        }

    } catch (error) {
        console.error('Ошибка загрузки PDF:', error);
        const viewer = document.getElementById(`pdfViewer-${fileId}`);
        if (viewer) {
            viewer.innerHTML = `
                <div class="alert alert-warning text-center">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>Не удалось загрузить PDF для предпросмотра</div>
                    <button class="btn btn-primary btn-sm mt-2" onclick="openPdfInNewTab(${fileId})">
                        <i class="bi bi-box-arrow-up-right"></i> Открыть в новой вкладке
                    </button>
                </div>
            `;
        }
    }
}

async function openPdfInNewTab(fileId) {
    try {

        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Загрузка...';
        button.disabled = true;

        const response = await fetch(`/files/download/${fileId}`, {
            method: 'GET',
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Не удалось загрузить PDF');
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);

        const newWindow = window.open(url, '_blank');

        if (!newWindow) {
            showMessage('warning', 'Всплывающие окна заблокированы. Разрешите всплывающие окна для этого сайта.');
        }

        button.innerHTML = originalContent;
        button.disabled = false;

    } catch (error) {
        console.error('Ошибка открытия PDF:', error);
        showMessage('danger', 'Не удалось открыть PDF файл');

        const button = event.target.closest('button');
        button.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Открыть в новой вкладке';
        button.disabled = false;
    }
}

function getFileExtension(filename) {
    if (!filename) return 'Неизвестно';
    const parts = filename.split('.');
    return parts.length > 1 ? '.' + parts[parts.length - 1].toUpperCase() : 'Без расширения';
}

function downloadFileFromModal() {
    const modalElement = document.getElementById('viewFileModal');
    const fileId = modalElement.getAttribute('data-file-id');
    if (fileId) {
        downloadFile(fileId);
    }
}

function deleteFileFromModal() {
    const modalElement = document.getElementById('viewFileModal');
    const fileId = modalElement.getAttribute('data-file-id');
    if (fileId) {

        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }

        deleteFile(fileId);
    }
}

function downloadFile(fileId) {

    const url = `/files/download/${fileId}`;
    window.open(url, '_blank');
}

async function deleteFile(fileId) {
    if (!confirm('Вы уверены, что хотите удалить этот файл?')) {
        return;
    }

    try {

        const response = await fetch(`/admin/files/${fileId}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            const text = await response.text();
            console.error('Delete file error response:', text);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            showMessage('success', result.message || 'Файл успешно удален');
            await loadFiles();
        } else {
            showMessage('danger', 'Ошибка при удалении файла: ' + (result.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка при удалении файла:', error);
        showMessage('danger', 'Ошибка при удалении файла');
    }
}

async function loadStats() {
    try {
        const response = await fetch('/admin/stats', {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success && data.stats) {
            updateStatsDisplay(data.stats);
        } else {
            throw new Error('Некорректные данные статистики');
        }
    } catch (error) {
        console.error('Ошибка загрузки статистики:', error);
        showStatsError();
    }
}

async function refreshSystemHealth() {
    const statusElem = document.getElementById('systemHealthStatus');
    const detailsElem = document.getElementById('systemHealthDetails');

    if (!statusElem || !detailsElem) return;

    statusElem.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Загрузка...
    `;
    detailsElem.textContent = 'Загрузка...';

    try {
        const response = await fetch('/admin/system/health', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error(`HTTP error ${response.status}`);

        const data = await response.json();

        if (!data.success) throw new Error(data.error || 'Ошибка при получении состояния системы');

        const health = data.health;

        let overallStatus = 'Здоровая';
        let statusColor = '#198754';

        if (health.status === 'unhealthy' || health.status === 'error') {
            overallStatus = 'Проблемы';
            statusColor = '#dc3545';
        } else if (health.status === 'warning') {
            overallStatus = 'Предупреждение';
            statusColor = '#ffc107';
        }

        statusElem.innerHTML = '';

        const square = document.createElement('span');
        square.style.display = 'inline-block';
        square.style.width = '12px';
        square.style.height = '12px';
        square.style.marginRight = '8px';
        square.style.backgroundColor = statusColor;
        square.style.borderRadius = '2px';
        square.style.verticalAlign = 'middle';

        statusElem.appendChild(square);
        statusElem.appendChild(document.createTextNode(overallStatus));

        let detailsHtml = '';
        if (health.checks) {
            for (const [key, check] of Object.entries(health.checks)) {
                detailsHtml += `<strong>${key}:</strong> ${check.status.toUpperCase()} - ${check.message}<br>`;
                if (check.free_space_formatted) {
                    detailsHtml += `Свободное место: ${check.free_space_formatted}<br>`;
                }
                if (check.usage) {
                    detailsHtml += `Использование памяти: ${check.usage} / ${check.limit}<br>`;
                }
                detailsHtml += '<br>';
            }
        } else if (health.message) {
            detailsHtml = health.message.replace(/\n/g, '<br>');
        }

        detailsElem.innerHTML = detailsHtml.trim();

    } catch (error) {
        statusElem.textContent = 'Ошибка';
        statusElem.style.color = '#dc3545';
        detailsElem.textContent = error.message;
        console.error('Ошибка загрузки состояния системы:', error);
    }
}

window.addEventListener('hashchange', () => {
    if (window.location.hash === '#dashboard' || window.location.hash === '#system') {
        refreshSystemHealth();
    }
});

async function cleanupFiles() {
    if (!confirm('Вы уверены, что хотите очистить все файлы? Это действие необратимо.')) {
        return;
    }

    try {
        const response = await fetch('/admin/files/clear', {
            method: 'DELETE',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            const text = await response.text();
            console.error('Server response:', text);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            showMessage('success', 'Очистка файлов завершена');
            await loadFiles();
        } else {
            showMessage('danger', 'Ошибка при очистке файлов: ' + (data.error || 'Неизвестная ошибка'));
        }
    } catch (error) {
        console.error('Ошибка очистки файлов:', error);
        showMessage('danger', 'Ошибка при очистке файлов');
    }
}
