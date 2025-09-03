let currentView = localStorage.getItem('currentView') || 'list';
let currentDirectoryId = localStorage.getItem("currentDirectoryId") || "root";
let currentUserId = null;
let currentPreviewVideo = null;
const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;

function getFileIcon(mimeType, size = '24px') {
    const isImage = mimeType && mimeType.startsWith('image/');
    const isVideo = mimeType && mimeType.startsWith('video/');

    if (isImage) {
        return `<i class="bi bi-file-image text-primary me-2" style="font-size: ${size};"></i>`;
    } else if (isVideo) {
        return `<i class="bi bi-play-circle-fill text-primary me-2" style="font-size: ${size};"></i>`;
    } else if (mimeType === 'application/pdf') {
        return `<i class="bi bi-file-pdf text-danger me-2" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
        mimeType === 'application/msword'
    ) {
        return `<i class="bi bi-file-word text-primary me-2" style="font-size: ${size};"></i>`;
    } else if (mimeType && mimeType.startsWith('audio/')) {
        return `<i class="bi bi-file-music text-warning me-2" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
        mimeType === 'application/vnd.ms-excel'
    ) {
        return `<i class="bi bi-file-excel text-success me-2" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ||
        mimeType === 'application/vnd.ms-powerpoint'
    ) {
        return `<i class="bi bi-file-ppt text-warning me-2" style="font-size: ${size};"></i>`;
    } else if (mimeType && mimeType.startsWith('text/')) {
        return `<i class="bi bi-file-text text-info me-2" style="font-size: ${size};"></i>`;
    } else if (mimeType && (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z'))) {
        return `<i class="bi bi-file-zip text-secondary me-2" style="font-size: ${size};"></i>`;
    } else {
        return `<i class="bi bi-file-earmark text-muted me-2" style="font-size: ${size};"></i>`;
    }
}

function getFileIconForTiles(mimeType, size = '48px') {
    const isImage = mimeType && mimeType.startsWith('image/');
    const isVideo = mimeType && mimeType.startsWith('video/');

    if (isImage) {
        return `<i class="bi bi-file-image text-primary" style="font-size: ${size};"></i>`;
    } else if (isVideo) {
        return `<i class="bi bi-play-circle-fill text-primary" style="font-size: ${size};"></i>`;
    } else if (mimeType === 'application/pdf') {
        return `<i class="bi bi-file-pdf text-danger" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
        mimeType === 'application/msword'
    ) {
        return `<i class="bi bi-file-word text-primary" style="font-size: ${size};"></i>`;
    } else if (mimeType && mimeType.startsWith('audio/')) {
        return `<i class="bi bi-file-music text-warning" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
        mimeType === 'application/vnd.ms-excel'
    ) {
        return `<i class="bi bi-file-excel text-success" style="font-size: ${size};"></i>`;
    } else if (
        mimeType === 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ||
        mimeType === 'application/vnd.ms-powerpoint'
    ) {
        return `<i class="bi bi-file-ppt text-warning" style="font-size: ${size};"></i>`;
    } else if (mimeType && mimeType.startsWith('text/')) {
        return `<i class="bi bi-file-text text-info" style="font-size: ${size};"></i>`;
    } else if (mimeType && (mimeType.includes('zip') || mimeType.includes('rar') || mimeType.includes('7z'))) {
        return `<i class="bi bi-file-zip text-secondary" style="font-size: ${size};"></i>`;
    } else {
        return `<i class="bi bi-file-earmark text-muted" style="font-size: ${size};"></i>`;
    }
}

async function refreshLists() {
    try {

        const filesList = document.getElementById('filesList');
        const foldersList = document.getElementById('foldersList');

        if (filesList) filesList.innerHTML = '<div class="text-center p-3"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Загрузка...</div>';
        if (foldersList) foldersList.innerHTML = '<li class="list-group-item text-center"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Загрузка...</li>';

        await loadFolders();

        showMessage('Списки обновлены', 'success');
    } catch (error) {
        console.error('Ошибка при обновлении списков:', error);
        showMessage('Ошибка при обновлении списков', 'danger');
    }
}

function openFolder(folderId) {
    currentDirectoryId = folderId;
    localStorage.setItem("currentDirectoryId", currentDirectoryId);
    loadFolders();
}

function addRefreshButton() {
    const container = document.getElementById('refreshBtnContainer');
    if (!container || container.querySelector('#refreshListsBtn')) {
        return;
    }

    const refreshBtn = document.createElement('button');
    refreshBtn.id = 'refreshListsBtn';
    refreshBtn.className = 'btn btn-outline-secondary';
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i><span class="d-none d-sm-inline ms-2">Обновить списки</span>';
    refreshBtn.onclick = refreshLists;
    refreshBtn.title = 'Обновить списки файлов и папок';

    container.appendChild(refreshBtn);
}

function handleDragEnter(event) {
    event.preventDefault();
    if (event.currentTarget.dataset.folderId ||
        (event.currentTarget.dataset.id && event.currentTarget.dataset.type === 'directory')) {
        event.currentTarget.classList.add('drag-over');
    }
}

function handleDragOver(event) {
    event.preventDefault();
    if (event.currentTarget.dataset.folderId) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function handleDragLeave(event) {
    if (event.currentTarget.dataset.folderId ||
        (event.currentTarget.dataset.id && event.currentTarget.dataset.type === 'directory')) {
        event.currentTarget.classList.remove('drag-over');
    }
}

function renderFoldersList(folders, currentDirectory) {
    const foldersList = document.getElementById('foldersList');
    foldersList.innerHTML = '';
    foldersList.className = 'list-group mb-3';

    let hasBack = false;
    if (currentDirectory) {
        const parentId = currentDirectory.real_parent_id ?? currentDirectory.parent_id;

        if (parentId !== null && parentId !== undefined) {
            const backLi = document.createElement('li');
            backLi.className = 'list-group-item list-group-item-action d-flex align-items-center';
            backLi.style.cursor = 'pointer';
            backLi.innerHTML = `<i class="bi bi-arrow-left-circle me-2"></i> Назад к "${currentDirectory.directory_name || 'предыдущей папке'}"`;
            backLi.onclick = () => goBack();
            foldersList.appendChild(backLi);
            hasBack = true;
        }
    }

    if (folders.length > 0) {
        folders.forEach(folder => {
            const li = document.createElement('li');
            li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';

            li.setAttribute('draggable', 'true');
            li.dataset.folderId = folder.id;
            li.dataset.type = 'directory';
            li.dataset.id = folder.id;

            const isOwner = Number(folder.user_id) === Number(currentUserId);

            let sharedInfo = '';
            if (folder.is_shared && !folder.is_shared_by_owner) {
                sharedInfo = `<i class="bi bi-people-fill text-info ms-2" title="Общая папка"></i>`;
            } else if (folder.is_shared_by_owner && isOwner) {
                sharedInfo = `<i class="bi bi-send-fill text-primary ms-2" title="Вы поделились"></i>`;
            }

            const actionLinkHtml = isOwner
                ? `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFolder(${folder.id})"><i class="bi bi-trash me-2"></i>Удалить</a></li>`
                : `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFolder(${folder.id})"><i class="bi bi-box-arrow-left me-2"></i>Отказаться от доступа</a></li>`;

            li.innerHTML = `
                <div class="d-flex align-items-center text-truncate" style="cursor:pointer;" onclick="openFolder(${folder.id})">
                    <i class="bi bi-folder-fill text-warning me-3 fs-5"></i>
                    <span class="fw-500 text-truncate">${folder.name}</span>
                    ${sharedInfo}
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="renameFolder(${folder.id}, '${folder.name}')"><i class="bi bi-pencil-square me-2"></i>Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFolder(${folder.id}, '${folder.name}')"><i class="bi bi-share me-2"></i>Поделиться</a></li>
                        <li><a class="dropdown-item" href="${getApiBase()}/directories/download/${folder.id}"><i class="bi bi-download me-2"></i>Скачать</a></li>
                        ${actionLinkHtml}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="createSubfolderPrompt(${folder.id})"><i class="bi bi-folder-plus me-2"></i>Создать подпапку</a></li>
                    </ul>
                </div>
            `;

            li.addEventListener('dragstart', handleDragStart);
            li.addEventListener('dragend', handleDragEnd);
            li.addEventListener('dragenter', handleDragEnter);
            li.addEventListener('dragover', handleDragOver);
            li.addEventListener('dragleave', handleDragLeave);
            li.addEventListener('drop', handleDrop);
            foldersList.appendChild(li);
        });
    } else if (!hasBack) {
        foldersList.innerHTML += '<li class="list-group-item text-muted">Нет папок</li>';
    }
}

