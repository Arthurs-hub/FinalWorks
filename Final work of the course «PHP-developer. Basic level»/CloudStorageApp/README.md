# CloudStorageApp

Полнофункциональное веб-приложение для облачного хранения файлов с возможностью создания папок, загрузки файлов, управления доступом, администрирования пользователей и системой сброса паролей.

## Описание проекта

CloudStorageApp - это современное решение для хранения и управления файлами в облаке. Приложение предоставляет пользователям возможность:

- Регистрации и авторизации в системе
- Создания иерархической структуры папок
- Загрузки файлов различных форматов
- Управления доступом к файлам (приватные/полученные от другого пользователя)
- Предварительного просмотра файлов
- Скачивания и обмена файлами
- Администрирования пользователей (для админов)
- Ведения логов активности
- Сброса паролей через email** (новая функция)

## Системные требования

### Обязательные требования

- **PHP**: версия 7.4 или выше
- **MySQL**: версия 5.7 или выше / **MariaDB**: версия 10.3 или выше
- **Веб-сервер**: Apache 2.4+ или Nginx 1.18+
- **SMTP сервер**: для отправки email уведомлений (опционально)

### Необходимые расширения PHP

- `pdo` - для работы с базой данных
- `pdo_mysql` - драйвер MySQL для PDO
- `mbstring` - для работы с многобайтовыми строками
- `fileinfo` - для определения типов файлов
- `gd` или `imagick` - для работы с изображениями (опционально)
- `json` - для работы с JSON данными
- `session` - для управления сессиями
- `filter` - для валидации данных
- `openssl` - для генерации токенов сброса паролей
- `curl` - для отправки HTTP запросов (опционально)

### Рекомендуемые настройки PHP

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
max_input_vars = 3000
```

## Установка и настройка

### 1. Загрузка проекта

```bash
# Скачайте архив проекта или клонируйте репозиторий
git clone https://gitlab.skillbox.ru/artur_zelenco/php-developer-base/-/tree/dev13/Final%20work%20the%20course%20%C2%ABPHP-developer.%20Basic%20level%C2%BB
cd CloudStorageApp
```

### 2. Создание и настройка базы данных

#### Автоматическое создание структуры БД

```bash
# Выполните эту команду для создания базы данных и всех таблиц  (PowerShell или CMD). Например, если приложение у вас расположено по этому же адресу, то в CMD вместо YourUserName впишите Ваше имя пользователя и выполните эту команду:
mysql -u YourUserName -p <C:\xampp\htdocs\welcome\CloudStorageApp\database.sql
# Далее введите свой пароль и нажмите Enter:
Enter password: ***********
# Всё, Ваша база данных создана!
```

Эта команда автоматически:

- Создаст базу данных `cloud_storage`
- Создаст все необходимые таблицы (`directories`, `files`, `shared_items`, `users`, `password_reset_tokens`)
- Настроит все связи и индексы
- Установит правильную кодировку UTF-8

#### Проверка успешного создания

**Из командной строки системы:**

```bash
mysql -u root -p -e "USE cloud_storage; SHOW TABLES;"
```

**Или из командной строки MySQL:**

```bash
# Войдите в MySQL
mysql -u root -p

# В консоли MySQL выполните:
USE cloud_storage;
SHOW TABLES;
```

Должны отобразиться таблицы:

```bash
+-------------------------+
| Tables_in_cloud_storage |
+-------------------------+
| directories             |
| files                   |
| password_reset_tokens   |
| shared_items            |
| users                   |
+-------------------------+
```

#### Альтернативный способ - пошаговое создание

## Войдите в MySQL

```bash
mysql -u root -p
```

## В консоли MySQL выполните

source /path/to/your/project/database.sql

## Или скопируйте команды из database.sql

### Проверьте, что база данных и таблицы созданы

```bash
mysql -u root -p -e "USE cloud_storage; SHOW TABLES;"
```

### 3. Настройка конфигурации

Отредактируйте файл `config/config.php`:

```php
<?php
return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'cloud_storage',
        'username' => 'your_db_username',
        'password' => 'your_db_password',
        'charset' => 'utf8mb4'
    ],
    'app' => [
        'name' => 'CloudStorageApp',
        'url' => 'http://localhost',
        'upload_path' => __DIR__ . '/../uploads/',
        'max_file_size' => 104857600, // 100MB в байтах
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'],
        'timezone' => 'Europe/Moscow'
    ],
    'security' => [
        'session_lifetime' => 3600, // 1 час
        'password_min_length' => 6,
        'max_login_attempts' => 5
    ],
    'email' => [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_username' => 'your_email@gmail.com',
        'smtp_password' => 'your_app_password',
        'smtp_secure' => 'tls',
        'from_email' => 'your_email@gmail.com',
        'from_name' => 'CloudStorageApp'
    ]
];
```

### 4. Настройка email (опционально)

Для работы функции сброса паролей настройте SMTP в `config/config.php`:

#### Для Gmail (рекомендуется работает со всеми почтовыми сервисами)

1. Включите двухфакторную аутентификацию
2. Создайте пароль приложения
3. Используйте настройки выше

#### Для других провайдеров

- **Yandex**: smtp.yandex.ru, порт 587
- **Mail.ru**: smtp.mail.ru, порт 465
- **Outlook**: smtp-mail.outlook.com, порт 587

### 5. Настройка веб-сервера

#### XAMPP (рекомендуется)

Файл `.htaccess` уже настроен в папке `public/`.

**Установка и настройка:**

1. Поместите проект в папку `C:\xampp\htdocs\welcome\`
2. Внесите или измените конфигурацию в файле httpd-vhosts.conf в папке `C:\xampp\apache\conf\extra\`и если нужно в файле httpd.conf в папке C:\xampp\apache\conf\ следующим содержимым:

    ```apache
    <VirtualHost *:8080>
         DocumentRoot "C:/xampp/htdocs/welcome/CloudStorageApp/public"
         ServerName localhost
         <Directory "C:/xampp/htdocs/welcome/CloudStorageApp/public">
              AllowOverride All
              Require all granted
         </Directory>
    </VirtualHost>

   ```

3. Запустите Apache и MySQL в панели управления XAMPP
4. Откройте в браузере: `http://localhost:8080/login.html`

