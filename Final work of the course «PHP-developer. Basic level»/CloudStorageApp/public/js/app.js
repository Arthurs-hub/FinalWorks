let currentView = 'list';
let currentDirectoryId = localStorage.getItem("currentDirectoryId") || "root";

async function refreshLists() {
    try {

        const filesList = document.getElementById('filesList');
        const foldersList = document.getElementById('foldersList');

        if (filesList) filesList.innerHTML = '<div class="text-center">Загрузка...</div>';
        if (foldersList) foldersList.innerHTML = '<div class="text-center">Загрузка...</div>';

        await loadFolders();

        showMessage('Списки обновлены', 'success');
    } catch (error) {
        console.error('Ошибка при обновлении списков:', error);
        showMessage('Ошибка при обновлении списков', 'danger');
    }
}

function addRefreshButton() {
    if (document.getElementById('refreshListsBtn')) {
        return;
    }

    const refreshBtn = document.createElement('button');
    refreshBtn.id = 'refreshListsBtn';
    refreshBtn.className = 'btn btn-outline-secondary btn-sm';
    refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Обновить списки';
    refreshBtn.onclick = refreshLists;
    refreshBtn.title = 'Обновить списки файлов и папок';

    const refreshBtnContainer = document.getElementById('refreshBtnContainer');
    if (refreshBtnContainer) {
        refreshBtnContainer.appendChild(refreshBtn);
    }
}

function handleDragEnter(event) {
    event.preventDefault();
    if (event.currentTarget.dataset.folderId) {
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
    if (event.currentTarget.dataset.folderId) {
        event.currentTarget.classList.remove('drag-over');
    }
}

async function handleDrop(event) {
    event.preventDefault();
    const folderId = event.currentTarget?.dataset?.folderId;
    console.log('Drop target folderId:', folderId);
    if (!folderId) {
        showMessage('Целевая папка не определена', 'danger');
        return;
    }

    const data = event.dataTransfer.getData('text/plain');
    console.log('Dragged data:', data);
    if (!data) return;

    try {
        const parsed = JSON.parse(data);
        console.log('handleDrop parsed data:', parsed);

        const sourceIdNum = Number(parsed.id);
        const targetIdNum = Number(folderId);

        if (parsed.type === 'file') {

            const res = await fetch('/CloudStorageApp/public/files/move', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    file_id: sourceIdNum,
                    target_directory_id: targetIdNum
                }),
                credentials: 'include'
            });

            const result = await res.json();

            if (result.success) {
                showMessage('Файл успешно перемещён', 'success');
                await loadFolders();
            } else {
                showMessage(result.error || 'Ошибка при перемещении файла', 'danger');
            }
        } else if (parsed.type === 'folder') {
            if (!sourceIdNum || !targetIdNum) {
                showMessage('Недостаточно данных для перемещения папки', 'danger');
                return;
            }

            if (sourceIdNum === targetIdNum) {
                showMessage('Нельзя переместить папку в саму себя', 'warning');
                return;
            }

            if (await isDescendantFolder(sourceIdNum, targetIdNum)) {
                showMessage('Нельзя переместить папку в её вложенную папку', 'warning');
                return;
            }

            const res = await fetch('/CloudStorageApp/public/directories/move', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    directory_id: sourceIdNum,
                    target_directory_id: targetIdNum
                }),
                credentials: 'include'
            });

            if (!res.ok) {
                const errorText = await res.text();
                console.error('Ошибка HTTP при перемещении папки:', res.status, errorText);
                showMessage('Ошибка при перемещении папки', 'danger');
                return;
            }

            const result = await res.json();

            if (result.success) {
                showMessage('Папка успешно перемещена', 'success');
                await loadFolders();
            } else {
                showMessage(result.error || 'Ошибка при перемещении папки', 'danger');
            }
        }
    } catch (error) {
        console.error('Ошибка при обработке перемещения:', error);
        showMessage('Произошла ошибка при перемещении', 'danger');
    } finally {
        if (event.currentTarget && event.currentTarget.classList) {
            event.currentTarget.classList.remove('drag-over');
        }
    }
}