function renderFoldersGrid(folders, currentDirectory) {
    const foldersList = document.getElementById('foldersList');
    foldersList.innerHTML = '';
    foldersList.className = 'files-list grid-view';

    let hasBack = false;
    if (currentDirectory) {
        const parentId = currentDirectory.real_parent_id ?? currentDirectory.parent_id;

        if (parentId !== null && parentId !== undefined) {
            const backCard = document.createElement('div');
            backCard.className = 'grid-item';
            backCard.onclick = () => goBack();
            backCard.innerHTML = `
                <div class="grid-item-icon">
                    <i class="bi bi-arrow-left-circle-fill text-secondary"></i>
                </div>
                <div class="grid-item-name">Назад</div>
                <div class="grid-item-info text-truncate" title="${currentDirectory.directory_name || ''}">${currentDirectory.directory_name || ''}</div>
            `;
            foldersList.appendChild(backCard);
            hasBack = true;
        }
    }

    if (folders && folders.length > 0) {
        folders.forEach(folder => {
            const card = document.createElement('div');
            card.className = 'grid-item';
            card.setAttribute('draggable', 'true');
            card.dataset.folderId = folder.id;
            card.dataset.type = 'directory';
            card.dataset.id = folder.id;

            const isOwner = Number(folder.user_id) === Number(currentUserId);
            let sharedBadge = '';
            if (folder.is_shared && !folder.is_shared_by_owner) {
                const sharedByText = folder.shared_by ? ` (Доступ предоставил: ${folder.shared_by})` : '';
                sharedBadge = `<span class="badge bg-info position-absolute top-0 start-0 m-1" title="Общая папка${sharedByText}"><i class="bi bi-people-fill"></i></span>`;
            } else if (folder.is_shared_by_owner && isOwner) {
                sharedBadge = `<span class="badge bg-primary position-absolute top-0 start-0 m-1" title="Вы поделились"><i class="bi bi-send-fill"></i></span>`;
            }

            const actionLinkHtml = isOwner
                ? `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFolder(${folder.id})"><i class="bi bi-trash me-2"></i>Удалить</a></li>`
                : `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFolder(${folder.id})"><i class="bi bi-box-arrow-left me-2"></i>Отказаться от доступа</a></li>`;

            card.innerHTML = `
                ${sharedBadge}
                <div class="grid-item-icon" onclick="openFolder(${folder.id})">
                    <i class="bi bi-folder-fill text-warning"></i>
                </div>
                <div class="grid-item-name" title="${folder.name}" onclick="openFolder(${folder.id})">${folder.name}</div>
                <div class="dropdown position-absolute top-0 end-0 m-1">
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="renameFolder(${folder.id}, '${folder.name}')"><i class="bi bi-pencil-square me-2"></i>Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFolder(${folder.id}, '${folder.name}')"><i class="bi bi-share me-2"></i>Поделиться</a></li>
                        <li><a class="dropdown-item" href="${getApiBase()}/directories/download/${folder.id}"><i class="bi bi-download me-2"></i>Скачать</a></li>
                        ${actionLinkHtml}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="createSubfolderPrompt(${folder.id})"><i class="bi bi-folder-plus me-2"></i>Создать подпапку</a></li>
                    </ul>
                </div>
            `;

            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
            card.addEventListener('dragenter', handleDragEnter);
            card.addEventListener('dragover', handleDragOver);
            card.addEventListener('dragleave', handleDragLeave);
            card.addEventListener('drop', handleDrop);
            foldersList.appendChild(card);
        });
    } else if (!hasBack) {
        const noFolders = document.createElement('div');
        noFolders.className = 'text-center text-muted p-4 w-100';
        noFolders.textContent = 'Нет папок';
        foldersList.appendChild(noFolders);
    }
}

async function renderPdfPreview(fileId, canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    try {
        const url = `${getApiBase()}/files/download/${fileId}?inline=1`;
        const loadingTask = pdfjsLib.getDocument(url);
        const pdf = await loadingTask.promise;
        const page = await pdf.getPage(1);

        const viewport = page.getViewport({ scale: 1 });
        const scale = Math.min(canvas.width / viewport.width, canvas.height / viewport.height);
        const scaledViewport = page.getViewport({ scale: scale });

        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;

        const renderContext = {
            canvasContext: ctx,
            viewport: scaledViewport
        };
        await page.render(renderContext).promise;
    } catch (error) {
        console.error(`Error rendering PDF preview for file ${fileId}:`, error);
        ctx.fillStyle = '#6c757d';
        ctx.textAlign = 'center';
        ctx.font = '12px sans-serif';
        ctx.fillText('Preview Error', canvas.width / 2, canvas.height / 2);
    }
}

function handleDragStart(event) {
    const element = event.currentTarget;
    const itemType = element.dataset.type;
    const itemId = element.dataset.id || element.dataset.fileId || element.dataset.folderId;

    if (!itemId || !itemType) {
        console.error('Не удалось получить данные элемента для перетаскивания');
        event.preventDefault();
        return;
    }

    try {
        event.dataTransfer.clearData();
        event.dataTransfer.setData('text/plain', JSON.stringify({ type: itemType, id: itemId }));
    } catch (e) {
        console.error('Ошибка при установке данных перетаскивания:', e);
        event.preventDefault();
        return;
    }
    event.dataTransfer.effectAllowed = 'move';

    element.style.opacity = '0.5';
}