**Структура должна быть:**

C:\xampp\htdocs\
├── phpmyadmin\
└── welcome\
    └── CloudStorageApp\
        ├── public\
        │   └── index.php
        ├── database.sql
        └── README.md

#### Альтернативные пути размещения

Если хотите разместить проект в корне htdocs:

C:\xampp\htdocs\
├── phpmyadmin\
└── CloudStorageApp\
    ├── public\
    └── ...

Внесите или измените конфигурацию в файле httpd-vhosts.conf в папке `C:\xampp\apache\conf\extra\`и если нужно в файле httpd.conf в папке `C:\xampp\apache\conf\` следующим содержимым:

```apache

    <VirtualHost *:8080>
         DocumentRoot "C:/xampp/htdocs/CloudStorageApp/public"
         ServerName localhost
         <Directory "C:/xampp/htdocs/CloudStorageApp/public">
              AllowOverride All
              Require all granted
         </Directory>
    </VirtualHost>

```

Тогда адрес тоже будет: `http://localhost:8080/login.html`

#### Требования к веб-серверу

- PHP 7.4 или выше
- Модуль `mod_rewrite` включен
- Поддержка `.htaccess` файлов
- MySQL/MariaDB через phpMyAdmin

#### Альтернативные локальные серверы

Если используете другие локальные серверы (WAMP, MAMP, Laragon):

- Поместите проект в соответствующую папку (`www`, `htdocs`)
- Убедитесь, что Apache и MySQL запущены
- Откройте `http://localhost/login.html`

### Краткие требования к веб-серверу

- PHP 7.4 или выше
- Модуль `mod_rewrite` включен
- Поддержка `.htaccess` файлов
- MySQL/MariaDB

### 6. Проверка установки

Откройте браузер и перейдите по адресу: `http://localhost:8080/login.html`

## Структура проекта