async function isDescendantFolder(sourceFolderId, targetFolderId) {
    try {

        const res = await fetch(`/CloudStorageApp/public/directories/get/${targetFolderId}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });
        if (!res.ok) {
            console.error('Ошибка при проверке вложенности папок:', res.status);
            return false;
        }
        const data = await res.json();
        if (!data.success || !data.directory) {
            return false;
        }

        let currentParentId = data.directory.parent_id;

        while (currentParentId !== null && currentParentId !== undefined) {
            if (currentParentId === sourceFolderId) {
                return true;
            }

            const parentRes = await fetch(`/CloudStorageApp/public/directories/get/${currentParentId}`, {
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            if (!parentRes.ok) {
                break;
            }
            const parentData = await parentRes.json();
            if (!parentData.success || !parentData.directory) {
                break;
            }
            currentParentId = parentData.directory.parent_id;
        }
        return false;
    } catch (e) {
        console.error('Ошибка при проверке вложенности папок:', e);
        return false;
    }
}

async function loadUserInfo() {
    try {
        const res = await fetch('/CloudStorageApp/public/users/current', {
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
        document.getElementById('userGreeting').textContent = 'Добро пожаловать!';
    }
}
document.addEventListener('DOMContentLoaded', async () => {
    try {

        await loadUserInfo();
        await loadFolders();
        addRefreshButton();

    } catch (error) {

    }

    document.getElementById("fileInput").addEventListener("change", handleFileSelection);
    document.getElementById("folderInput").addEventListener("change", handleFileSelection);
    document.getElementById("createFolderBtn").onclick = (e) => {
        e.preventDefault();
        createFolder();
    };
    document.getElementById('logoutBtn').onclick = async () => {
        await fetch('/CloudStorageApp/public/logout', { method: 'POST', credentials: 'include' });
        localStorage.removeItem("currentDirectoryId");
        window.location.href = '/CloudStorageApp/public/login.html';
    };

    const modalEl = document.getElementById('filePreviewModal');
    window.filePreviewModalInstance = new bootstrap.Modal(modalEl, { focus: false });

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

function handleFileSelection(event) {
    const files = event.target.files;
    const selectedFilesElement = document.getElementById("selectedFiles");

    if (!selectedFilesElement) {
        console.error("Element with id 'selectedFiles' not found");
        return;
    }

    if (files.length > 0) {
        let names = [];
        for (let i = 0; i < Math.min(files.length, 3); i++) {
            names.push(files[i].name);
        }
        let message = `Выбрано: ${files.length} ${files.length === 1 ? 'файл' : 'файлов'}`;
        if (files.length > 3) {
            message += ` (${names.join(', ')}...)`;
        } else {
            message += ` (${names.join(', ')})`;
        }
        selectedFilesElement.textContent = message;
        selectedFilesElement.title = Array.from(files).map(f => f.name).join('\n');
    } else {
        selectedFilesElement.textContent = "Файлы/папки не выбраны";
        selectedFilesElement.removeAttribute('title');
    }
}

async function loadFiles() {
    try {
        const res = await fetch(`/CloudStorageApp/public/files/list?directory_id=${currentDirectoryId}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
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
    filesList.className = 'list-group mt-2';

    if (files && files.length > 0) {
        files.forEach(file => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('draggable', 'true');

            const isOwner = Number(file.user_id) === Number(currentUserId);

            const isImage = file.mime_type && file.mime_type.startsWith('image/');

            let fileIcon = '';
            if (isImage) {
                fileIcon = '<i class="bi bi-file-image text-primary" style="font-size: 24px;"></i>';
            } else if (file.mime_type === 'application/pdf') {
                fileIcon = '<i class="bi bi-file-pdf text-danger" style="font-size: 24px;"></i>';
            } else if (
                file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                file.mime_type === 'application/msword'
            ) {
                fileIcon = '<i class="bi bi-file-word text-primary" style="font-size: 24px;"></i>';
            } else {
                fileIcon = '<i class="bi bi-file-earmark" style="font-size: 48px;"></i>';
            }

            let sharedInfo = '';
            if (isOwner && file.is_shared_by_owner) {
                sharedInfo = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1">👥 (вы поделились)</span>`;
            } else if (!isOwner && file.is_shared) {
                sharedInfo = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1" title="Доступ предоставил: ${file.shared_by}">👥</span>`;
            }

            const fileSize = file.file_size ? `<span class="text-muted ms-2">(${file.file_size})</span>` : '';

            let deleteLinkHtml = '';
            if (isOwner) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(${file.id})">Удалить</a></li>`;
            } else if (file.is_shared) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFile(${file.id})">Отказаться от доступа</a></li>`;
            }

            li.innerHTML = `
                <div class="d-flex align-items-center">
                    ${fileIcon}
                    ${sharedInfo}
                    <span style="cursor:pointer">${file.name}</span>
                    ${fileSize}
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-link p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 50%; width: 28px; height: 28px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="2" cy="8" r="2"/>
                            <circle cx="8" cy="8" r="2"/>
                            <circle cx="14" cy="8" r="2"/>
                        </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="renameFile(${file.id}, '${file.name}')">Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFile(${file.id}, '${file.name}')">Поделиться</a></li>
                        <li><a class="dropdown-item" href="/CloudStorageApp/public/files/download/${file.id}">Скачать</a></li>
                        ${deleteLinkHtml}
                    </ul>
                </div>
            `;

            li.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    id: file.id,
                    type: 'file'
                }));
            });

            li.addEventListener('click', function (e) {
                if (e.target.closest('.dropdown')) return;
                showFileInfo(file.id);
            });

            filesList.appendChild(li);
        });
    } else {
        filesList.innerHTML = '<li class="list-group-item">Нет файлов</li>';
    }
}