async function handleDrop(event) {
    event.preventDefault();

    let draggedType = null;
    let draggedId = null;

    const plainData = event.dataTransfer.getData('text/plain');
    if (plainData) {
        try {
            const parsed = JSON.parse(plainData);
            draggedType = parsed.type;
            draggedId = parsed.id;
        } catch (e) {
            console.error('Ошибка при разборе данных перетаскивания:', e);
        }
    }

    if (!draggedType || !draggedId) {
        draggedType = draggedType || event.dataTransfer.getData('text/type') || event.dataTransfer.getData('type') || event.dataTransfer.getData('Text');
        draggedId = draggedId || event.dataTransfer.getData('text/id') || event.dataTransfer.getData('id') || event.dataTransfer.getData('Text');
    }

    const targetFolderId = event.currentTarget.dataset.folderId || event.currentTarget.dataset.id;

    if (!draggedId || !targetFolderId || !draggedType) {
        console.error('Недостаточно данных для перемещения:', { draggedType, draggedId, targetFolderId });
        showMessage('Недостаточно данных для перемещения', 'danger');
        return;
    }

    if (draggedType === 'directory' && draggedId === targetFolderId) {
        showMessage('Нельзя переместить папку саму в себя', 'danger');
        return;
    }

    try {
        let url, body;

        if (draggedType === 'directory') {
            url = `${getApiBase()}/directories/move`;
            body = {
                directory_id: parseInt(draggedId),
                target_parent_id: targetFolderId === 'root' ? 'root' : parseInt(targetFolderId)
            };
        } else if (draggedType === 'file') {
            url = `${getApiBase()}/files/move`;
            body = {
                file_id: parseInt(draggedId),
                directory_id: targetFolderId === 'root' ? 'root' : parseInt(targetFolderId)
            };
        } else {
            throw new Error('Неизвестный тип элемента: ' + draggedType);
        }

        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(body),
            credentials: 'include'
        });

        if (!response.ok) {
            const errorText = await response.text();
            try {
                const errorData = JSON.parse(errorText);
                console.error('Server error:', errorData);

                if (errorData.error && errorData.error.includes('Нет прав доступа к папке')) {
                    showMessage('Ошибка при обработке перемещения: Нет прав владельца', 'danger');
                } else {
                    showMessage(errorData.error || `Ошибка сервера: ${response.status}`, 'danger');
                }
                return;
            } catch {
                console.error('Could not parse error response:', errorText);
                showMessage(`Ошибка сервера: ${response.status}`, 'danger');
                return;
            }
        }

        const result = await response.json();

        if (result.success) {
            showMessage(result.message || 'Элемент успешно перемещен', 'success');
            await loadFolders();
        } else {
            const errorMsg = result.error || 'Ошибка при перемещении';
            if (errorMsg.toLowerCase().includes('прав')) {
                showMessage('Ошибка при обработке перемещения: Нет прав владельца', 'danger');
            } else {
                showMessage(errorMsg, 'danger');
            }
        }

    } catch (error) {
        console.error('Ошибка при обработке перемещения:', error);
        showMessage('Ошибка при перемещении: ' + error.message, 'danger');
    }
}

function handleDragEnd(event) {
    event.currentTarget.style.opacity = '1';
    event.currentTarget.classList.remove('dragging');
}

async function isDescendantFolder(sourceId, targetId) {
    try {
        const res = await fetch(`${getApiBase()}/directories/get/${targetId}`, {
            credentials: 'include'
        });

        if (!res.ok) return false;

        const data = await res.json();
        if (!data.success || !data.directory) return false;

        const directory = data.directory;

        if (directory.parent_id === sourceId) return true;

        if (directory.parent_id && directory.parent_id !== directory.id) {
            return await isDescendantFolder(sourceId, directory.parent_id);
        }

        return false;
    } catch (error) {
        console.error('Ошибка при проверке иерархии папок:', error);
        return false;
    }
}

function addDragAndDropHandlersToFolders() {
    const folderElements = document.querySelectorAll('[data-folder-id], [data-id][data-type="directory"]');
    folderElements.forEach(folderEl => {
        folderEl.addEventListener('dragenter', handleDragEnter);
        folderEl.addEventListener('dragover', handleDragOver);
        folderEl.addEventListener('dragleave', handleDragLeave);
        folderEl.addEventListener('drop', handleDrop);
    });
}

function updateSelectedFilesUI() {
    const fileInput = document.getElementById('fileInput');
    const folderInput = document.getElementById('folderInput');
    const allFiles = [...(fileInput.files || []), ...(folderInput.files || [])];

    const selectedFilesElement = document.getElementById("selectedFiles");
    const clearBtn = document.getElementById('clearSelectionBtn');

    if (!selectedFilesElement || !clearBtn) return;

    if (allFiles.length > 0) {
        let names = allFiles.slice(0, 3).map(f => f.name);
        let fileWord = 'файлов';
        const count = allFiles.length;
        if (count === 1) fileWord = 'файл';
        else if (count > 1 && count < 5) fileWord = 'файла';

        let message = `Выбрано: ${count} ${fileWord}`;
        if (count > 3) {
            message += ` (${names.join(', ')}...)`;
        } else {
            message += ` (${names.join(', ')})`;
        }
        selectedFilesElement.textContent = message;
        selectedFilesElement.title = allFiles.map(f => f.name).join('\n');
        clearBtn.disabled = false;
    } else {
        selectedFilesElement.textContent = "Файлы не выбраны";
        selectedFilesElement.removeAttribute('title');
        clearBtn.disabled = true;
    }
}

function getApiBase() {
    const meta = document.querySelector('meta[name="api-base"]');
    return meta ? meta.getAttribute('content') || '' : '';
}