CloudStorageApp/
├── App/
│   ├── Core/
│   │   └── Logger.php          # Система логирования
│   ├── Repositories/
│   │   ├── IUserRepository.php # Интерфейс репозитория пользователей
│   │   └── ...
│   └── Services/
│       ├── IUserService.php    # Интерфейс сервиса пользователей
│       └── ...
├── Controllers/
│   ├── AdminController.php     # Контроллер администрирования
│   ├── AuthController.php      # Контроллер аутентификации
│   ├── BaseController.php      # Базовый контроллер
│   ├── DirectoryController.php # Контроллер управления папками
│   ├── FileController.php      # Контроллер управления файлами
│   └── UserController.php      # Контроллер пользователей
├── Core/
│   ├── App.php                 # Основной класс приложения
│   ├── AuthMiddleware.php      # Middleware аутентификации
│   ├── BaseController.php      # Базовый контроллер
│   ├── Db.php                  # Класс для работы с БД
│   ├── Logger.php              # Логгер
│   ├── Repository.php          # Базовый репозиторий
│   ├── Request.php             # Класс HTTP запроса
│   ├── Response.php            # Класс HTTP ответа
│   ├── Router.php              # Маршрутизатор
│   └── Validator.php           # Базовый валидатор
├── Repositories/
│   ├── AdminRepository.php     # Админский репозиторий
│   ├── DirectoryRepository.php # Репозиторий папок
│   ├── FileRepository.php      # Репозиторий файлов
│   ├── PasswordResetRepository.php # Репозиторий сброса паролей
│   └── UserRepository.php      # Репозиторий пользователей
├── Services/
│   ├── AdminService.php        # Сервис администрирования
│   ├── AuthService.php         # Сервис аутентификации
│   ├── DirectoryService.php    # Сервис управления папками
│   ├── EmailService.php        # Сервис отправки email
│   ├── FileService.php         # Сервис управления файлами
│   └── UserService.php         # Сервис пользователей
├── Validators/
│   ├── AuthValidator.php       # Валидатор аутентификации
│   └── DirectoryValidator.php  # Валидатор папок
├── config/
│   └── config.php              # Конфигурация приложения
├── public/
│   ├── css/                    # CSS стили
│   ├── js/                     # JavaScript файлы
│   ├── .htaccess               # Конфигурация Apache
│   ├── Admins.html              # Шаблон администратора
|   ├── CloudIcon.png           # Иконка входа в CloudStorageApp
│   ├── CoveringCloudIcon.png   # Анимация
│   ├── Elibrary.png            # Иконка страницы Мои файлы
│   ├── index.php               # Точка входа
│   ├── login.html              # Шаблон входа
│   ├── reset-password.html     # Страница сброса пароля
|   └── upload.html             # Страница пользователей
├── uploads/                    # Папка для загруженных файлов
│   ├── files/                  # Подпапка для файлов
│   └── folders/                # Подпапка для папок
├── logs/                       # Папка для логов (уже создана)
├── database.sql                # SQL скрипты для создания БД
└── README.md                   # Документация проекта

## Использование системы

### Регистрация нового пользователя

Для регистрации необходимо заполнить следующие обязательные поля (отмечены красной звездочкой в пользовательском интерфейсе):

- **Имя** - имя пользователя
- **Фамилия** - фамилия пользователя  
- **Email** - адрес электронной почты
- **Пароль** - пароль для входа в систему
- **Повторите пароль** - подтверждение пароля

### Авторизация

Для входа в систему используйте:

- **Email** - адрес электронной почты, указанный при регистрации
- **Пароль** - пароль, установленный при регистрации

### Сброс пароля (НОВАЯ ФУНКЦИЯ)

Если вы забыли пароль:

1. На странице входа нажмите "Забыли пароль?"
2. Введите ваш email адрес
3. Проверьте почту - вам придет письмо со ссылкой для сброса
4. Перейдите по ссылке и установите новый пароль
5. Войдите в систему с новым паролем

**Важно:** Ссылка действительна в течение 1 часа.

### Получение прав администратора

Для получения доступа к административной панели:

### 1. Назначьте пользователю роль администратора в базе данных или командной строке SQL

```sql
UPDATE users SET role = 'admin', is_admin = 1 WHERE email = your@email.com;
UPDATE users SET role = 'admin', is_admin = 1 WHERE id = userID;
```

### 2. На странице входа login.html очистите кеш и куки браузера (CTRL+SHIFT+R)

### 3. Войдите в систему с теми же логином и паролем с которыми вы зарегистрировались в качестве пользователя

### 4. Теперь у вас будет доступ к панели администратора

## API Роуты и функциональность

**⚠️ Важно:** Большинство роутов требуют авторизации. Сначала выполните POST /login для получения сессии.

---

## Роуты для пользователей (роль: user)

## Регистрация и авторизация

`base_url`: `http://localhost:8080/login.html`

**POST /register**  
Регистрация нового пользователя

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "first_name": "Иван",
    "middle_name": "Иванович",
    "last_name": "Иванов", 
    "gender": "male",
    "age": 25,
    "email": "ivan@example.com",
    "password": "secure_password"
}

{
    "first_name": "Артем",
    "middle_name": "Артемович",
    "last_name": "Артемов", 
    "gender": "male",
    "age": 27,
    "email": "artiom@example.com",
    "password": "secure_password1"
}

{
    "first_name": "Алексей",
    "middle_name": "Алексеевич",
    "last_name": "Алексеев", 
    "gender": "male",
    "age": 29,
    "email": "alexey@example.com",
    "password": "secure_password2"
}
```

**POST /users/login**  
Авторизация пользователя

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "ivan@example.com",
    "password": "secure_password"
}
```

**GET /users/logout**  
Выход из системы (требует авторизации)

---

## Новые роуты для сброса пароля

**POST /users/reset_password**  
Запрос на сброс пароля через email

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "user@example.com"
}
```

**POST /users/password-reset-validate**  
Проверка валидности токена сброса

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "token": "your_reset_token_here"
}
```