function renderFilesGrid(files) {
    const filesList = document.getElementById('filesList');
    filesList.innerHTML = '';
    filesList.className = 'd-flex flex-wrap gap-3';

    if (files && files.length > 0) {
        files.forEach(file => {
            const card = document.createElement('div');
            card.className = 'card position-relative p-2 text-center';
            card.style.width = '160px';
            card.style.cursor = 'pointer';
            card.setAttribute('draggable', 'true');

            const isOwner = Number(file.user_id) === Number(currentUserId);

            const isImage = file.mime_type && file.mime_type.startsWith('image/');

            let fileIcon = '';
            if (isImage) {
                fileIcon = '<i class="bi bi-file-image text-primary" style="font-size: 48px;"></i>';
            } else if (file.mime_type === 'application/pdf') {
                fileIcon = '<i class="bi bi-file-pdf text-danger" style="font-size: 48px;"></i>';
            } else if (
                file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                file.mime_type === 'application/msword'
            ) {
                fileIcon = '<i class="bi bi-file-word text-primary" style="font-size: 48px;"></i>';
            } else {
                fileIcon = '<i class="bi bi-file-earmark" style="font-size: 48px;"></i>';
            }

            let sharedInfo = '';
            if (isOwner && file.is_shared_by_owner) {
                sharedInfo = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1">👥 (вы поделились)</span>`;
            } else if (!isOwner && file.is_shared) {
                sharedInfo = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1" title="Доступ предоставил: ${file.shared_by}">👥</span>`;
            }

            const fileSize = file.file_size ? `<div class="text-muted">${file.file_size}</div>` : '';

            let deleteLinkHtml = '';
            if (isOwner) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFile(${file.id})">Удалить</a></li>`;
            } else if (file.is_shared) {
                deleteLinkHtml = `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFile(${file.id})">Отказаться от доступа</a></li>`;
            }

            card.innerHTML = `
                ${sharedInfo}
                <div class="img-container" style="height: 100px; display: flex; 
                     align-items: center; justify-content: center;">
                    ${isImage ?
                    `<img src="/CloudStorageApp/public/files/download/${file.id}?inline=1" 
                              alt="${file.name}" 
                              style="max-width: 100%; max-height: 100%; 
                                     object-fit: contain; border-radius: 4px;">` :
                    fileIcon}
                </div>
                <div class="fw-bold text-truncate mt-2" title="${file.name}">${file.name}</div>
                ${fileSize}
                <div class="dropdown position-absolute top-0 end-0 m-1">
                    <button class="btn btn-sm btn-link p-1" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false" 
                            style="border-radius: 50%; width: 28px; height: 28px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="2" cy="8" r="2"/>
                            <circle cx="8" cy="8" r="2"/>
                            <circle cx="14" cy="8" r="2"/>
                        </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" 
                               onclick="renameFile(${file.id}, '${file.name}')">Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" 
                               onclick="shareFile(${file.id}, '${file.name}')">Поделиться</a></li>
                        <li><a class="dropdown-item" 
                               href="/CloudStorageApp/public/files/download/${file.id}">Скачать</a></li>
                        ${deleteLinkHtml}
                    </ul>
                </div>
            `;

            if (file.mime_type === 'application/pdf') {
                card.querySelector('.img-container').innerHTML = '<div class="text-muted">Загрузка превью...</div>';
                getPdfPreviewImageUrl(file.id).then(imgUrl => {
                    card.querySelector('.img-container').innerHTML = `<img src="${imgUrl}" style="max-width:100%;max-height:100%;">`;
                }).catch(() => {
                    card.querySelector('.img-container').innerHTML = '<i class="bi bi-file-pdf text-danger" style="font-size: 48px;"></i>';
                });
            }

            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', JSON.stringify({
                    id: file.id,
                    type: 'file'
                }));
            });

            card.onclick = (e) => {
                if (!e.target.closest('.dropdown')) {
                    showFileInfo(file.id);
                }
            };

            filesList.appendChild(card);
        });
    } else {
        const noFiles = document.createElement('div');
        noFiles.textContent = 'Нет файлов';
        filesList.appendChild(noFiles);
    }
}

async function loadFolders() {
    try {
        let directoryIdToLoad = currentDirectoryId;

        if (!directoryIdToLoad || directoryIdToLoad === 'root') {
            directoryIdToLoad = 'root';
        }

        const res = await fetch(`/CloudStorageApp/public/directories/get/${directoryIdToLoad}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        if (!res.ok) {
            console.warn(`Directory ${directoryIdToLoad} inaccessible, falling back to root.`);
            directoryIdToLoad = 'root';
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
        }

        const data = await res.json();

        if (!data.success) {
            console.error('Ошибка в ответе сервера при загрузке папок:', data.error);
            showMessage(data.error || 'Ошибка загрузки папок', 'danger');
            return;
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
        console.error('Ошибка при загрузке папок:', error);
        showMessage('Ошибка при загрузке папок', 'danger');
    }
}

async function renameFile(fileId, currentName) {
    const newName = prompt(`Введите новое имя для файла "${currentName}":`, currentName);
    if (!newName || newName.trim() === '' || newName === currentName) {
        return;
    }

    try {
        const res = await fetch('/CloudStorageApp/public/files/rename', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                file_id: fileId,
                new_name: newName.trim()
            }),
            credentials: 'include'
        });

        const data = await res.json();

        if (data.success) {
            showMessage('Файл успешно переименован', 'success');
            await loadFiles();
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
        const res = await fetch('/CloudStorageApp/public/directories/rename', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: folderId,
                new_name: newName.trim()
            }),
            credentials: 'include'
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
        const response = await fetch('/CloudStorageApp/public/files/share', {
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

        const response = await fetch('/CloudStorageApp/public/directories/share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                folder_id: folderId,
                email: email.trim()
            }),
            credentials: 'include'
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

document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', () => { const safeElement = document.getElementById('userGreeting') || document.body; if (safeElement) { safeElement.setAttribute('tabindex', '-1'); safeElement.focus(); } });

function shareFromModal(fileId, fileName) {
    if (!fileId || !fileName) {
        console.error('Не указан ID файла или имя файла');
        return;
    }
    shareFile(fileId, fileName);
}

async function deleteFile(fileId) {
    if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;

    try {
        const res = await fetch(`/CloudStorageApp/public/files/remove/${fileId}`, {
            method: 'DELETE',
            credentials: 'include'
        });
        const data = await res.json();
        if (data.success) {
            showMessage('Файл успешно удалён', 'success');
            await loadFiles();
        } else {
            showMessage(data.error || 'Ошибка при удалении файла', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при удалении файла:', error);
        showMessage('Произошла ошибка при удалении файла', 'danger');
    }
}

async function deleteFolder(folderId) {
    if (!confirm('Вы уверены, что хотите удалить эту папку?')) return;

    try {
        const res = await fetch(`/CloudStorageApp/public/directories/delete/${folderId}`, {
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

async function deleteFileFromPreview(fileId) {
    try {
        const res = await fetch(`/CloudStorageApp/public/files/remove/${fileId}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        const data = await res.json();

        if (data.success) {
            showPreviewMessage("Файл успешно удален!", "success");
            const modal = bootstrap.Modal.getInstance(document.getElementById('filePreviewModal'));
            modal.hide();

            showMessage('Файл успешно удалён', 'success');

        } else {
            showPreviewMessage(data.error || "Ошибка удаления файла", "danger");
        }
    } catch (error) {
        console.error('Ошибка при удалении файла:', error);
        showPreviewMessage("Произошла ошибка при удалении файла", "danger");
    }
}

async function unshareFolder(folderId) {
    if (!confirm("Вы уверены, что хотите отказаться от доступа к этой папке?")) {
        return;
    }

    try {
        const res = await fetch('/CloudStorageApp/public/directories/unshare', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
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

function renderFoldersList(folders, currentDirectory) {
    const foldersList = document.getElementById('foldersList');
    foldersList.innerHTML = '';
    foldersList.className = 'list-group mb-3';

    let hasBack = false;
    if (currentDirectory && currentDirectory.parent_id !== null) {
        const backLi = document.createElement('li');
        backLi.className = 'list-group-item d-flex align-items-center';
        backLi.innerHTML = `<span style="cursor:pointer" onclick="goBack()">⬅️ Назад</span>
            <span class="fw-bold ms-3">${currentDirectory.name}</span>`;
        foldersList.appendChild(backLi);
        hasBack = true;
    }

    if (folders.length > 0) {
        folders.forEach(folder => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.setAttribute('draggable', 'true');

            li.dataset.folderId = folder.id;

            const isOwner = Number(folder.user_id) === Number(currentUserId);

            let sharedInfo = '';
            if (folder.is_shared && !isOwner) {

                sharedInfo = `<span class="badge bg-info me-2" title="Доступ предоставил: ${folder.shared_by}">👥 Общая</span>`;
            } else if (folder.is_shared_by_owner && isOwner) {

                sharedInfo = `<span class="badge bg-info me-2">👥 Общая (вы поделились)</span>`;
            }

            const actionLinkHtml = isOwner
                ? `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFolder(${folder.id})">Удалить</a></li>`
                : `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFolder(${folder.id})">Отказаться от доступа</a></li>`;

            li.innerHTML = `
                <div class="d-flex align-items-center">
                    <span style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; margin-right: 8px;">
                        <svg width="28" height="28" viewBox="0 0 48 48">
                          <rect x="4" y="14" width="40" height="24" rx="4" fill="#FFD54F" stroke="#FFA000" stroke-width="2"/>
                          <rect x="4" y="10" width="16" height="8" rx="2" fill="#FFE082" stroke="#FFA000" stroke-width="2"/>
                        </svg>
                    </span>
                    ${sharedInfo}
                    <span style="cursor:pointer">${folder.name}</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-link p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 50%; width: 28px; height: 28px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="2" cy="8" r="2"/>
                            <circle cx="8" cy="8" r="2"/>
                            <circle cx="14" cy="8" r="2"/>
                        </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="renameFolder(${folder.id}, '${folder.name}')">Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFolder(${folder.id}, '${folder.name}')">Поделиться</a></li>
                        <li><a class="dropdown-item" href="/CloudStorageApp/public/directories/download/${folder.id}">Скачать</a></li>
                        ${actionLinkHtml}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="createSubfolderPrompt(${folder.id})">+ Создать папку</a></li>
                    </ul>
                </div>
            `;

            li.onclick = null;
            li.onclick = (e) => {
                if (!e.target.closest('.dropdown')) {
                    openFolder(folder.id);
                }
            };

            foldersList.appendChild(li);
        });
    } else if (!hasBack) {
        foldersList.innerHTML += '<li class="list-group-item">Нет папок</li>';
    }
}

function renderFoldersGrid(folders, currentDirectory) {
    const foldersList = document.getElementById('foldersList');
    foldersList.innerHTML = '';
    foldersList.className = 'd-flex flex-wrap gap-3 mb-3';

    let hasBack = false;
    if (currentDirectory && currentDirectory.parent_id !== null) {
        const backCard = document.createElement('div');
        backCard.className = 'card position-relative p-2 text-center';
        backCard.style.width = '180px';
        backCard.style.cursor = 'pointer';
        backCard.onclick = () => goBack();
        backCard.innerHTML = `
            <div class="img-container" style="height: 120px; display: flex; align-items: center; justify-content: center;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="fw-bold text-truncate mt-2">Назад</div>
            <div class="text-muted text-truncate" title="${currentDirectory.name}">${currentDirectory.name}</div>
        `;
        foldersList.appendChild(backCard);
        hasBack = true;
    }

    if (folders && folders.length > 0) {
        folders.forEach(folder => {
            const card = document.createElement('div');
            card.className = 'card position-relative p-2 text-center';
            card.style.width = '180px';
            card.style.cursor = 'pointer';
            card.dataset.folderId = folder.id;

            card.onclick = null;
            card.onclick = (e) => {
                if (!e.target.closest('.dropdown')) {
                    openFolder(folder.id);
                }
            };

            const isOwner = Number(folder.user_id) === Number(currentUserId);

            let sharedBadge = '';
            if (folder.is_shared && !isOwner) {

                sharedBadge = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1" title="Доступ предоставил: ${folder.shared_by}">👥</span>`;
            } else if (folder.is_shared_by_owner && isOwner) {

                sharedBadge = `<span class="badge bg-info me-2 position-absolute top-0 start-0 m-1">👥</span>`;
            }

            const actionLinkHtml = isOwner
                ? `<li><a class="dropdown-item text-danger" href="#" onclick="deleteFolder(${folder.id})">Удалить</a></li>`
                : `<li><a class="dropdown-item text-danger" href="#" onclick="unshareFolder(${folder.id})">Отказаться от доступа</a></li>`;

            card.innerHTML = `
                ${sharedBadge}
                <div class="img-container" style="height: 120px; display: flex; align-items: center; justify-content: center;">
                    <svg width="96" height="96" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M40 12H24L20 8H8C5.8 8 4 9.8 4 12V36C4 38.2 5.8 40 8 40H40C42.2 40 44 38.2 44 36V16C44 13.8 42.2 12 40 12Z" fill="#FFD54F"/>
                        <path d="M40 12H24L20 8H8C5.8 8 4 9.8 4 12V16H44V16C44 13.8 42.2 12 40 12Z" fill="#FFE082"/>
                    </svg>
                </div>
                <div class="fw-bold text-truncate mt-2" title="${folder.name}">${folder.name}</div>
                <div class="dropdown position-absolute top-0 end-0 m-1">
                    <button class="btn btn-sm btn-link p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 50%; width: 28px; height: 28px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                            <circle cx="2" cy="8" r="2"/>
                            <circle cx="8" cy="8" r="2"/>
                            <circle cx="14" cy="8" r="2"/>
                        </svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="renameFolder(${folder.id}, '${folder.name}')">Переименовать</a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareFolder(${folder.id}, '${folder.name}')">Поделиться</a></li>
                        <li><a class="dropdown-item" href="/CloudStorageApp/public/directories/download/${folder.id}">Скачать</a></li>
                        ${actionLinkHtml}
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" onclick="createSubfolderPrompt(${folder.id})">+ Создать папку</a></li>
                    </ul>
                </div>
            `;

            foldersList.appendChild(card);
        });
    } else if (!hasBack) {
        const noFolders = document.createElement('div');
        noFolders.textContent = 'Нет папок';
        foldersList.appendChild(noFolders);
    }
}

async function getPdfPreviewImageUrl(fileId) {
    const url = `/CloudStorageApp/public/files/download/${fileId}?inline=1`;
    const pdf = await pdfjsLib.getDocument(url).promise;
    const page = await pdf.getPage(1);
    const viewport = page.getViewport({ scale: 1 });
    const canvas = document.createElement('canvas');
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    return canvas.toDataURL();
}

function getPreviewHtml(file) {

    if (file.mime_type === 'application/pdf') {
        return `<iframe src="/CloudStorageApp/public/files/download/${file.id}?inline=1" width="100%" height="600px" style="border:none"></iframe>`;
    }

    if (file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        const fileUrl = encodeURIComponent(window.location.origin + `/CloudStorageApp/public/files/download/${file.id}?inline=1`);
        return `
            <div class="alert alert-info mb-2">Если документ не отображается, скачайте его или откройте в Word Online вручную.</div>
            <iframe src="https://view.officeapps.live.com/op/view.aspx?src=${fileUrl}" width="100%" height="600px" style="border:none"></iframe>
        `;
    }

    if (file.mime_type === 'application/msword') {
        const fileUrl = encodeURIComponent(window.location.origin + `/CloudStorageApp/public/files/download/${file.id}?inline=1`);
        return `
            <div class="alert alert-info mb-2">Если документ не отображается, скачайте его или откройте в Word Online вручную.</div>
            <iframe src="https://view.officeapps.live.com/op/view.aspx?src=${fileUrl}" width="100%" height="600px" style="border:none"></iframe>
        `;
    }

    if (file.mime_type && file.mime_type.startsWith('image/')) {
        return `<img src="/CloudStorageApp/public/files/download/${file.id}?inline=1" style="max-width:100%;max-height:600px;" alt="preview">`;
    }

    return `<div class="text-center text-muted">Нет предпросмотра для этого типа файла</div>`;
}

function showMessage(message, type = 'info') {
    const messageContainer = document.getElementById('messageContainer');
    if (!messageContainer) {
        alert(message);
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;

    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.left = '50%';
    alertDiv.style.transform = 'translateX(-50%)';
    alertDiv.style.zIndex = '1100';
    alertDiv.style.minWidth = '200px';
    alertDiv.style.maxWidth = '800px';
    alertDiv.style.textAlign = 'center';
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

function showPreviewMessage(message, type = 'info') {
    const previewMessageContainer = document.getElementById('filePreviewMessage');
    if (!previewMessageContainer) {
        alert(message);
        return;
    }
    previewMessageContainer.textContent = message;
    previewMessageContainer.className = `alert alert-${type}`;
    previewMessageContainer.style.display = 'block';
    previewMessageContainer.style.position = 'absolute';
    previewMessageContainer.style.top = '10px';
    previewMessageContainer.style.left = '50%';
    previewMessageContainer.style.transform = 'translateX(-50%)';
    previewMessageContainer.style.zIndex = '1050';
    previewMessageContainer.style.width = 'auto';
    previewMessageContainer.style.maxWidth = '90%';
    previewMessageContainer.style.textAlign = 'center';

    setTimeout(() => {
        previewMessageContainer.style.display = 'none';
        previewMessageContainer.textContent = '';
        previewMessageContainer.className = '';
        previewMessageContainer.style.position = '';
        previewMessageContainer.style.top = '';
        previewMessageContainer.style.left = '';
        previewMessageContainer.style.transform = '';
        previewMessageContainer.style.zIndex = '';
        previewMessageContainer.style.width = '';
        previewMessageContainer.style.maxWidth = '';
        previewMessageContainer.style.textAlign = '';
    }, 3000);
}

async function createFolder() {
    const folderNameInput = document.getElementById('newFolderName');
    const folderName = folderNameInput.value.trim();

    if (!folderName) {
        showMessage('Имя папки не может быть пустым', 'warning');
        return;
    }

    try {
        const res = await fetch('/CloudStorageApp/public/directories/add', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: folderName,
                parent_id: currentDirectoryId === 'root' ? null : currentDirectoryId
            })
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при создании папки:', res.status, errorText);
            showMessage('Ошибка при создании папки', 'danger');
            return;
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
        showMessage('Произошла ошибка при создании папки', 'danger');
    }
}

async function createSubfolderPrompt(parentFolderId) {
    const folderName = prompt('Введите имя новой папки:');
    if (!folderName) return;

    try {
        const res = await fetch('/CloudStorageApp/public/directories/add', {
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

function openFolder(folderId) {
    currentDirectoryId = folderId;
    localStorage.setItem("currentDirectoryId", folderId);
    loadFolders();
}

async function goBack() {
    try {
        if (currentDirectoryId === 'root' || !currentDirectoryId) {
            return;
        }

        const res = await fetch(`/CloudStorageApp/public/directories/get/${currentDirectoryId}`, {
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

        const parentId = data.directory.parent_id;
        const ownerId = data.directory.user_id;

        if (ownerId !== currentUserId && data.directory.is_shared) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        if (!parentId || parentId === null || parentId === undefined || parentId === 1) {
            currentDirectoryId = 'root';
            localStorage.setItem("currentDirectoryId", currentDirectoryId);
            await loadFolders();
            return;
        }

        const parentRes = await fetch(`/CloudStorageApp/public/directories/get/${parentId}`, {
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache'
            }
        });

        if (!parentRes.ok) {
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

function addDragAndDropHandlersToFolders() {
    const folderElements = document.querySelectorAll('[data-folder-id]');
    folderElements.forEach(folderEl => {
        folderEl.setAttribute('draggable', 'true');

        folderEl.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', JSON.stringify({
                id: folderEl.dataset.folderId,
                type: 'folder'
            }));
        });

        folderEl.addEventListener('dragenter', handleDragEnter);
        folderEl.addEventListener('dragover', handleDragOver);
        folderEl.addEventListener('dragleave', handleDragLeave);
        folderEl.addEventListener('drop', handleDrop);
    });
}

document.getElementById('uploadFilesBtn').addEventListener('click', async (e) => {
    e.preventDefault();

    const fileInput = document.getElementById('fileInput');
    const folderInput = document.getElementById('folderInput');

    const files = fileInput.files.length > 0 ? fileInput.files : folderInput.files;

    if (!files || files.length === 0) {
        showMessage('Выберите файлы или папки для загрузки', 'warning');
        return;
    }

    const formData = new FormData();

    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i], files[i].webkitRelativePath || files[i].name);
    }

    const paths = [];
    for (let i = 0; i < files.length; i++) {
        paths.push(files[i].webkitRelativePath || files[i].name);
    }
    formData.append('paths', JSON.stringify(paths));

    formData.append('directory_id', currentDirectoryId);

    try {
        const res = await fetch('/CloudStorageApp/public/files/upload', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        if (!res.ok) {
            const errorText = await res.text();
            console.error('Ошибка HTTP при загрузке файлов:', res.status, errorText);
            showMessage('Ошибка при загрузке файлов', 'danger');
            return;
        }

        const data = await res.json();

        if (data.success) {
            showMessage('Файлы успешно загружены', 'success');

            fileInput.value = '';
            folderInput.value = '';
            document.getElementById('selectedFiles').textContent = 'Файлы/папки не выбраны';

            await loadFolders();
        } else {
            showMessage(data.error || 'Ошибка при загрузке файлов', 'danger');
        }
    } catch (error) {
        console.error('Ошибка при загрузке файлов:', error);
        showMessage('Произошла ошибка при загрузке файлов', 'danger');
    }
});

async function unshareFile(fileId) {
    if (!confirm("Вы уверены, что хотите отказаться от доступа к этому файлу?")) {
        return;
    }

    try {
        const res = await fetch('/CloudStorageApp/public/files/unshare', {
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

let currentUserId = null;

async function showFileInfo(fileId) {
    try {
        const res = await fetch(`/CloudStorageApp/public/files/info/${fileId}`, {
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

        const file = data.file;
        const modalEl = document.getElementById('filePreviewModal');
        if (!modalEl) {
            alert('Модальное окно предпросмотра не найдено!');
            return;
        }
        const modalTitle = modalEl.querySelector('.modal-title');
        if (!modalTitle) {
            alert('Заголовок модального окна не найден!');
            return;
        }
        modalTitle.textContent = file.filename || file.name || '';

        const fileInfoDiv = modalEl.querySelector('#fileInfo');
        const filePreviewDiv = modalEl.querySelector('#filePreview');
        const downloadBtn = document.getElementById('downloadBtn');
        const shareBtn = document.getElementById('shareInModalBtn');
        const deleteBtn = document.getElementById('deleteInModalBtn');

        if (fileInfoDiv) fileInfoDiv.innerHTML = `
            <p><strong>Имя файла:</strong> ${file.filename || file.name || ''}</p>
            <p><strong>Тип файла:</strong> ${file.mime_type || 'неизвестно'}</p>
            <p><strong>Размер:</strong> ${formatFileSize(file.file_size || file.size)}</p>
        `;
        if (filePreviewDiv) {
            if (file.mime_type && file.mime_type.startsWith('image/')) {
                filePreviewDiv.innerHTML = `<img src="/CloudStorageApp/public/files/download/${file.id}?inline=1" 
                      alt="${file.name}" 
                      style="max-width: 100%;">`;
            } else if (file.mime_type === 'application/pdf') {
                filePreviewDiv.innerHTML = `<iframe src="/CloudStorageApp/public/files/download/${file.id}?inline=1" width="100%" height="600px" style="border:none"></iframe>`;
            } else if (
                file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
                file.mime_type === 'application/msword'
            ) {
                filePreviewDiv.innerHTML = `
                    <div class="alert alert-warning mb-2">
                        Предпросмотр docx доступен только для публичных файлов.<br>
                        <a href="/CloudStorageApp/public/files/download/${file.id}" class="btn btn-primary mt-2" download>Скачать файл</a>
                    </div>
                `;
            } else {
                filePreviewDiv.innerHTML = '<div class="text-center text-muted">Нет предпросмотра для этого типа файла</div>';
            }
        }
        if (downloadBtn) {
            downloadBtn.href = `/CloudStorageApp/public/files/download/${fileId}`;
            downloadBtn.download = file.name;
        }
        if (shareBtn) {
            shareBtn.dataset.fileId = fileId;
            shareBtn.dataset.fileName = file.name || file.filename || '';
        }
        const isOwner = file.user_id === currentUserId;

        if (deleteBtn) {
            if (isOwner) {
                deleteBtn.textContent = 'Удалить';
                deleteBtn.onclick = async () => {
                    if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;
                    try {
                        const res = await fetch(`/CloudStorageApp/public/files/remove/${fileId}`, {
                            method: 'DELETE',
                            credentials: 'include'
                        });
                        const result = await res.json();
                        if (result.success) {
                            window.filePreviewModalInstance.hide();
                            showMessage('Файл успешно удалён', 'success');
                            await loadFiles();
                        } else {
                            alert(result.error || 'Ошибка при удалении файла');
                        }
                    } catch (e) {
                        alert('Произошла ошибка при удалении файла');
                    }
                };
            } else {
                deleteBtn.textContent = 'Отказаться от доступа';
                deleteBtn.onclick = async () => {
                    if (!confirm('Вы уверены, что хотите отказаться от доступа к этому файлу?')) return;
                    try {
                        const res = await fetch('/CloudStorageApp/public/files/unshare', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ file_id: fileId }),
                            credentials: 'include'
                        });
                        const result = await res.json();
                        if (result.success) {
                            window.filePreviewModalInstance.hide();
                            showMessage('Доступ к файлу успешно отозван', 'success');
                            await loadFiles();
                        } else {
                            alert(result.error || 'Ошибка при отзыве доступа');
                        }
                    } catch (e) {
                        alert('Произошла ошибка при отзыве доступа');
                    }
                };
            }
        }

        window.filePreviewModalInstance.show();

    } catch (error) {
        console.error('Ошибка при получении информации о файле:', error);
        alert('Произошла ошибка при получении информации о файле');
    }
}

document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', () => {
    const safeElement = document.getElementById('userGreeting') || document.body;
    if (safeElement) {
        safeElement.setAttribute('tabindex', '-1');
        safeElement.focus();
        safeElement.removeAttribute('tabindex');
    }

});

function getTileIconHtml(file) {
    if (file.mime_type === 'application/pdf') {
        return '<i class="fa fa-file-pdf-o fa-3x text-danger"></i>';
    }
    if (file.mime_type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ||
        file.mime_type === 'application/msword') {
        return '<i class="fa fa-file-word-o fa-3x text-primary"></i>';
    }
    if (file.mime_type && file.mime_type.startsWith('image/')) {
        return `<img src="/CloudStorageApp/public/download/${file.id}?inline=1" style="max-width:48px;max-height:48px;" alt="preview">`;
    }
    return '<i class="fa fa-file-o fa-3x text-secondary"></i>';
}

function formatFileSize(bytes) {
    if (!bytes || isNaN(bytes)) return 'неизвестно';
    if (bytes < 1024) return bytes + ' Б';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
    return (bytes / (1024 * 1024)).toFixed(2) + ' МБ';
}