async function loadUserInfo() {
    try {
        const res = await fetch(`${getApiBase()}/users/current`, {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await res.json();
        const greeting = document.getElementById('userGreeting');

        if (data.success && data.user) {
            currentUserId = data.user.id;
            greeting.textContent = `Добро пожаловать, ${data.user.first_name} ${data.user.last_name}`;
        } else {
            console.error('Failed to load user info:', data);
            greeting.textContent = 'Добро пожаловать!';
        }
    } catch (error) {
        console.error('Ошибка при загрузке информации о пользователе:', error);
        const greeting = document.getElementById('userGreeting');
        if (greeting) greeting.textContent = 'Добро пожаловать!';
    }
}

async function goBack() {
    try {
        if (currentDirectoryId === 'root' || !currentDirectoryId) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        const res = await fetch(`${getApiBase()}/directories/get/${currentDirectoryId}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        if (!res.ok) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        const data = await res.json();

        if (!data.success || !data.directory) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        const directory = data.directory;

        if (directory.is_shared_root) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        const parentId = directory.real_parent_id ?? directory.parent_id;

        if (!parentId) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        currentDirectoryId = parentId;
        localStorage.setItem("currentDirectoryId", currentDirectoryId);
        await loadFolders();

    } catch (error) {
        showMessage('Произошла ошибка при переходе назад', 'danger');
    }
}

async function showFileInfo(fileId) {
    try {
        const res = await fetch(`${getApiBase()}/files/info/${fileId}`, {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`Ошибка HTTP: ${res.status}`);
        }

        const data = await res.json();

        if (!data.success || !data.file) {
            alert(data.error || 'Не удалось получить информацию о файле');
            return;
        }

        const file = data.file.file || data.file;

        const modalEl = document.getElementById('filePreviewModal');
        const modalTitle = modalEl.querySelector('.modal-title');
        const fileInfoDiv = modalEl.querySelector('#fileInfo');
        const filePreviewDiv = modalEl.querySelector('#filePreview');
        const downloadBtn = document.getElementById('downloadBtn');
        const shareBtn = document.getElementById('shareInModalBtn');
        const deleteBtn = document.getElementById('deleteInModalBtn');

        modalTitle.textContent = file.name || file.filename || '';

        fileInfoDiv.innerHTML = `
            <p><strong>Имя файла:</strong> ${file.name || file.filename || 'неизвестно'}</p>
            <p><strong>Тип файла:</strong> ${file.mime_type || 'неизвестно'}</p>
            <p><strong>Размер:</strong> ${file.file_size_formatted || file.file_size || file.size || 'неизвестно'}</p>
        `;

        if (file.mime_type && file.mime_type.startsWith('image/')) {
            filePreviewDiv.innerHTML = `<img src="${getApiBase()}/files/download/${file.id}?inline=1" alt="${file.name}" style="max-width: 100%;">`;
        } else if (file.mime_type === 'application/pdf') {
            filePreviewDiv.innerHTML = `<iframe src="${getApiBase()}/files/preview/${file.id}" width="100%" height="600px" style="border:none"></iframe>`;
        } else if (file.mime_type && file.mime_type.startsWith('video/')) {
            filePreviewDiv.innerHTML = `
                <video id="modalVideoElement" controls muted playsinline style="width: 100%; height: auto; border-radius: var(--radius); background: black;">
                    <source src="${getApiBase()}/files/preview/${file.id}" type="${file.mime_type}">
                </video>
            `;

            const video = document.getElementById('modalVideoElement');
            video.muted = true;
            video.play().catch(e => {
                console.warn("Modal video autoplay was prevented:", e.name);
            });

            window.currentPreviewVideo = video;
        } else if (
            file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
            file.mime_type === 'application/msword'
        ) {
            filePreviewDiv.innerHTML = `
                <div class="alert alert-warning mb-2">
                    Предпросмотр docx доступен только для публичных файлов.<br>
                    <a href="${getApiBase()}/files/download/${file.id}" class="btn btn-primary mt-2" download>Скачать файл</a>
                </div>
            `;
        } else {
            filePreviewDiv.innerHTML = '<div class="alert alert-light text-center">Нет предпросмотра для этого типа файла</div>';
        }

        downloadBtn.href = `${getApiBase()}/files/download/${file.id}`;
        downloadBtn.download = file.name || file.filename || '';

        shareBtn.dataset.fileId = file.id;
        shareBtn.dataset.fileName = file.name || file.filename || '';

        if (deleteBtn) {
            deleteBtn.dataset.fileId = file.id;
        }

        const isOwner = Number(file.user_id) === Number(currentUserId);

        if (isOwner) {
            shareBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else if (!isOwner && Number(file.is_shared) === 1) {
            shareBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'none';
        } else {
            shareBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
        }

        const unshareContainer = document.getElementById('unshareBtnContainer');
        if (unshareContainer) {
            if (!isOwner && Number(file.is_shared) === 1) {
                unshareContainer.innerHTML = `<button id="unshareInModalBtn" class="btn btn-outline-warning" type="button"><i class="bi bi-box-arrow-left me-1"></i>Отказаться от доступа</button>`;
                document.getElementById('unshareInModalBtn').onclick = () => {
                    window.filePreviewModalInstance.hide();
                    unshareFile(file.id);
                };
                unshareContainer.style.display = 'inline-block';
            } else {
                unshareContainer.innerHTML = '';
                unshareContainer.style.display = 'none';
            }
        }

        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        window.filePreviewModalInstance.show();

    } catch (error) {
        console.error('Ошибка при получении информации о файле:', error);
        alert('Произошла ошибка при получении информации о файле');
    }
}


document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', () => {
    const modalEl = document.getElementById('filePreviewModal');
    const video = modalEl.querySelector('video');

    if (video) {
        video.pause();
        video.removeAttribute('src');
        video.load();
        video.remove();
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

    const filePreviewDiv = modalEl.querySelector('#filePreview');
    if (filePreviewDiv) {
        filePreviewDiv.innerHTML = '';
    }

    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    const safeElement = document.getElementById('userGreeting') || document.body;
    if (safeElement) {
        safeElement.setAttribute('tabindex', '-1');
        safeElement.focus();
        safeElement.removeAttribute('tabindex');
    }
});


document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', () => {
    const modalEl = document.getElementById('filePreviewModal');
    const video = modalEl.querySelector('video');

    if (video) {

        video.pause();

        while (video.firstChild) {
            video.removeChild(video.firstChild);
        }

        video.removeAttribute('src');
        video.load();

        video.remove();
    }

    if (window.currentPreviewVideo) {
        try {
            window.currentPreviewVideo.pause();
            while (window.currentPreviewVideo.firstChild) {
                window.currentPreviewVideo.removeChild(window.currentPreviewVideo.firstChild);
            }
            window.currentPreviewVideo.removeAttribute('src');
            window.currentPreviewVideo.load();
            if (window.currentPreviewVideo.parentNode) {
                window.currentPreviewVideo.parentNode.removeChild(window.currentPreviewVideo);
            }
        } catch (_) { }
        window.currentPreviewVideo = null;
    }

    const filePreviewDiv = modalEl.querySelector('#filePreview');
    if (filePreviewDiv) {
        filePreviewDiv.innerHTML = '';
    }

    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    const safeElement = document.getElementById('userGreeting') || document.body;
    if (safeElement) {
        safeElement.setAttribute('tabindex', '-1');
        safeElement.focus();
        safeElement.removeAttribute('tabindex');
    }
});

async function createFolder() {
    const folderNameInput = document.getElementById('newFolderName');
    const folderName = folderNameInput.value.trim();

    if (!folderName) {
        showMessage('Имя папки не может быть пустым', 'warning');
        return;
    }

    try {
        const res = await fetch(`${getApiBase()}/directories/add`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: folderName,
                parent_id: currentDirectoryId === 'root' ? 'root' : parseInt(currentDirectoryId)
            })
        });

        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`Ошибка сервера: ${res.status} ${errorText}`);
        }

        const data = await res.json();
        if (data.success) {
            showMessage('Папка успешно создана', 'success');
            folderNameInput.value = '';
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при создании папки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при создании папки:', error);
        showMessage('Произошла ошибка при создании папки: ' + error.message, 'danger');
    }
}

async function loadFolders() {
    try {
        let directoryIdToLoad = currentDirectoryId;

        if (!directoryIdToLoad || directoryIdToLoad === 'root') {
            directoryIdToLoad = 'root';
        }

        const timestamp = Date.now();

        const res = await fetch(`${getApiBase()}/directories/get/${directoryIdToLoad}?_t=${timestamp}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });

        if (!res.ok) {
            directoryIdToLoad = 'root';
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
        }

        const data = await res.json();

        if (data.directory && data.directory.is_shared && Number(data.directory.user_id) !== Number(currentUserId)) {

            if (!sharedRootId || directoryIdToLoad === 'root') {
                sharedRootId = data.directory.shared_root_id || data.directory.id;
            }
        }

        if (!data.directory || directoryIdToLoad === 'root' || Number(data.directory.user_id) === Number(currentUserId)) {
            sharedRootId = null;
        }

        const allDirectories = [];
        if (Array.isArray(data.subdirectories)) allDirectories.push(...data.subdirectories);
        if (Array.isArray(data.shared_directories)) allDirectories.push(...data.shared_directories);

        if (currentView === 'list') {
            renderFoldersList(allDirectories, data.directory);
        } else {
            renderFoldersGrid(allDirectories, data.directory);
        }

        addDragAndDropHandlersToFolders();

        await loadFiles();

    } catch (error) {
        console.log('loadFolders called');
        console.error('Ошибка при загрузке папок:', error);
        showMessage('Ошибка при загрузке папок', 'danger');
    }
}


document.addEventListener('DOMContentLoaded', async () => {
    try {
        await loadUserInfo();
        await loadFolders();
        addRefreshButton();

        const clearBtn = document.getElementById('clearSelectionBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearFileSelection);
        }

        updateSelectedFilesUI();
    } catch (error) {
        console.error('Ошибка при инициализации приложения:', error);
        showMessage('Ошибка при загрузке приложения', 'danger');

    }

    document.getElementById("fileInput").addEventListener("change", handleFileSelection);
    document.getElementById("folderInput").addEventListener("change", handleFileSelection);

    setupDragAndDrop();

    const fileInput = document.getElementById("fileInput");
    const folderInput = document.getElementById("folderInput");
    const uploadBtn = document.getElementById("uploadFilesBtn");
    if (uploadBtn) {
        uploadBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await uploadSelectedFiles();
            } catch (err) {
                console.error('Ошибка при загрузке файлов:', err);
                showMessage('Ошибка при загрузке файлов: ' + (err.message || err), 'danger');
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                uploadBtn.click();
            }
        });
    }

    if (folderInput) {
        folderInput.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                uploadBtn.click();
            }
        });
    }


    const selectedFilesSpan = document.getElementById("selectedFiles");
    if (selectedFilesSpan) {
        selectedFilesSpan.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                e.preventDefault();
                uploadBtn.click();
            }
        });
    }

    document.getElementById("createFolderBtn").onclick = (e) => {
        e.preventDefault();
        createFolder();
    };


    const newFolderNameInput = document.getElementById('newFolderName');
    if (newFolderNameInput) {
        newFolderNameInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('createFolderBtn').click();
            }
        });
    }

    document.getElementById('logoutBtn').onclick = async () => {
        await fetch(`${getApiBase()}/logout`, { method: 'POST', credentials: 'include' });
        localStorage.removeItem("currentDirectoryId");
        window.location.href = '/login.html';
    };

    const modalEl = document.getElementById('filePreviewModal');
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    window.filePreviewModalInstance = new bootstrap.Modal(modalEl, { backdrop: true, focus: false });
    modalEl.addEventListener('show.bs.modal', () => {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    });


    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            try { window.filePreviewModalInstance?.hide(); } catch (_) { }
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        }
    });

    const shareBtn = document.getElementById('shareInModalBtn');
    if (shareBtn) {
        shareBtn.onclick = function () {
            const fileId = this.dataset.fileId;
            const fileName = this.dataset.fileName;
            if (fileId && fileName) {
                shareFile(fileId, fileName);
            } else {
                console.error('Не указан fileId или fileName для shareFile');
            }
        };
    }

    modalEl.addEventListener('hide.bs.modal', () => {
        const focused = modalEl.querySelector(':focus');
        if (focused) {
            focused.blur();
        }
        const safeElement = document.getElementById('userGreeting') || document.body;
        if (safeElement) {
            safeElement.setAttribute('tabindex', '-1');
            safeElement.focus();
            safeElement.removeAttribute('tabindex');
        }
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        const modalTitle = modalEl.querySelector('.modal-title');
        if (modalTitle) {
            modalTitle.setAttribute('tabindex', '-1');
            modalTitle.focus();
            modalTitle.removeAttribute('tabindex');
        }
    });

    document.addEventListener('show.bs.dropdown', event => {
        const parentItem = event.target.closest('.grid-item, .list-group-item');
        if (parentItem) {
            parentItem.style.zIndex = '10';
        }
    });

    document.addEventListener('hide.bs.dropdown', event => {
        const parentItem = event.target.closest('.grid-item, .list-group-item');
        if (parentItem) {
            parentItem.style.zIndex = 'auto';
        }
    });
});