**POST /users/password-reset-confirm**  
Подтверждение сброса пароля с новым паролем

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "token": "your_reset_token_here",
    "password": "new_secure_password",
    "confirm_password": "new_secure_password"
}
```

---

## Управление пользователями (для пользователей)

**GET /users/list**  
Получить список пользователей

**GET /users/get/{id}**  
Получить информацию о пользователе по ID

**PUT /users/update**  
Обновить данные текущего пользователя

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{ 
    "first_name": "Новое имя", 
    "last_name": "Новая фамилия", 
    "email": "new@example.com", 
    "old_password": "", 
    "new_password": "", 
    "confirm_new_password": "" 
}

Описание полей:
- `first_name` — новое имя пользователя.
- `last_name` — новая фамилия пользователя.
- `email` — новый email пользователя.
- `old_password` — текущий пароль (необязательное поле, требуется только при смене пароля).
- `new_password` — новый пароль (необязательное поле).
- `confirm_new_password` — подтверждение нового пароля (необязательное поле).

Если поля для смены пароля (`old_password`, `new_password`, `confirm_new_password`) не переданы, пароль останется без изменений.  
Для успешного изменения пароля необходимо указать все три поля, при этом `new_password` и `confirm_new_password` должны совпадать, а `old_password` должен быть корректным текущим паролем пользователя.

```

---

## Управление файлами

**POST /files/add**  
Добавить файл (алиас для upload)

**Content-Type:** `multipart/form-data`

**Параметры формы:**

- `files[]` - массив файлов для загрузки (обязательный)
- `directory_id` - ID папки назначения (по умолчанию "root")
- `paths` - JSON строка с относительными путями для создания структуры папок (необязательный)

**GET /files/list**  
Получить список файлов и папок

**GET /files/get/{id}**  
Получить информацию о файле по ID

**PUT /files/rename**  
Переименовать файл

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "file_id": 123,
    "new_name": "Новое имя файла"
}
```

**DELETE /files/remove/{id}**  
Удалить файл по ID

---

## Управление папками

**POST /directories/add**  
Создать папку

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "name": "Новая папка",
    "parent_id": "root"
}
```

**PUT /directories/rename**  
Переименовать папку

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "id": 123,    
    "new_name": "Новое имя папки"  
}
```

**GET /directories/get/{id}**  
Получить информацию о папке и её содержимом

**DELETE /directories/delete/{id}**  
Удалить папку по ID

---

## Расшаривание файлов

**PUT /files/share/{id}/{user_id}**  
Расшарить файл конкретному пользователю

**GET /files/share/{id}**  
Получить информацию о расшаривании файла

**DELETE /files/share/{id}/{user_id}**  
Убрать расшаривание файла с конкретного пользователя

---

## Дополнительные файловые роуты (расширенная функциональность)

**POST /files/upload**  
Загрузить файл или несколько файлов

**Content-Type:** `multipart/form-data`

**Параметры формы:**

- `files[]` - массив файлов для загрузки (обязательный)
- `directory_id` - ID папки назначения (по умолчанию "root")
- `paths` - JSON строка с относительными путями для создания структуры папок (необязательный)

### Пример 1: Загрузка одного или нескольких файлов в корневую папку

## POST /files/upload

Content-Type: multipart/form-data

Form data:

files[]: document.pdf
directory_id: root

### Пример 2: Загрузка файлов с созданием структуры папок

## POST /files/upload (структурированная загрузка)

Content-Type: multipart/form-data

Form data:

files[]: project/docs/readme.txt
files[]: project/images/logo.png
files[]: project/src/main.js
directory_id: root
paths: ["project/docs/readme.txt", "project/images/logo.png", "project/src/main.js"]

**Пример настройки form-data в Postman:**

```text
Key: files[]        Type: File      Value: [Select Files]
Key: directory_id   Type: Text      Value: root
Key: paths          Type: Text      Value: ["docs/readme.txt", "images/logo.png"]
```

**Структура JSON для параметра `paths`:**

```json
[
    "folder1/subfolder1/file1.txt",
    "folder1/subfolder2/file2.jpg",
    "folder2/file3.pdf"
]
```

**Ограничения:**

- Максимальный размер файла: 50MB
- Поддерживаемые форматы: jpg, jpeg, png, gif, pdf, doc, docx, txt, zip, rar
- Максимальное количество файлов за один запрос: 20

**Ответ при успехе (200 OK):**

```json
{
    "success": true,
    "message": "Загружено 3 из 3 файлов",
    "results": [
        {
            "file": "document.pdf",
            "success": true,
            "file_id": 123
        },
        {
            "file": "photo.jpg",
            "success": true,
            "file_id": 124
        },
        {
            "file": "large_file.zip",
            "success": false,
            "error": "Файл слишком большой (максимум 50MB)"
        }
    ],
    "total": 3,
    "success_count": 2
}
```

---

## Дополнительные роуты папок (расширенная функциональность)

**POST /directories/share**  
Расшарить папку пользователю

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123,
    "email": "artiom@example.com"
}
```