document.getElementById('viewListBtn').onclick = () => {
    currentView = 'list';
    document.getElementById('viewListBtn').classList.add('active');
    document.getElementById('viewGridBtn').classList.remove('active');
    loadFolders();
};

document.getElementById('viewGridBtn').onclick = () => {
    currentView = 'grid';
    document.getElementById('viewGridBtn').classList.add('active');
    document.getElementById('viewListBtn').classList.remove('active');
    loadFolders();
};

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function setupDragAndDrop() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const folderInput = document.getElementById('folderInput');
    const uploadBtn = document.getElementById('uploadFilesBtn');

    if (!dropZone || !fileInput || !folderInput || !uploadBtn) {
        console.error('Один из элементов для Drag & Drop не найден.');
        return;
    }

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer?.files;
        if (!files || files.length === 0) return;

        const hasFolders = Array.from(files).some(f => f.webkitRelativePath && f.webkitRelativePath.includes('/'));

        if (hasFolders) {
            showMessage('Загрузка папок через Drag-and-Drop не поддерживается. Пожалуйста, используйте кнопку "Выбрать папку".', 'warning');
            return;
        }

        const dataTransfer = new DataTransfer();
        Array.from(files).forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;

        updateSelectedFilesUI();

        setTimeout(() => {
            uploadSelectedFiles().catch(err => {
                console.error('Автозагрузка: ошибка', err);
                showMessage('Ошибка при автозагрузке: ' + (err.message || err), 'danger');
            });
        }, 0);
    });
}

function handleFileSelection(event) {
    updateSelectedFilesUI();
}

function clearFileSelection() {
    document.getElementById('fileInput').value = '';
    document.getElementById('folderInput').value = '';
    updateSelectedFilesUI();
}

function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) {
        alert(message);
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    alertDiv.style.opacity = '0';
    alertDiv.style.transition = 'opacity 0.3s ease-in-out';
    messageContainer.appendChild(alertDiv);

    requestAnimationFrame(() => {
        alertDiv.style.opacity = '1';
    });

    setTimeout(() => {
        alertDiv.style.opacity = '0';

        setTimeout(() => {
            if (alertDiv.parentNode) {
                messageContainer.removeChild(alertDiv);
            }
        }, 300);
    }, 3000);
}

async function loadFiles() {
    try {
        const timestamp = Date.now();

        const res = await fetch(`${getApiBase()}/files/list?directory_id=${currentDirectoryId}&_t=${timestamp}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при загрузке файлов:', res.status, errorText);
            showMessage('Ошибка при загрузке файлов', 'danger');
            return;
        }

        const data = await res.json();

        if (currentView === 'list') {
            renderFilesList(data.files || []);
        } else {
            renderFilesGrid(data.files || []);
        }
    } catch (error) {
        console.error('Ошибка при загрузке файлов:', error);
        showMessage('Ошибка при загрузке файлов', 'danger');
    }
}

function renderFilesList(files) {
    const filesList = document.getElementById('filesList');
    filesList.innerHTML = '';
    filesList.className = 'list-group list-group-flush';

    if (files && files.length > 0) {
        files.forEach(file => {
            const li = document.createElement('a');
            li.href = "#";
            li.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            li.dataset.type = 'file';
            li.dataset.id = file.id;

            const isOwner = Number(file.user_id) === Number(currentUserId);
            const fileIcon = getFileIcon(file.mime_type, '24px');

            let sharedInfo = '';
            if (isOwner && file.is_shared_by_owner) {
                sharedInfo = `<i class="bi bi-send-fill text-primary ms-2" title="Вы поделились"></i>`;
            } else if (!isOwner && file.is_shared) {
                const sharedByText = file.shared_by ? ` (Доступ предоставил: ${file.shared_by})` : '';
                sharedInfo = `<i class="bi bi-people-fill text-info ms-2" title="Общий файл${sharedByText}"></i>`;
            }

            const fileSize = file.file_size_formatted ? `<small class="text-muted ms-auto me-3">${file.file_size_formatted}</small>` : '';

            let deleteLinkHtml = '';
            if (isOwner) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(${file.id})"><i class="bi bi-trash me-2"></i>Удалить</a></li>`;
            } else if (file.is_shared) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFile(${file.id})"><i class="bi bi-box-arrow-left me-2"></i>Отказаться от доступа</a></li>`;
            }

            li.innerHTML = `
                <div class="d-flex align-items-center text-truncate">
                    ${fileIcon}
                    <span class="text-truncate">${file.name}</span>
                    ${sharedInfo}
                </div>
                ${fileSize}
                <div class="dropdown">
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="showFileInfo(${file.id})"><i class="bi bi-eye me-2"></i>Просмотр</a></li>
                        <li><a class="dropdown-item" href="#" onclick="renameFile(${file.id}, '${file.name}')"><i class="bi bi-pencil-square me-2"></i>Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFile(${file.id}, '${file.name}')"><i class="bi bi-share me-2"></i>Поделиться</a></li>
                        <li><a class="dropdown-item" href="${getApiBase()}/files/download/${file.id}"><i class="bi bi-download me-2"></i>Скачать</a></li>
                        ${deleteLinkHtml}
                    </ul>
                </div>
            `;

            li.setAttribute('draggable', 'true');
            li.addEventListener('dragstart', handleDragStart);
            li.addEventListener('dragend', handleDragEnd);
            li.addEventListener('click', function (e) {
                if (e.target.closest('.dropdown')) return;
                showFileInfo(file.id);
            });

            filesList.appendChild(li);
        });
    } else {
        filesList.innerHTML = '<li class="list-group-item text-muted">Нет файлов</li>';
    }
}

function renderFilesGrid(files) {
    const filesList = document.getElementById('filesList');
    filesList.innerHTML = '';
    filesList.className = 'files-list grid-view';

    if (files && files.length > 0) {
        files.forEach(file => {
            const card = document.createElement('div');
            card.className = 'grid-item';
            card.setAttribute('draggable', 'true');

            card.dataset.type = 'file';
            card.dataset.id = file.id;

            const isOwner = Number(file.user_id) === Number(currentUserId);
            const isImage = file.mime_type && file.mime_type.startsWith('image/');
            const isVideo = file.mime_type && file.mime_type.startsWith('video/');
            const isPdf = file.mime_type === 'application/pdf';

            let sharedInfo = '';
            if (isOwner && file.is_shared_by_owner) {
                sharedInfo = `<span class="badge bg-primary position-absolute top-0 start-0 m-1" title="Вы поделились"><i class="bi bi-send-fill"></i></span>`;
            } else if (!isOwner && file.is_shared) {
                const sharedByText = file.shared_by ? ` (Доступ предоставил: ${file.shared_by})` : '';
                sharedInfo = `<span class="badge bg-info position-absolute top-0 start-0 m-1" title="Общий файл${sharedByText}"><i class="bi bi-people-fill"></i></span>`;
            }

            const fileSize = file.file_size_formatted ? `<div class="grid-item-info">${file.file_size_formatted}</div>` : '';

            let deleteLinkHtml = '';
            if (isOwner) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(${file.id})"><i class="bi bi-trash me-2"></i>Удалить</a></li>`;
            } else if (file.is_shared) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFile(${file.id})"><i class="bi bi-box-arrow-left me-2"></i>Отказаться от доступа</a></li>`;
            }

            let contentHtml = '';
            if (isImage) {
                contentHtml = `<img src="${getApiBase()}/files/download/${file.id}?inline=1" alt="${file.name}" loading="lazy">`;
            } else if (isVideo) {
                contentHtml = `
                    <div class="video-tile-container">
                        <canvas width="140" height="100"></canvas>
                        <video class="video-tile" muted loop preload="metadata" playsinline>
                            <source src="${getApiBase()}/files/preview/${file.id}" type="${file.mime_type}">
                        </video>
                        <div class="video-overlay">
                            <i class="bi bi-play-circle-fill pulse"></i>
                        </div>
                    </div>
                `;
            } else if (isPdf) {
                contentHtml = `<canvas id="pdfPreviewCanvas_${file.id}" width="140" height="100" style="border:1px solid #ddd; border-radius:4px;"></canvas>`;
            } else {
                contentHtml = getFileIconForTiles(file.mime_type, '48px');
            }

            card.innerHTML = `
                ${sharedInfo}
                <div class="grid-item-icon">
                    ${contentHtml}
                </div>
                <div class="grid-item-name" title="${file.name}">${file.name}</div>
                ${fileSize}
                <div class="dropdown">
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="showFileInfo(${file.id})"><i class="bi bi-eye me-2"></i>Просмотр</a></li>
                        <li><a class="dropdown-item" href="#" onclick="renameFile(${file.id}, '${file.name}')"><i class="bi bi-pencil-square me-2"></i>Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFile(${file.id}, '${file.name}')"><i class="bi bi-share me-2"></i>Поделиться</a></li>
                        <li><a class="dropdown-item" href="${getApiBase()}/files/download/${file.id}"><i class="bi bi-download me-2"></i>Скачать</a></li>
                        ${deleteLinkHtml}
                    </ul>
                </div>
            `;

            card.setAttribute('draggable', 'true');
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragend', handleDragEnd);
            card.addEventListener('click', (e) => {
                if (!e.target.closest('.dropdown')) {
                    showFileInfo(file.id);
                }
            });

            filesList.appendChild(card);

        });

        files.forEach(file => {
            if (file.mime_type === 'application/pdf') {
                const canvasId = `pdfPreviewCanvas_${file.id}`;
                renderPdfPreview(file.id, canvasId).catch(err => {
                    console.warn(`Ошибка при рендеринге превью PDF для файла ${file.id}:`, err);
                });
            }
        });

        const noFiles = document.createElement('div');
        noFiles.className = 'text-center text-muted p-4 w-100';
        noFiles.textContent = 'Нет файлов';
        filesList.appendChild(noFiles);
    }
    initVideoTileHandlers();
}