### POST /directories/unshare

1) Отменить расшаривание папки владельцем

2) Отказаться от доступа к папке полученной от другого пользователя

**⚠️ Важно: нужно авторизоваться в аккаунте пользователя получившего папку от другого пользователя

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123
}
```

**PUT /directories/move**  
Переместить папку

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123,
    "target_parent_id": 456
}
```

**GET /directories/download/{id}**  
Скачать папку как архив

**GET /directories/list**  
Получить список всех папок пользователя

---

## Роуты для администраторов (роль: admin)

## Авторизация и назначение администратора

**POST /users/create-first-admin**  
Назначение первого администратора (публичный эндпоинт для тестирования)

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "ivan@example.com"
}
```

**POST /users/login**  
Авторизация администратора (тот же эндпоинт, что и для пользователей)

```json
{
    "email": "ivan@example.com",
    "password": "secure_password"
}
```

---

## Управление пользователями

**⚠️ Обязательно: нужно заново авторизоваться в аккаунте администратора

**GET /admin/users/list**  
Получить список всех пользователей

**GET /admin/users/get/{id}**  
Получить подробную информацию о пользователе по ID

**PUT /admin/users/update/{id}**  
Обновить данные пользователя

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "first_name": "Новое имя",
    "last_name": "Новая фамилия",
    "email": "new@example.com",
    "role": "user"
}
```

**DELETE /admin/users/delete/{id}**  
Удалить пользователя по ID

---

## Дополнительные админские роуты (расширенная функциональность)

**GET /admin/stats**  
Получить статистику системы

**POST /admin/users/{id}/make-admin**  
Администратор назначает другого пользователя тоже администратором

**PATCH /admin/users/{id}/remove-admin**  
Отозвать права с другого администратора

**⚠️ ВАЖНО:** Чтобы снять права с первого администратора, сначала назначьте другого пользователя администратором, авторизуйтесь под его аккаунтом, и потом отзовите права первого администратора.

**DELETE /admin/users/bulk-delete**  
Массовое удаление пользователей

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "user_ids": [1, 2, 3, 4]
}
```

## Управление файлами и папками

**GET /admin/files/list**  
Получить список всех файлов в системе

**DELETE /admin/files/{id}**  
Удалить любой файл по ID (админский доступ)

**DELETE /admin/directories/delete/{id}**  
Удалить любую папку по ID (админский доступ)

**DELETE /admin/files/cleanup**  
Очистка неиспользуемых файлов

**DELETE /admin/files/clear**  
Удалить все файлы в системе

## Системные функции

**GET /admin/logs**  
Получить системные логи

```text
Query параметры:
- level: all|info|warning|error (по умолчанию: all)
- limit: количество записей (по умолчанию: 100)
```

**DELETE /admin/logs/clear**  
Очистить все логи за предыдущие дни

**GET /admin/system/health**  
Получить информацию о состоянии системы

**GET /admin/security/report**  
Получить отчет по безопасности

---

### Типичные ошибки и их решения

#### 1. Ошибка 401 "Пользователь не авторизован"

**Причина:** Отсутствуют cookies авторизации  
**Решение:** Выполните POST /users/login и убедитесь, что cookies сохранились

#### 2. Ошибка 403 "Недостаточно прав"

**Причина:** Пытаетесь получить доступ к админскому эндпоинту без прав администратора  
**Решение:** Используйте `/users/create-first-admin` для назначения прав

#### 3. Ошибка 404 "Пользователь не найден"

**Причина:** Пытаетесь назначить администратором несуществующего пользователя  
**Решение:** Сначала зарегистрируйте пользователя через `/users/register`

#### 4. Получение HTML вместо JSON

**Причина:** Неправильный URL или метод запроса  
**Решение:** Проверьте правильность URL и HTTP метода

#### 5. Ошибка "Администратор уже существует"

**Причина:** В системе уже есть администратор  
**Решение:** Используйте `/remove-admin` для сброса или авторизуйтесь существующим админом

#### 6. Ошибки сброса пароля

**"Недействительный или просроченный токен"**  
**Причина:** Токен истек (срок действия 1 час) или уже использован  
**Решение:** Запросите новый токен через `/users/password-reset-request`

**"Email не отправляется"**  
**Причина:** Неправильные настройки SMTP  
**Решение:** Проверьте настройки email в `config/config.php`

**"Токен не найден в базе данных"**  
**Причина:** Проблемы с сохранением токена  
**Решение:** Проверьте таблицу `password_reset_tokens` в базе данных

---

### Контакты для поддержки

При возникновении проблем с тестированием:

1. Проверьте логи в папке `/logs/`
2. Убедитесь, что база данных настроена корректно
3. Проверьте права доступа к папкам `/uploads/` и `/logs/`
4. Для проблем с email проверьте настройки SMTP

## Безопасность

### Реализованные меры безопасности

- **Хеширование паролей**: Использование password_hash() для хранения паролей
- **Валидация файлов**: Проверка типов и размеров загружаемых файлов
- **SQL Injection защита**: Использование подготовленных запросов
- **XSS защита**: Экранирование пользовательского ввода
- **Ограничение доступа**: Middleware для проверки авторизации
- **Логирование**: Ведение журнала всех действий пользователей
- **Токены сброса паролей**: Безопасные одноразовые токены с ограниченным сроком действия

### Новые меры безопасности

- **Временные токены**: Токены сброса паролей действуют только 1 час
- **Одноразовые токены**: Каждый токен можно использовать только один раз
- **Очистка токенов**: Автоматическое удаление просроченных токенов
- **Валидация email**: Проверка существования пользователя перед отправкой
- **Безопасная генерация**: Использование криптографически стойких генераторов

## Устранение неполадок

### Проблемы с загрузкой файлов

I. **Проверьте права доступа к папкам:**

```bash
chmod 755 uploads/
chmod 755 uploads/files/
chmod 755 uploads/folders/
```

II. **Увеличьте лимиты PHP в `php.ini`:**

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
```