function initVideoTileHandlers() {
    document.querySelectorAll('.grid-item').forEach(card => {
        const container = card.querySelector('.video-tile-container');
        if (!container) return;

        const video = container.querySelector('video.video-tile');
        const canvas = container.querySelector('canvas');
        const overlay = container.querySelector('.video-overlay');
        if (!video || !canvas) return;

        const ctx = canvas.getContext('2d');
        let animationFrameId = null;

        const drawCorrectedFrame = () => {
            if (!video.videoWidth || !video.videoHeight) return;

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
        };

        const drawLoop = () => {
            if (video.paused || video.ended) {
                if (animationFrameId) cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
                return;
            }
            drawCorrectedFrame();
            animationFrameId = requestAnimationFrame(drawLoop);
        };

        video.addEventListener('loadeddata', () => video.currentTime = 0.1);
        video.addEventListener('seeked', drawCorrectedFrame);
        video.addEventListener('play', () => {
            if (overlay) overlay.style.opacity = '0';
            drawLoop();
        });
        video.addEventListener('pause', () => {
            if (overlay) overlay.style.opacity = '1';
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        });

        card.addEventListener('mouseenter', () => {
            if (currentPreviewVideo && currentPreviewVideo !== video) {
                currentPreviewVideo.pause();
                currentPreviewVideo.currentTime = 0.1;
            }
            currentPreviewVideo = video;
            video.muted = true;
            video.play().catch(e => console.warn("Autoplay prevented:", e.name));
        });

        card.addEventListener('mouseleave', () => {
            video.pause();
            video.currentTime = 0.1; // Reset to first frame
        });
    });
}

async function showFileInfo(fileId) {
    try {
        const res = await fetch(`${getApiBase()}/files/info/${fileId}`, {
            credentials: 'include'
        });

        if (!res.ok) {
            throw new Error(`Ошибка HTTP: ${res.status}`);
        }

        const data = await res.json();

        if (!data.success || !data.file) {
            alert(data.error || 'Не удалось получить информацию о файле');
            return;
        }

        const file = data.file.file || data.file;

        const modalEl = document.getElementById('filePreviewModal');
        const modalTitle = modalEl.querySelector('.modal-title');
        const fileInfoDiv = modalEl.querySelector('#fileInfo');
        const filePreviewDiv = modalEl.querySelector('#filePreview');
        const downloadBtn = document.getElementById('downloadBtn');
        const shareBtn = document.getElementById('shareInModalBtn');
        const deleteBtn = document.getElementById('deleteInModalBtn');

        modalTitle.textContent = file.name || file.filename || '';

        fileInfoDiv.innerHTML = `
            <p><strong>Имя файла:</strong> ${file.name || file.filename || 'неизвестно'}</p>
            <p><strong>Тип файла:</strong> ${file.mime_type || 'неизвестно'}</p>
            <p><strong>Размер:</strong> ${file.file_size_formatted || file.file_size || file.size || 'неизвестно'}</p>
        `;

        if (file.mime_type && file.mime_type.startsWith('image/')) {
            filePreviewDiv.innerHTML = `<img src="${getApiBase()}/files/download/${file.id}?inline=1" alt="${file.name}" style="max-width: 100%;">`;
        } else if (file.mime_type === 'application/pdf') {
            filePreviewDiv.innerHTML = `<iframe src="${getApiBase()}/files/preview/${file.id}" width="100%" height="600px" style="border:none"></iframe>`;
        } else if (file.mime_type && file.mime_type.startsWith('video/')) {
            filePreviewDiv.innerHTML = `
                <div class="video-preview-container" style="position: relative; max-width: 100%; max-height: 60vh; background: black; border-radius: var(--radius);">
                    <canvas id="modalVideoCanvas" width="640" height="360" style="width: 100%; height: auto; display: block; border-radius: var(--radius);"></canvas>
                    <video id="modalVideoElement" muted playsinline style="display: none;">
                        <source src="${getApiBase()}/files/preview/${file.id}" type="${file.mime_type}">
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
                if (isFinite(video.duration)) {
                    seekBar.max = video.duration;
                }
                updateTimeLabel();
            });

            video.addEventListener('loadeddata', () => {
                const maxWidth = canvas.parentElement.clientWidth;
                const maxHeight = canvas.parentElement.clientHeight;
                let scale = Math.min(maxWidth / video.videoWidth, maxHeight / video.videoHeight, 1);
                canvas.width = video.videoWidth * scale;
                canvas.height = video.videoHeight * scale;

                if (isFinite(video.duration)) {
                    seekBar.max = video.duration;
                }
                updateTimeLabel();

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

        } else if (
            file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
            file.mime_type === 'application/msword'
        ) {
            filePreviewDiv.innerHTML = `
                <div class="alert alert-warning mb-2">
                    Предпросмотр docx доступен только для публичных файлов.<br>
                    <a href="${getApiBase()}/files/download/${file.id}" class="btn btn-primary mt-2" download>Скачать файл</a>
                </div>
            `;
        } else {
            filePreviewDiv.innerHTML = '<div class="alert alert-light text-center">Нет предпросмотра для этого типа файла</div>';
        }

        downloadBtn.href = `${getApiBase()}/files/download/${file.id}`;
        downloadBtn.download = file.name || file.filename || '';

        shareBtn.dataset.fileId = file.id;
        shareBtn.dataset.fileName = file.name || file.filename || '';

        if (deleteBtn) {
            deleteBtn.dataset.fileId = file.id;
        }

        const isOwner = Number(file.user_id) === Number(currentUserId);

        if (isOwner) {
            shareBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'inline-block';
        } else if (!isOwner && Number(file.is_shared) === 1) {
            shareBtn.style.display = 'inline-block';
            deleteBtn.style.display = 'none';
        } else {
            shareBtn.style.display = 'none';
            deleteBtn.style.display = 'none';
        }

        const unshareContainer = document.getElementById('unshareBtnContainer');
        if (unshareContainer) {
            if (!isOwner && Number(file.is_shared) === 1) {
                unshareContainer.innerHTML = `<button id="unshareInModalBtn" class="btn btn-outline-warning" type="button"><i class="bi bi-box-arrow-left me-1"></i>Отказаться от доступа</button>`;
                document.getElementById('unshareInModalBtn').onclick = () => {
                    window.filePreviewModalInstance.hide();
                    unshareFile(file.id);
                };
                unshareContainer.style.display = 'inline-block';
            } else {
                unshareContainer.innerHTML = '';
                unshareContainer.style.display = 'none';
            }
        }

        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        window.filePreviewModalInstance.show();

    } catch (error) {
        console.error('Ошибка при получении информации о файле:', error);
        alert('Произошла ошибка при получении информации о файле');
    }
}

let sharedRootId = null;

async function createSubfolderPrompt(parentFolderId) {
    const folderName = prompt('Введите имя новой папки:');
    if (!folderName) return;

    try {
        const res = await fetch(`${getApiBase()}/directories/add`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: folderName,
                parent_id: parentFolderId
            })
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при создании подпапки:', res.status, errorText);
            showMessage('Ошибка при создании папки', 'danger');
            return;
        }

        const data = await res.json();

        if (data.success) {
            showMessage('Папка успешно создана', 'success');
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при создании папки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при создании подпапки:', error);
        showMessage('Произошла ошибка при создании папки', 'danger');
    }
}

async function renameFile(fileId, currentName) {

    const extMatch = currentName.match(/(\.[^\.]+)$/);
    const extension = extMatch ? extMatch[1] : '';
    const baseName = extMatch ? currentName.slice(0, -extension.length) : currentName;

    const newBaseName = prompt(`Введите новое имя для файла "${baseName}":`, baseName);
    if (!newBaseName || newBaseName.trim() === '' || newBaseName === baseName) {
        return;
    }

    const newName = newBaseName.trim() + extension;

    try {
        const res = await fetch(`${getApiBase()}/files/rename`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file_id: Number(fileId),
                new_name: newName
            }),
            credentials: 'include'
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при переименовании файла:', res.status, errorText);
            showMessage('Ошибка при переименовании файла', 'danger');
            return;
        }

        const data = await res.json();

        if (data.success) {
            showMessage('Файл успешно переименован', 'success');
            await loadFiles();

            const downloadBtn = document.getElementById('downloadBtn');
            const modalEl = document.getElementById('filePreviewModal');
            if (downloadBtn && modalEl && modalEl.classList.contains('show')) {
                downloadBtn.download = newName;

                await showFileInfo(fileId);
            }
        } else {
            showMessage(data.error || 'Ошибка при переименовании файла', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при переименовании файла:', error);
        showMessage('Произошла ошибка при переименовании файла', 'danger');
    }
}

async function renameFolder(folderId, currentName) {
    const newName = prompt(`Введите новое имя для папки "${currentName}":`, currentName);
    if (!newName || newName.trim() === '' || newName === currentName) {
        return;
    }

    try {
        const res = await fetch(`${getApiBase()}/directories/rename`, {
            method: 'PUT',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: folderId,
                new_name: newName.trim()
            })
        });

        const data = await res.json();

        if (data.success) {
            showMessage('Папка успешно переименована', 'success');
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при переименовании папки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при переименовании папки:', error);
        showMessage('Произошла ошибка при переименовании папки', 'danger');
    }
}

async function shareFile(fileId, fileName) {
    const email = prompt(`Введите email пользователя, которому хотите предоставить доступ к файлу "${fileName}":`);
    if (!email) return;

    try {
        const response = await fetch(`${getApiBase()}/files/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                email: email.trim()
            }),
            credentials: 'include'
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Доступ к файлу успешно предоставлен', 'success');
            await loadFiles();
        } else {
            showMessage(data.error || 'Ошибка при предоставлении доступа', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при отправке запроса:', error);
        showMessage('Произошла ошибка при предоставлении доступа', 'danger');
    }
}