III. **Проверьте, что папки существуют:**

```bash
mkdir -p uploads/files
mkdir -p uploads/folders
mkdir -p logs
```

IV. **Проверьте настройки веб-сервера:**

- Убедитесь, что Apache/Nginx имеет доступ к папке uploads
- Проверьте, что .htaccess файл не блокирует загрузку

### Проблемы с базой данных

**Сбой подключения к базе данных** → Проверьте подключение:

```bash
mysql -u username -p -h localhost database_name
```

**Нет таблиц в базе данных** → Убедитесь, что все таблицы созданы:

```sql
SHOW TABLES;
-- Должны быть: directories, files, password_reset_tokens, shared_items, users
```

**Отсутствует таблица password_reset_tokens** → Создайте её:

```sql
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);
```

### Проблемы с email

**Email не отправляется** → Проверьте настройки SMTP:

```php
// В config/config.php
'email' => [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@gmail.com',
    'smtp_password' => 'your_app_password', 
    'smtp_secure' => 'tls',
    'from_email' => 'your_email@gmail.com',
    'from_name' => 'CloudStorageApp'
]
```

**Для Gmail:**

1. Включите двухфакторную аутентификацию
2. Создайте пароль приложения в настройках Google
3. Используйте пароль приложения, а не обычный пароль

**Ошибки SMTP** → Проверьте логи:

```bash
# Посмотрите PHP логи
tail -f /var/log/php_errors.log

# Или логи приложения
tail -f logs/app_YYYY-MM-DD.log
```

V. **Проверьте логи ошибок:**

```bash
# Посмотрите логи PHP (заранее перейдя по адресу папки логов вебсервера или окружения PHP)
#Unix/Linux: 
tail -f /var/log/php_errors.log
#Windows PowerShell or Command Prompt:
Get-Content -Path .\logs\php_errors.log 

# Или логи приложения (заранее перейдя по адресу приложения)
#Unix/Linux:
tail -f logs/app_YYYY-MM-DD.log
#Windows PowerShell or Command Prompt:
Get-Content -Path .\logs\app_YYYY-MM-DD.log (например: app_2025-07-10.log)
```

VI. **Типичные ошибки и решения:**

```text
**"Файл слишком большой"** → увеличьте `upload_max_filesize`

**"Превышено время выполнения"** → увеличьте `max_execution_time`

**"Недостаточно памяти"** → увеличьте `memory_limit`

**"Папка не найдена"** → проверьте права доступа и существование папок

**"SMTP Authentication failed"** → проверьте логин/пароль SMTP

**"Connection refused"** → проверьте хост и порт SMTP

**"Токен не найден"** → проверьте таблицу password_reset_tokens
```

### Проблемы с правами доступа

```bash
chmod -R 755 CloudStorageApp/
chmod -R 777 uploads/
chmod -R 777 logs/
```

## FAQ (Часто задаваемые вопросы)

### Q: Как назначить пользователя администратором?

A: Выполните SQL запрос:

```sql
UPDATE users SET role = 'admin', is_admin = 1 WHERE email = your@email.com;
UPDATE users SET role = 'admin', is_admin = 1 WHERE id = userID;
```

Затем пользователь должен очистить кеш браузера и войти заново. (CTRL+SHIFT+R)

### Q: Какие поля обязательны при регистрации?

A: Имя, Фамилия, Email, Пароль и Повторите пароль (отмечены красной звездочкой в пользовательском интерфейсе).

### Q: Нужен ли Composer для работы приложения?

A: Да, для работы приложения необходим Composer, так как проект использует зависимости и автозагрузку по стандарту PSR-4. Для установки всех зависимостей выполните команду:

```bash
composer install
```

Это установит PHPMailer и другие необходимые библиотеки.

### Q: Какой максимальный размер файла?

A: По умолчанию ограничен настройками PHP. Можно изменить в `php.ini`.

### Q: Где хранятся загруженные файлы?

A: В папке `uploads/files/` в корне проекта.

### Q: Где хранятся загруженные папки?

A: В папке `uploads/folders/` в корне проекта.

### Q: Как работает сброс пароля?

A:

1. Пользователь запрашивает сброс через email
2. Система генерирует уникальный токен и сохраняет в БД
3. Токен отправляется на email пользователя
4. Пользователь переходит по ссылке и устанавливает новый пароль
5. Токен помечается как использованный

### Q: Сколько действует токен сброса пароля?

A: Токен действует 1 час с момента создания. После использования токен становится недействительным.

### Q: Что делать, если email не приходит?

A:

1. Проверьте папку "Спам"
2. Убедитесь, что настройки SMTP корректны
3. Проверьте логи приложения на наличие ошибок
4. Для Gmail используйте пароль приложения, а не обычный пароль

### Q: Как использовать новые роуты расшаривания?

A: Используйте:

- `PUT /files/share/{file_id}/{user_id}` - расшарить файл конкретному пользователю
- `DELETE /files/share/{file_id}/{user_id}` - убрать расшаривание
- `GET /files/share/{file_id}` - посмотреть, кому расшарен файл

### Q: Какие роуты требуют админских прав?

A: Все роуты начинающиеся с `/admin/`:

- `/admin/users/list`
- `/admin/users/get/{id}`
- `/admin/users/update/{id}`
- `/admin/users/delete/{id}`
- `/admin/stats`
- `/admin/files`
- `/admin/logs`

### Q: Нужно ли устанавливать PHPMailer?

A: Для работы email функций рекомендуется установить PHPMailer:

```bash
composer require phpmailer/phpmailer
```

Или скачать вручную и поместить в папку `vendor/`.

## Примеры использования новых роутов

### Пример 1: Работа с пользователями

```bash
# Получить список пользователей
GET /users/list

# Получить конкретного пользователя
GET /users/get/123

# Обновить свои данные
PUT /users/update
{
    "first_name": "Новое имя",
    "email": "new@email.com"
}
```

### Пример 2: Сброс пароля (полный цикл)

```bash
# 1. Запросить сброс пароля
POST /users/password-reset-request
{
    "email": "user@example.com"
}

# 2. Проверить токен (получен из email)
POST /users/password-reset-validate
{
    "token": "abc123def456"
}

# 3. Установить новый пароль
POST /users/password-reset-confirm
{
    "token": "abc123def456",
    "password": "new_password123",
    "confirm_password": "new_password123"
}
```

### Пример 3: Точное управление расшариванием

```bash
# Расшарить файл пользователю с ID 456
PUT /files/share/123/456

# Убрать расшаривание
DELETE /files/share/123/456

# Посмотреть, кому расшарен файл
GET /files/share/123
```

### Пример 4: Админские функции (новые роуты)

```bash
# Получить всех пользователей (админ)
GET /admin/users/list

# Получить пользователя (админ)
GET /admin/users/get/123

# Обновить пользователя (админ)
PUT /admin/users/update/123
{
    "first_name": "Новое имя",
    "role": "admin"
}

# Удалить пользователя (админ)
DELETE /admin/users/delete/123

# Получить статистику системы
GET /admin/stats

# Получить системные логи
GET /admin/logs?level=error&limit=50
```

### Пример 5: Работа с email уведомлениями

```bash
# Тестирование отправки email
POST /users/password-reset-request
{
    "email": "test@example.com"
}

# Ответ при успехе:
{
    "success": true,
    "message": "Ссылка для сброса пароля отправлена на ваш email"
}

# Ответ при ошибке SMTP:
{
    "success": false,
    "error": "Ошибка при отправке email"
}
```

## Новые возможности системы

### 1. Система сброса паролей

- **Безопасные токены**: Криптографически стойкие токены
- **Ограниченное время**: Токены действуют 1 час
- **Одноразовое использование**: Каждый токен можно использовать только один раз
- **Email уведомления**: Красивые HTML письма с инструкциями
- **Автоочистка**: Просроченные токены автоматически удаляются