window.shareFile = shareFile;

async function shareFolder(folderId, folderName) {
    try {
        const email = prompt(`Введите email пользователя, которому хотите предоставить доступ к папке "${folderName}":`);
        if (!email) {
            return;
        }

        const response = await fetch(`${getApiBase()}/directories/share`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                folder_id: folderId,
                email: email.trim()
            })
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Доступ к папке успешно предоставлен', 'success');
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при предоставлении доступа', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при отправке запроса:', error);
        showMessage('Произошла ошибка при предоставлении доступа', 'danger');
    }
}

async function unshareFolder(folderId) {
    if (!confirm("Вы уверены, что хотите отказаться от доступа к этой папке?")) {
        return;
    }

    try {
        const res = await fetch(`${getApiBase()}/directories/unshare`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ directory_id: folderId }),
            credentials: 'include'
        });

        const data = await res.json();

        if (data.success) {
            showMessage('Доступ к папке успешно отозван', 'success');
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при отзыве доступа', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при отзыве доступа:', error);
        showMessage('Произошла ошибка при отзыве доступа', 'danger');
    }
}

async function deleteFolder(folderId) {
    if (!confirm('Вы уверены, что хотите удалить эту папку?')) return;

    try {
        const res = await fetch(`${getApiBase()}/directories/delete/${folderId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            showMessage('Папка успешно удалена', 'success');
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при удалении папки', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при удалении папки:', error);
        showMessage('Произошла ошибка при удалении папки', 'danger');
    }
}

async function deleteFile(fileId) {
    if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;

    try {
        const res = await fetch(`${getApiBase()}/files/remove/${fileId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            showMessage('Файл успешно удалён', 'success');
            await loadFiles();
            if (window.filePreviewModalInstance) {
                window.filePreviewModalInstance.hide();
            }
        } else {
            showMessage(data.error || 'Ошибка при удалении файла', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при удалении файла:', error);
        showMessage('Произошла ошибка при удалении файла', 'danger');
    }
}


document.addEventListener('DOMContentLoaded', () => {
    const deleteBtn = document.getElementById('deleteInModalBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', () => {
            const fileId = deleteBtn.dataset.fileId;
            if (!fileId) {
                console.error('fileId не найден у кнопки удаления в модальном окне');
                return;
            }
            deleteFile(fileId);
        });
    }
});

async function uploadSelectedFiles() {
    const fileInput = document.getElementById('fileInput');
    const folderInput = document.getElementById('folderInput');
    const uploadBtn = document.getElementById('uploadFilesBtn');

    const allFiles = [...(fileInput.files || []), ...(folderInput.files || [])];
    if (allFiles.length === 0) {
        showMessage('Выберите файлы или папки для загрузки', 'warning');
        return;
    }

    const originalBtnHtml = uploadBtn ? uploadBtn.innerHTML : '';
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Загрузка...';
    }

    try {
        const formData = new FormData();
        const paths = allFiles.map(f => f.webkitRelativePath || f.name);
        allFiles.forEach(f => formData.append('files[]', f));
        formData.append('paths', JSON.stringify(paths));
        formData.append('directory_id', currentDirectoryId === 'root' ? 'root' : currentDirectoryId);

        const res = await fetch(`${getApiBase()}/files/upload`, {
            method: 'POST',
            body: formData,
            credentials: 'include'
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при загрузке:', res.status, errorText);
            showMessage('Ошибка при загрузке файлов', 'danger');
            return;
        }

        const data = await res.json();
        if (data.success) {
            showMessage(data.message || 'Загрузка завершена', 'success');
            clearFileSelection();
            await loadFiles();
            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при загрузке файлов', 'danger');
        }
    } catch (e) {
        console.error('Ошибка при загрузке файлов:', e);
        showMessage('Произошла ошибка при загрузке файлов', 'danger');
    } finally {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = originalBtnHtml;
        }
    }
}

async function unshareFile(fileId) {
    if (!confirm("Вы уверены, что хотите отказаться от доступа к этому файлу?")) {
        return;
    }

    try {
        const res = await fetch(`${getApiBase()}/files/unshare`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: fileId }),
            credentials: 'include'
        });

        const data = await res.json();

        if (data.success) {
            showMessage('Доступ к файлу успешно отозван', 'success');
            await loadFiles();
        } else {
            showMessage(data.error || 'Ошибка при отзыве доступа', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при отзыве доступа:', error);
        showMessage('Произошла ошибка при отзыве доступа', 'danger');
    }
}


document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', () => {
    const modalEl = document.getElementById('filePreviewModal');
    const video = modalEl.querySelector('video');
    if (video) {
        video.pause();
        video.currentTime = 0;
        video.removeAttribute('src');
    }

    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

    const safeElement = document.getElementById('userGreeting') || document.body;
    if (safeElement) {
        safeElement.setAttribute('tabindex', '-1');
        safeElement.focus();
        safeElement.removeAttribute('tabindex');
    }
});