### 2. Email сервис

- **SMTP поддержка**: Отправка через внешние SMTP серверы
- **HTML шаблоны**: Красивые email с брендингом
- **Безопасность**: Защищенные соединения TLS/SSL
- **Логирование**: Все email операции записываются в логи
- **Fallback**: Резервные методы отправки

### 3. Улучшенная безопасность

- **Валидация токенов**: Многоуровневая проверка токенов
- **Защита от спама**: Ограничения на частоту запросов
- **Аудит безопасности**: Детальное логирование всех операций
- **Очистка данных**: Автоматическое удаление устаревших данных

## Установка PHPMailer (рекомендуется)

### Через Composer (рекомендуется)

```bash
# В корне проекта выполните:
composer require phpmailer/phpmailer
```

### Ручная установка

1. Скачайте PHPMailer с GitHub: [https://github.com/PHPMailer/PHPMailer]

2. Распакуйте в папку `vendor/phpmailer/phpmailer/`
3. Структура должна быть:

```text
CloudStorageApp/
├── vendor/
│   └── phpmailer/
│       └── phpmailer/
│           ├── src/
│           └── ...
```

### Проверка установки

```php
// Создайте файл test_email.php в корне проекта:
<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
echo "PHPMailer установлен корректно!";
```

## Настройка email провайдеров

### Gmail

```php
'email' => [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@gmail.com',
    'smtp_password' => 'your_app_password', // Пароль приложения!
    'smtp_secure' => 'tls',
    'from_email' => 'your_email@gmail.com',
    'from_name' => 'CloudStorageApp'
]
```

### Yandex

```php
'email' => [
    'smtp_host' => 'smtp.yandex.ru',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@yandex.ru',
    'smtp_password' => 'your_password',
    'smtp_secure' => 'tls',
    'from_email' => 'your_email@yandex.ru',
    'from_name' => 'CloudStorageApp'
]
```

### Mail.ru

```php
'email' => [
    'smtp_host' => 'smtp.mail.ru',
    'smtp_port' => 465,
    'smtp_username' => 'your_email@mail.ru',
    'smtp_password' => 'your_password',
    'smtp_secure' => 'tls',
    'from_email' => 'your_email@mail.ru',
    'from_name' => 'CloudStorageApp'
]
```

### Outlook/Hotmail

```php
'email' => [
    'smtp_host' => 'smtp-mail.outlook.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@outlook.com',
    'smtp_password' => 'your_password',
    'smtp_secure' => 'tls',
    'from_email' => 'your_email@outlook.com',
    'from_name' => 'CloudStorageApp'
]
```

## Тестирование новых функций

### Тестирование сброса пароля

1. **Зарегистрируйте тестового пользователя**
2. **Настройте SMTP в config.php**
3. **Запросите сброс пароля:**

```bash
POST /users/password-reset-request
{
    "email": "test@example.com"
}
```

### 4. **Проверьте email (и папку спам)**

### 5. **Скопируйте токен из письма**

### 6. **Проверьте токен:**

```bash
POST /users/password-reset-validate
{
    "token": "your_token_here"
}
```

### 7. **Установите новый пароль:**

```bash
POST /users/password-reset-confirm
{
    "token": "your_token_here",
    "password": "new_password",
    "confirm_password": "new_password"
}
```

### Проверка базы данных

```sql
-- Проверьте таблицу токенов
SELECT * FROM password_reset_tokens;

-- Проверьте, что токены очищаются
SELECT COUNT(*) FROM password_reset_tokens WHERE expires_at < UNIX_TIMESTAMP();
```

## Мониторинг и обслуживание

### Регулярные задачи

1. **Очистка просроченных токенов** (можно настроить cron):

```bash
# Добавьте в crontab для ежедневной очистки:
0 2 * * * php /path/to/your/project/cleanup_tokens.php
```

### 2. **Мониторинг логов**

```bash
# Проверяйте логи на ошибки email:
grep -i "email\|smtp\|mail" logs/app_*.log
```

### 3. **Проверка дискового пространства**

```bash
# Следите за размером папки uploads:
du -sh uploads/
```

## Лицензия

Данный проект создан в учебных целях и предназначен для демонстрации навыков разработки.

## О проекте

Этот проект разработан как демонстрация навыков создания веб-приложений на PHP с использованием:

- Архитектуры MVC
- Паттернов Repository и Service
- Работы с базами данных MySQL
- Создания REST API
- Обеспечения безопасности веб-приложений
- Современных подходов к маршрутизации
- Безопасного сброса паролей
- SMTP интеграции

**CloudStorageApp v2.0** - Демонстрационное приложение облачного хранилища файлов с системой email уведомлений 📁✉️

*Проект создан для образовательных целей и демонстрации технических навыков.*
