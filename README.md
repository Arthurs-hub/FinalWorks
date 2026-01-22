# CloudStorageApp

Full-featured web application for cloud file storage with the ability to create folders, upload files, manage access, administer users and password reset system.

## Project Description

CloudStorageApp is a modern solution for storing and managing files in the cloud. The application provides users with the ability to:

- Register and authenticate in the system
- **🔐 Two-factor authentication** (Email codes and Google Authenticator)
- Create hierarchical folder structure
- Upload files of various formats
- Manage file access (private/received from another user)
- Preview files (images, PDF, **video**)
- **View video of any format** in modal window
- **Auto-play video in tile mode** (like in Viber)
- Download and share files
- User administration (for admins)
- Activity logging
- Password reset via email

## System Requirements

### Mandatory Requirements

- **PHP**: version 7.4 or higher
- **MySQL**: version 5.7 or higher / **MariaDB**: version 10.3 or higher
- **Web server**: Apache 2.4+ or Nginx 1.18+
- **SMTP server**: for sending email notifications (optional)

### Required PHP Extensions

- `pdo` - for database work
- `pdo_mysql` - MySQL driver for PDO
- `mbstring` - for working with multibyte strings
- `fileinfo` - for determining file types
- `gd` or `imagick` - for working with images (optional)
- `json` - for working with JSON data
- `session` - for session management
- `filter` - for data validation
- `openssl` - for generating password reset tokens
- `curl` - for sending HTTP requests (optional)

### Recommended PHP Settings

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
max_input_vars = 3000
```

## Installation and Configuration

### 1. Project Download

```bash
# Download project archive or clone repository
git clone https://gitlab.skillbox.ru/artur_zelenco/php-developer-base/-/tree/dev13/Final%20work%20the%20course%20%C2%ABPHP-developer.%20Basic%20level%C2%BB
cd CloudStorageApp
```

### 2. Database Creation and Configuration

#### Automatic DB Structure Creation

```bash
# Execute this command to create database and all tables (PowerShell or CMD). For example, if your application is located at this address, then in CMD instead of YourUserName enter your username and execute this command:
mysql -u YourUserName -p <C:\xampp\htdocs\welcome\CloudStorageApp\database.sql
# Then enter your password and press Enter:
Enter password: ***********
# Done, your database is created!
```

This command automatically:

- Creates `cloud_storage` database
- Creates all necessary tables (`directories`, `files`, `shared_items`, `users`, `password_reset_tokens`)
- **Creates 2FA tables** (`two_factor_codes`, `system_settings`, `two_factor_logs`, `trusted_devices`)
- **Adds 2FA fields** to `users` table
- Sets up all relationships and indexes
- Sets correct UTF-8 encoding

#### Successful Creation Check

**From system command line:**

```bash
mysql -u root -p -e "USE cloud_storage; SHOW TABLES;"
```

**Or from MySQL command line:**

```bash
# Enter MySQL
mysql -u root -p

# In MySQL console execute:
USE cloud_storage;
SHOW TABLES;
```

Should display tables:

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

#### Alternative Method - Step-by-step Creation

## Enter MySQL

```bash
mysql -u root -p
```

## In MySQL console execute

source /path/to/your/project/database.sql

## Or copy commands from database.sql

### Check that database and tables are created

```bash
mysql -u root -p -e "USE cloud_storage; SHOW TABLES;"
```

### 3. Configuration Setup

Edit `config/config.php` file:

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
        'max_file_size' => 104857600, // 100MB in bytes
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'mp4', 'avi', 'mkv', 'webm', 'mov', 'wmv', 'flv', '3gp', 'ts', 'asf', 'ogg'],
        'timezone' => 'Europe/Moscow'
    ],
    'security' => [
        'session_lifetime' => 3600, // 1 hour
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

### 4. Email Setup (optional)

For password reset functionality, configure SMTP in `config/config.php`:

#### For Gmail (recommended, works with all email services)

1. Enable two-factor authentication
2. Create app password
3. Use settings above

#### For Other Providers

- **Yandex**: smtp.yandex.ru, port 587
- **Mail.ru**: smtp.mail.ru, port 465
- **Outlook**: smtp-mail.outlook.com, port 587

### 5. Web Server Configuration

#### XAMPP (recommended)

`.htaccess` file is already configured in `public/` folder.

**Installation and setup:**

1. Place project in `C:\xampp\htdocs\welcome\` folder
2. Add or modify configuration in httpd-vhosts.conf file in `C:\xampp\apache\conf\extra\` folder and if needed in httpd.conf file in C:\xampp\apache\conf\ with following content:

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

3. Start Apache and MySQL in XAMPP control panel
4. Open in browser: `http://localhost:8080/login.html`

**Structure should be:**

C:\xampp\htdocs\
├── phpmyadmin\
└── welcome\
    └── CloudStorageApp\
        ├── public\
        │   └── index.php
        ├── database.sql
        └── README.md

#### Alternative Placement Paths

If you want to place project in htdocs root:

C:\xampp\htdocs\
├── phpmyadmin\
└── CloudStorageApp\
    ├── public\
    └── ...

Add or modify configuration in httpd-vhosts.conf file in `C:\xampp\apache\conf\extra\` folder and if needed in httpd.conf file in `C:\xampp\apache\conf\` with following content:

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

Then address will also be: `http://localhost:8080/login.html`

#### Web Server Requirements

- PHP 7.4 or higher
- `mod_rewrite` module enabled
- `.htaccess` file support
- MySQL/MariaDB via phpMyAdmin

#### Alternative Local Servers

If using other local servers (WAMP, MAMP, Laragon):

- Place project in corresponding folder (`www`, `htdocs`)
- Ensure Apache and MySQL are running
- Open `http://localhost/login.html`

### Brief Web Server Requirements

- PHP 7.4 or higher
- `mod_rewrite` module enabled
- `.htaccess` file support
- MySQL/MariaDB

### 6. Installation Check

Open browser and go to: `http://localhost:8080/login.html`

## Project Structure

CloudStorageApp/
├── App/
│   ├── Controllers/
│   │   ├── AdminController.php     # Administration controller
│   │   ├── AuthController.php      # Authentication controller
│   │   ├── BaseController.php      # Base controller
│   │   ├── DirectoryController.php # Folder management controller
│   │   ├── FileController.php      # File management controller
│   │   └── UserController.php      # User controller
│   ├── Core/
│   │   ├── App.php                 # Main application class
│   │   ├── AuthMiddleware.php      # Authentication middleware
│   │   ├── BaseController.php      # Base controller
│   │   ├── Container.php           # DI container
│   │   ├── Db.php                  # Database class
│   │   ├── Logger.php              # Logging system
│   │   ├── Repository.php          # Base repository
│   │   ├── Request.php             # HTTP request class
│   │   ├── Response.php            # HTTP response class
│   │   ├── Router.php              # Router
│   │   └── Validator.php           # Base validator
│   ├── Repositories/
│   │   ├── AdminRepository.php     # Admin repository
│   │   ├── DirectoryRepository.php # Folder repository
│   │   ├── FileRepository.php      # File repository
│   │   ├── IFileRepository.php     # File repository interface
│   │   ├── IPasswordResetRepository.php # Password reset interface
│   │   ├── IUserRepository.php     # User repository interface
│   │   ├── PasswordResetRepository.php # Password reset repository
│   │   └── UserRepository.php      # User repository
│   ├── Services/
│   │   ├── AdminService.php        # Administration service
│   │   ├── AuthService.php         # Authentication service
│   │   ├── DirectoryService.php    # Folder management service
│   │   ├── EmailService.php        # Email sending service
│   │   ├── FileResponseService.php # File sending service
│   │   ├── FileService.php         # File management service
│   │   ├── FileTypeService.php     # File type detection service
│   │   ├── IDirectoryService.php   # Folder service interface
│   │   ├── IEmailService.php       # Email service interface
│   │   ├── IFileService.php        # File service interface
│   │   ├── IUserService.php        # User service interface
│   │   └── UserService.php         # User service
│   ├── Utils/
│   │   └── FileUtils.php           # File utilities
│   ├── Validators/
│   │   ├── AuthValidator.php       # Authentication validator
│   │   └── DirectoryValidator.php  # Folder validator
│   ├── logs/                       # Logs folder
│   └── uploads/                    # Uploaded files folder
│       ├── files/                  # Files subfolder
│       └── folders/                # Folders subfolder
├── config/
│   └── config.php              # Application configuration
├── public/
│   ├── css/                    # CSS styles
│   │   ├── Admin.css           # Admin panel styles
│   │   ├── login.css           # Login page styles
│   │   ├── reset-password.css  # Password reset styles
│   │   ├── upload.css          # Main page styles
│   │   └── video-tiles.css     # Video tiles styles
│   ├── js/                     # JavaScript files
│   │   ├── admin.js            # Admin panel JavaScript
│   │   └── app.js              # Main JavaScript
│   ├── .htaccess               # Apache configuration
│   ├── Admins.html             # Administrator template
│   ├── CloudIcon.png           # CloudStorageApp login icon
│   ├── CoveringCloudIcon.png   # Animation
│   ├── ElibraryIcon.png        # My Files page icon
│   ├── index.php               # Entry point
│   ├── login.html              # Login template
│   ├── reset-password.html     # Password reset page
│   └── upload.html             # User page
├── database.sql                # SQL scripts for DB creation
└── README.md                   # Project documentation

## System Usage

### New User Registration

For registration, you need to fill in the following mandatory fields (marked with red asterisk in user interface):

- **First Name** - user's first name
- **Last Name** - user's last name  
- **Email** - email address
- **Password** - system login password
- **Repeat Password** - password confirmation

### Authorization

To log into the system use:

- **Email** - email address specified during registration
- **Password** - password set during registration

### Password Reset (NEW FEATURE)

If you forgot your password:

1. On login page click "Forgot password?"
2. Enter your email address
3. Check your email - you will receive a letter with reset link
4. Follow the link and set new password
5. Log into system with new password

**Important:** Link is valid for 1 hour.

### Video File Viewing (NEW FEATURE)

System supports viewing video of any format:

#### Supported Video Formats

- **MP4** (video/mp4) - recommended
- **AVI** (video/x-msvideo)
- **MKV** (video/x-matroska)
- **WebM** (video/webm)
- **MOV** (video/quicktime)
- **WMV** (video/x-ms-wmv)
- **FLV** (video/x-flv)
- **3GP** (video/3gpp)
- **TS** (video/mp2t)
- **ASF** (video/x-ms-asf)
- **OGG** (video/ogg)
- **MPEG** (video/mpeg)

#### Video Viewing Methods

1. **In tile mode** (auto-play):
   - Switch to "Tiles" view mode
   - Videos automatically play on hover
   - Like in Viber feed - without sound, with looping

2. **In modal window**:
   - Click on video tile or select "Preview" in menu
   - Video opens in modal window with controls
   - Built-in browser video player with full functionality

#### Video Player Features

- **Built into modal window** - like image preview
- **Standard browser controls** - familiar interface
- **Auto-load with muted sound** - no unexpected sounds
- **Adaptive size** - adjusts to window size
- **Playback error handling** - graceful degradation
- **File information** in modal window header

#### Technical Video Implementation Features

**Backend changes:**

- **isVideoFile() function** - video file detection by MIME type
- **Video preview** - built into modal window like images

**Frontend improvements:**

- **public/css/video-tiles.css** - styles for video in tile mode
- **Updated public/js/app.js** with auto-play functions
- **Updated public/js/admin.js** for video support in admin panel
- **Style connection** in upload.html and Admins.html

**JavaScript functions:**

- **isVideoFile(mimeType)** - video file detection
- **Canvas animation** - video frame rendering in tiles
- **Lazy video loading** for performance optimization
- **Auto-play with 300ms delay** (like in Viber)
- **Hover event handling** with timeouts

**Visual elements:**

- **"VIDEO" indicator** in bottom right corner of card
- **Animated play button** with pulsing effect
- **Canvas preview** - video frame display in tiles
- **Smooth transitions** and hover effects
- **Loading and error state handling**
- **Modal window** for video viewing

**Performance optimization:**

- **preload="metadata"** - only metadata loaded for Canvas
- **Canvas rendering** - frame display without full video loading
- **Playback stop** when cursor leaves
- **Return to beginning** of video when stopped
- **DOM initialization delay** (100ms) for stability
- **Error handling** with fallback to static icons

#### Video Usage Instructions

**For users:**

1. Upload video file via "Choose files" button
2. Switch to "Tiles" mode (grid icon button)
3. Hover cursor over video - it will automatically start playing
4. Click on video to open in modal window
5. Use menu (three dots) → "Preview" for quick access

**For administrators:**

1. Go to "Files" in admin panel side menu
2. Find video file in table
3. Click "eye" button (View file)
4. Video opens in modal window with controls

**Video controls:**

- Use **standard browser controls**
- **Pause/play** - play/pause button
- **Volume** - sound control
- **Fullscreen mode** - button in browser controls

**Auto-play features:**

- 300ms delay before start (like in Viber)
- Playback without sound with looping
- Automatic stop when cursor leaves
- Lazy loading for traffic saving

## 🔐 Two-Factor Authentication (2FA)

### 2FA System Overview

CloudStorageApp includes a complete two-factor authentication system to enhance user security. The system supports two authentication methods and provides flexible settings for both users and administrators.

### 2FA Features

#### **🎯 For users:**

- **Registration toggle** - ability to enable 2FA immediately when creating account
- **Two authentication methods**:
  - **Email codes** - 6-digit codes sent to email (valid for 10 minutes)
  - **TOTP codes** - codes from Google Authenticator, Authy and other apps
- **QR codes** - for quick mobile app setup
- **Backup codes** - 10 one-time codes for access recovery
- **Flexible setup** - can be enabled/disabled at any time

#### **🛡️ For administrators:**

- **Forced 2FA** - 2FA requirement for all users
- **Usage statistics** - number of users with 2FA by methods
- **Action logging** - complete audit of all 2FA operations
- **Flexible logic** - users with their own 2FA are not affected by admin settings

### 2FA Installation and Setup

#### **1. Database already configured**

When executing `database.sql` automatically creates:

- 2FA fields in `users` table
- `two_factor_codes` table for temporary codes
- `system_settings` table for global settings
- `two_factor_logs` table for audit
- `trusted_devices` table for future features

#### **2. Email setup (required)**

Ensure SMTP parameters are correctly configured in `config/config.php`:

```php
'email' => [
    'method' => 'smtp',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_username' => 'your@gmail.com',
    'smtp_password' => 'your_app_password',
    'from_email' => 'your@gmail.com',
    'from_name' => 'CloudStorageApp Support'
]
```

### Using 2FA

#### **Registration with 2FA:**

1. **Open** <http://localhost:8080/login.html>
2. **Go** to "Registration" tab
3. **Fill** all required fields
4. **Enable** "Two-factor authentication" toggle
5. **Read** 2FA information block
6. **Click** "Register"
7. **Log in** with new credentials
8. **Automatically** redirected to 2FA setup

#### **Email 2FA Setup:**

1. **Select** "Email code" on setup page
2. **Click** "Continue"
3. **Check email** - test code will arrive
4. **Enter code** from email
5. **Save** backup codes (download or print)
6. **Complete** setup

#### **TOTP 2FA Setup:**

1. **Install** Google Authenticator on phone
2. **Select** "Authenticator App" on setup page
3. **Scan** QR code or enter key manually:
   - **Account name**: your email
   - **Issuer**: Cloud Storage
4. **Enter** 6-digit code from app
5. **Save** backup codes
6. **Complete** setup

#### **Login with 2FA:**

1. **Enter** email and password as usual
2. **2FA code form** appears
3. **Enter code**:
   - From email (if Email method selected)
   - From Authenticator app (if TOTP selected)
   - Or use backup code
4. **Automatic verification** when entering 6 digits
5. **Successful login** to system

### Administrative 2FA Management

#### **Enabling forced 2FA:**

1. **Log in** as administrator
2. **Go** to "System" in side menu
3. **Find** "Security Settings" section
4. **Enable** "Forced two-factor authentication"
5. **View** 2FA usage statistics

#### **Forced 2FA logic:**

- **Users with own 2FA** - not affected (continue using their settings)
- **Users without 2FA** - redirected to 2FA setup on login
- **New users** - must set up 2FA after first login
- **Disabling forced 2FA** - doesn't affect users who enabled 2FA themselves

### 2FA API Endpoints

For testing two-factor authentication (2FA) via Postman, follow the request sequence. After successful login, if 2FA is required, you will receive a response indicating the 2FA method (`two_factor_method: "email"` or `"totp"`). **Important: do not clear session/cookies between login request and 2FA confirmation request.**

#### **1. Login (getting 2FA requirement information)**

- **Method:** `POST`
- **URL:** `/users/login`
- **Request body (JSON):**

  ```json
  {
      "email": "your_email@example.com",
      "password": "your_password"
  }
  ```

- **Expected response (if 2FA enabled for user):**

  ```json
  {
    "success": true,
    "requires_2fa_verification": true,
    "two_factor_method": "totp", // or "email"
    "user_email": "your_email@example.com"
  }
  ```

  After this response, user session will contain temporary data needed for next 2FA confirmation step.

#### **2. 2FA Setup (performed once to enable 2FA)**

- `POST /api/2fa/generate-secret` - generate TOTP secret and QR code
  - **Description:** Used for TOTP setup (Authenticator app). Generates unique secret key and QR code URL.
  - **Request body:** (empty)
  - **Example response:**

    ```json
    {
        "success": true,
        "secret": "JBSWY3DPEHPK3PXP...",
        "qr_url": "otpauth://totp/...",
        "qr_image": "https://api.qrserver.com/v1/create-qr-code/...",
        "account_name": "artzlc@yandex.ru",
        "issuer": "Cloud Storage"
    }
    ```

- `POST /api/2fa/send-email-code` - send code to email
  - **Description:** Sends 6-digit confirmation code to user's registered email. Used for email 2FA setup or code resend during login.
  - **Request body:** (empty)
  - **Example response:**

    ```json
    {
        "success": true,
        "message": "Code sent to email"
    }
    ```

- `POST /api/2fa/verify-totp` - verify TOTP code during setup
  - **Description:** Verifies 6-digit code from Authenticator app with generated secret.
  - **Request body (JSON):**

    ```json
    {
        "code": "123456", // Code from Authenticator app
        "secret": "JBSWY3DPEHPK3PXP..." // Secret from /api/2fa/generate-secret
    }
    ```

- `POST /api/2fa/verify-email` - verify email code during setup
  - **Description:** Verifies 6-digit code sent to email to complete email 2FA setup.
  - **Request body (JSON):**

    ```json
    {
        "code": "654321" // Code received by email
    }
    ```

- `POST /api/2fa/generate-backup-codes` - generate backup codes
  - **Description:** Generates list of one-time backup codes for access recovery.
  - **Request body:** (empty)
  - **Example response:**

    ```json
    {
        "success": true,
        "backup_codes": ["CODE1", "CODE2", "CODE3", ...]
    }
    ```

- `POST /api/2fa/complete-setup` - complete 2FA setup
  - **Description:** Finalizes 2FA setup process, saving selected method and backup codes.
  - **Request body (JSON):**

    ```json
    {
        "method": "email", // or "totp"
        "backup_codes": ["CODE1", "CODE2", ...] // List of generated backup codes
    }
    ```

#### **3. Login with 2FA (performed after step 1 if 2FA required)**

- `POST /api/2fa/verify-email-login` - verify email code during login
  - **Description:** Used to confirm login if user's 2FA method is email. Code is sent automatically after `/users/login` if 2FA is enabled.
  - **Request body (JSON):**

    ```json
    {
        "code": "123456" // Code received by email
    }
    ```

  - **Example successful response:**

    ```json
    {
        "success": true,
        "message": "Login successful",
        "user": { /* user data */ }
    }
    ```

- `POST /api/2fa/verify-totp-login` - verify TOTP code during login
  - **Description:** Used to confirm login if user's 2FA method is Authenticator app.
  - **Request body (JSON):**

    ```json
    {
        "code": "654321" // Code from Authenticator app
    }
    ```

  - **Example successful response:**

    ```json
    {
        "success": true,
        "message": "Login successful",
        "user": { /* user data */ }
    }
    ```

- `POST /api/2fa/verify-backup-code` - verify backup code during login
  - **Description:** Used as alternative login method if main 2FA method is unavailable. Backup code is one-time use.
  - **Request body (JSON):**

    ```json
    {
        "code": "YOUR_BACKUP_CODE" // One of generated backup codes
    }
    ```

  - **Example successful response:**

    ```json
    {
        "success": true,
        "message": "Login successful with backup code",
        "user": { /* user data */ },
        "remaining_backup_codes": 9 // Number of remaining backup codes
    }
    ```

#### **4. Management (check 2FA status)**

- `GET /api/2fa/status` - get user's 2FA status
  - **Description:** Returns current 2FA status for authorized user.
  - **Request body:** (empty)
  - **Example response:**

    ```json
    {
        "success": true,
        "enabled": true,
        "method": "totp", // or "email"
        "setup_completed": true
    }
    ```

- `GET /api/admin/2fa/status` - get 2FA statistics (admin)
  - **Description:** (Requires admin rights) Returns overall 2FA usage statistics in system.
  - **Request body:** (empty)

- `POST /api/admin/2fa/toggle-forced` - toggle forced 2FA (admin)
  - **Description:** (Requires admin rights) Enables or disables forced 2FA for all users.
  - **Request body (JSON):**

    ```json
    {
        "enable": true // or false
    }
    ```

### 2FA Security

#### **Temporary codes:**

- **Email codes** valid for 10 minutes
- **TOTP codes** valid for 30 seconds (standard)
- **Backup codes** one-time use (deleted after use)

#### **Logging:**

All 2FA actions are recorded in `two_factor_logs` table:

- 2FA setup
- Login attempts (successful and failed)
- Code generation and usage
- Administrative changes

#### **Data storage:**

- **TOTP secrets** stored encrypted
- **Backup codes** hashed before saving
- **Temporary codes** automatically deleted after expiration

### Compatible Applications

#### **TOTP applications:**

- **Google Authenticator** (iOS/Android)
- **Microsoft Authenticator** (iOS/Android)
- **Authy** (iOS/Android/Desktop)
- **1Password** (with TOTP support)
- **Bitwarden** (with TOTP support)
- **LastPass Authenticator**

### Troubleshooting

#### **Email not arriving:**

- Check SMTP settings in `config.php`
- Check "Spam" folder
- Ensure email address is correct

#### **QR code not displaying:**

- Check internet connection
- Try entering key manually
- Refresh page

#### **Codes not working:**

- Check server and device time
- Ensure code hasn't expired
- Check correct input (6 digits)
- Use backup code if necessary

#### **Database errors:**

- Ensure complete `database.sql` executed
- Check database access rights
- Check PHP logs for errors

**File sharing issues:**

- ✅ **Fixed**: Error 500 when uploading files - restored correct `getFilesInRootDirectory()` call
- ✅ **Fixed**: Shared files from any folders now display for recipient
- ✅ **Improved**: Removed `d.parent_id IS NULL` restrictions in SQL queries
- ✅ **Added**: Getting `sharedRootIds` for correct method operation

### Getting Administrator Rights

To access administrative panel:

### 1. Assign user administrator role in database or SQL command line

```sql
UPDATE users SET role = 'admin', is_admin = 1 WHERE email = your@email.com;
UPDATE users SET role = 'admin', is_admin = 1 WHERE id = userID;
```

### 2. On login page login.html clear browser cache and cookies (CTRL+SHIFT+R)

### 3. Log into system with same login and password you registered as user

### 4. Now you will have access to administrator panel
## API Routes and Functionality

**⚠️ Important:** Most routes require authorization. First execute POST /users/login to get session.

---

## Routes for Users (role: user)

## Registration and Authorization

`base_url`: `http://localhost:8080/login.html`

**POST /register**  
Register new user

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "first_name": "John",
    "middle_name": "John",
    "last_name": "Johnson", 
    "gender": "male",
    "age": 25,
    "email": "john@example.com",
    "password": "secure_password"
}

{
    "first_name": "Arthur",
    "middle_name": "Arthur",
    "last_name": "Arthurs", 
    "gender": "male",
    "age": 27,
    "email": "arthur@example.com",
    "password": "secure_password1"
}

{
    "first_name": "Alex",
    "middle_name": "Alex",
    "last_name": "Alexson", 
    "gender": "male",
    "age": 29,
    "email": "alex@example.com",
    "password": "secure_password2"
}
```

**POST /users/login**  
User authorization

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "john@example.com",
    "password": "secure_password"
}
```

**GET /users/logout**  
System logout (requires authorization)

---

## New Password Reset Routes

**POST /users/reset_password**  
Request password reset via email

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "user@example.com"
}
```

**POST /users/password-reset-validate**  
Check reset token validity

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "token": "your_reset_token_here"
}
```

**POST /users/password-reset-confirm**  
Confirm password reset with new password

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

## User Management (for users)

**GET /users/list**  
Get user list

**GET /users/get/{id}**  
Get user information by ID

**PUT /users/update**  
Update current user data

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{ 
    "first_name": "New name", 
    "last_name": "New surname", 
    "email": "new@example.com", 
    "old_password": "", 
    "new_password": "", 
    "confirm_new_password": "" 
}

Field descriptions:
- `first_name` — new user first name.
- `last_name` — new user last name.
- `email` — new user email.
- `old_password` — current password (optional field, required only when changing password).
- `new_password` — new password (optional field).
- `confirm_new_password` — new password confirmation (optional field).

If password change fields (`old_password`, `new_password`, `confirm_new_password`) are not provided, password remains unchanged.  
For successful password change, all three fields must be specified, with `new_password` and `confirm_new_password` matching, and `old_password` being correct current user password.

```

---

## File Management

**POST /files/add**  
Add file (alias for upload)

**Content-Type:** `multipart/form-data`

**Form parameters:**

- `files[]` - array of files to upload (required)
- `directory_id` - destination folder ID (default "root")
- `paths` - JSON string with relative paths for folder structure creation (optional)

**GET /files/list**  
Get list of files and folders

**GET /files/get/{id}**
Get file information by ID

**PUT /files/rename**
Rename file

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "file_id": 123,
    "new_name": "New file name"
}
```

**DELETE /files/remove/{id}**  
Delete file by ID

---

## Folder Management

**POST /directories/add**  
Create folder

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "name": "New folder",
    "parent_id": "root"
}
```

**PUT /directories/rename**  
Rename folder

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "id": 123,    
    "new_name": "New folder name"  
}
```

**GET /directories/get/{id}**  
Get folder information and its contents

**DELETE /directories/delete/{id}**  
Delete folder by ID

---

## File Sharing

**PUT /files/share/{id}/{user_id}**  
Share file with specific user

**GET /files/share/{id}**  
Get file sharing information

**DELETE /files/share/{id}/{user_id}**  
Remove file sharing from specific user

---

## Additional File Routes (extended functionality)

**POST /files/upload**  
Upload file or multiple files

**Content-Type:** `multipart/form-data`

**Form parameters:**

- `files[]` - array of files to upload (required)
- `directory_id` - destination folder ID (default "root")
- `paths` - JSON string with relative paths for folder structure creation (optional)

### Example 1: Upload one or multiple files to root folder

## POST /files/upload

Content-Type: multipart/form-data

Form data:

files[]: document.pdf
directory_id: root

### Example 2: Upload files with folder structure creation

## POST /files/upload (structured upload)

Content-Type: multipart/form-data

Form data:

files[]: project/docs/readme.txt
files[]: project/images/logo.png
files[]: project/src/main.js
directory_id: root
paths: ["project/docs/readme.txt", "project/images/logo.png", "project/src/main.js"]

**Example form-data setup in Postman:**

```text
Key: files[]        Type: File      Value: [Select Files]
Key: directory_id   Type: Text      Value: root
Key: paths          Type: Text      Value: ["docs/readme.txt", "images/logo.png"]
```

**JSON structure for `paths` parameter:**

```json
[
    "folder1/subfolder1/file1.txt",
    "folder1/subfolder2/file2.jpg",
    "folder2/file3.pdf"
]
```

**Limitations:**

- Maximum file size: 50MB
- Supported formats: jpg, jpeg, png, gif, pdf, doc, docx, txt, zip, rar
- Maximum files per request: 20

**Success response (200 OK):**

```json
{
    "success": true,
    "message": "Uploaded 3 of 3 files",
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
            "error": "File too large (maximum 50MB)"
        }
    ],
    "total": 3,
    "success_count": 2
}
```

---

## Additional Folder Routes (extended functionality)

**POST /directories/share**  
Share folder with user

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123,
    "email": "arthur@example.com"
}
```

### POST /directories/unshare

1) Cancel folder sharing by owner

2) Decline access to folder received from another user

**⚠️ Important: need to authorize in account of user who received folder from another user

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123
}
```

**PUT /directories/move**  
Move folder

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "directory_id": 123,
    "target_parent_id": 456
}
```

**GET /directories/download/{id}**  
Download folder as archive

**GET /directories/list**  
Get list of all user folders

---

## Routes for Administrators (role: admin)

## Authorization and Administrator Assignment

**POST /users/create-first-admin**  
Assign first administrator (public endpoint for testing)

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "email": "john@example.com"
}
```

**POST /users/login**  
Administrator authorization (same endpoint as for users)

```json
{
    "email": "john@example.com",
    "password": "secure_password"
}
```

---

## User Management

**⚠️ Required: need to re-authorize in administrator account

**GET /admin/users/list**  
Get list of all users

**GET /admin/users/get/{id}**  
Get detailed user information by ID

**PUT /admin/users/update/{id}**  
Update user data

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "first_name": "New name",
    "last_name": "New surname",
    "email": "new@example.com",
    "role": "user"
}
```

**DELETE /admin/users/delete/{id}**  
Delete user by ID

---

## Additional Admin Routes (extended functionality)

**GET /admin/stats**  
Get system statistics

**POST /admin/users/{id}/make-admin**  
Administrator assigns another user as administrator

**PATCH /admin/users/{id}/remove-admin**  
Revoke rights from another administrator

**⚠️ IMPORTANT:** To remove rights from first administrator, first assign another user as administrator, authorize under their account, then revoke first administrator's rights.

**DELETE /admin/users/bulk-delete**  
Bulk user deletion

Headers: Content-Type: application/json
Body → raw → JSON:

```json
{
    "user_ids": [1, 2, 3, 4]
}
```

## File and Folder Management

**GET /admin/files/list**  
Get list of all files in system

**DELETE /admin/files/{id}**  
Delete any file by ID (admin access)

**DELETE /admin/directories/delete/{id}**  
Delete any folder by ID (admin access)

**DELETE /admin/files/cleanup**  
Cleanup unused files

**DELETE /admin/files/clear**  
Delete all files in system

## System Functions

**GET /admin/logs**  
Get system logs

```text
Query parameters:
- level: all|info|warning|error (default: all)
- limit: number of records (default: 100)
```

**DELETE /admin/logs/clear**  
Clear all logs from previous days

**GET /admin/system/health**  
Get system health information

**GET /admin/security/report**  
Get security report

---

### Common Errors and Solutions

#### 1. Error 401 "User not authorized"

**Cause:** Missing authorization cookies  
**Solution:** Execute POST /users/login and ensure cookies are saved

#### 2. Error 403 "Insufficient rights"

**Cause:** Trying to access admin endpoint without administrator rights  
**Solution:** Use `/users/create-first-admin` to assign rights

#### 3. Error 404 "User not found"

**Cause:** Trying to assign administrator to non-existent user  
**Solution:** First register user via `/users/register`

#### 4. Getting HTML instead of JSON

**Cause:** Incorrect URL or request method  
**Solution:** Check URL and HTTP method correctness

#### 5. Error "Administrator already exists"

**Cause:** Administrator already exists in system  
**Solution:** Use `/remove-admin` to reset or authorize with existing admin

#### 6. Password reset errors

**"Invalid or expired token"**  
**Cause:** Token expired (valid for 1 hour) or already used  
**Solution:** Request new token via `/users/password-reset-request`

**"Email not sending"**  
**Cause:** Incorrect SMTP settings  
**Solution:** Check email settings in `config/config.php`

**"Token not found in database"**  
**Cause:** Issues with token saving  
**Solution:** Check `password_reset_tokens` table in database

---

### Support Contacts

When testing issues occur:

1. Check logs in `/logs/` folder
2. Ensure database is configured correctly
3. Check access rights to `/uploads/` and `/logs/` folders
4. For email issues check SMTP settings

## Security

### Implemented Security Measures

- **Password hashing**: Using password_hash() for password storage
- **File validation**: Checking types and sizes of uploaded files
- **SQL Injection protection**: Using prepared statements
- **XSS protection**: Escaping user input
- **Access restriction**: Middleware for authorization checking
- **Logging**: Maintaining log of all user actions
- **Password reset tokens**: Secure one-time tokens with limited validity

### New Security Measures

- **Temporary tokens**: Password reset tokens valid for only 1 hour
- **One-time tokens**: Each token can only be used once
- **Token cleanup**: Automatic deletion of expired tokens
- **Email validation**: Checking user existence before sending
- **Secure generation**: Using cryptographically strong generators

## Troubleshooting

### File Upload Issues

I. **Check folder access rights:**

```bash
chmod 755 uploads/
chmod 755 uploads/files/
chmod 755 uploads/folders/
```

II. **Increase PHP limits in `php.ini`:**

```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M
```

III. **Check that folders exist:**

```bash
mkdir -p uploads/files
mkdir -p uploads/folders
mkdir -p logs
```

IV. **Check web server settings:**

- Ensure Apache/Nginx has access to uploads folder
- Check that .htaccess file doesn't block uploads

### Video Issues

I. **Video not displaying in tiles:**

1. Check browser console (F12) for errors
2. Ensure files have correct MIME type
3. Check that video files are not corrupted

II. **JavaScript cache issues (Chrome):**

1. Use Ctrl+F5 for forced refresh
2. Clear browser cache via settings
3. Try opening in incognito mode

III. **Video not playing:**

- Ensure file is actually video
- Check format support by browser
- Try different browser (Edge, Firefox)
- Check file size (50MB limit)

### Database Issues

**Database connection failure** → Check connection:

```bash
mysql -u username -p -h localhost database_name
```

**No tables in database** → Ensure all tables are created:

```sql
SHOW TABLES;
-- Should be: 
+-------------------------+
| Tables_in_cloud_storage |
+-------------------------+
| directories             |
| files                   |
| password_reset_tokens   |
| shared_items            |
| system_settings         |
| trusted_devices         |
| two_factor_codes        |
| two_factor_logs         |
| users                   |
+-------------------------+
9 rows in set
```

### Email Issues

**Email not sending** → Check SMTP settings:

```php
// In config/config.php
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

**For Gmail:**

1. Enable two-factor authentication
2. Create app password in Google settings
3. Use app password, not regular password

**SMTP errors** → Check logs:

```bash
# View PHP logs
tail -f /var/log/php_errors.log

# Or application logs
tail -f logs/app_YYYY-MM-DD.log
```

V. **Check error logs:**

```bash
# View PHP logs (first navigate to web server or PHP environment logs folder)
#Unix/Linux: 
tail -f /var/log/php_errors.log
#Windows PowerShell or Command Prompt:
Get-Content -Path .\logs\php_errors.log 

# Or application logs (first navigate to application address)
#Unix/Linux:
tail -f logs/app_YYYY-MM-DD.log
#Windows PowerShell or Command Prompt:
Get-Content -Path .\logs\app_YYYY-MM-DD.log (example: app_2025-07-10.log)
```

VI. **Common errors and solutions:**

```text
**"File too large"** → increase `upload_max_filesize`

**"Execution time exceeded"** → increase `max_execution_time`

**"Insufficient memory"** → increase `memory_limit`

**"Folder not found"** → check access rights and folder existence

**"SMTP Authentication failed"** → check SMTP login/password

**"Connection refused"** → check SMTP host and port

**"Token not found"** → check password_reset_tokens table
```

### Access Rights Issues

```bash
chmod -R 755 CloudStorageApp/
chmod -R 777 uploads/
chmod -R 777 logs/
```

## FAQ (Frequently Asked Questions)

### Q: How to assign user as administrator?

A: Execute SQL query:

```sql
UPDATE users SET role = 'admin', is_admin = 1 WHERE email = your@email.com;
UPDATE users SET role = 'admin', is_admin = 1 WHERE id = userID;
```

Then user must clear browser cache and log in again. (CTRL+SHIFT+R)

### Q: Which fields are mandatory during registration?

A: First Name, Last Name, Email, Password and Repeat Password (marked with red asterisk in user interface).

### Q: Is Composer required for application to work?

A: Yes, Composer is required for application to work, as project uses dependencies and autoloading according to PSR-4 standard. To install all dependencies execute command:

```bash
composer install
```

This will install PHPMailer and other necessary libraries.

### Q: What is maximum file size?

A: By default limited by PHP settings. Can be changed in `php.ini`.

### Q: Where are uploaded files stored?

A: In `uploads/files/` folder in project root.

### Q: Where are uploaded folders stored?

A: In `uploads/folders/` folder in project root.

### Q: How does password reset work?

A:

1. User requests reset via email
2. System generates unique token and saves in DB
3. Token is sent to user's email
4. User follows link and sets new password
5. Token is marked as used

### Q: How long is password reset token valid?

A: Token is valid for 1 hour from creation. After use token becomes invalid.

### Q: What to do if email doesn't arrive?

1. Check "Spam" folder
2. Ensure SMTP settings are correct
3. Check application logs for errors
4. For Gmail use app password, not regular password

### Q: How to use new sharing routes?

Use:

- `PUT /files/share/{file_id}/{user_id}` - share file with specific user
- `DELETE /files/share/{file_id}/{user_id}` - remove sharing
- `GET /files/share/{file_id}` - see who file is shared with

### Q: Which routes require admin rights?

All routes starting with `/admin/`:

- `/admin/users/list`
- `/admin/users/get/{id}`
- `/admin/users/update/{id}`
- `/admin/users/delete/{id}`
- `/admin/stats`
- `/admin/files`
- `/admin/logs`

### Q: Do I need to install PHPMailer?

For email functions it's recommended to install PHPMailer:

```bash
composer require phpmailer/phpmailer
```

Or download manually and place in `vendor/` folder.
## Examples of Using New Routes

### Example 1: Working with users

```bash
# Get user list
GET /users/list

# Get specific user
GET /users/get/123

# Update your data
PUT /users/update
{
    "first_name": "New name",
    "email": "new@email.com"
}
```

### Example 2: Password reset (full cycle)

```bash
# 1. Request password reset
POST /users/password-reset-request
{
    "email": "user@example.com"
}

# 2. Validate token (received from email)
POST /users/password-reset-validate
{
    "token": "abc123def456"
}

# 3. Set new password
POST /users/password-reset-confirm
{
    "token": "abc123def456",
    "password": "new_password123",
    "confirm_password": "new_password123"
}
```

### Example 3: Precise sharing management

```bash
# Share file with user ID 456
PUT /files/share/123/456

# Remove sharing
DELETE /files/share/123/456

# View who has access to the file
GET /files/share/123
```

### Example 4: Admin functions (new routes)

```bash
# Get all users (admin)
GET /admin/users/list

# Get user (admin)
GET /admin/users/get/123

# Update user (admin)
PUT /admin/users/update/123
{
    "first_name": "New name",
    "role": "admin"
}

# Delete user (admin)
DELETE /admin/users/delete/123

# Get system statistics
GET /admin/stats

# Get system logs
GET /admin/logs?level=error&limit=50
```

### Example 5: Working with email notifications

```bash
# Testing email sending
POST /users/password-reset-request
{
    "email": "test@example.com"
}

# Success response:
{
    "success": true,
    "message": "Password reset link sent to your email"
}

# SMTP error response:
{
    "success": false,
    "error": "Error sending email"
}
```

## New System Features

### 1. Video Viewing (NEW FEATURE)

- **Support for 12+ video formats**: MP4, AVI, MKV, WebM, MOV, WMV, FLV, 3GP, TS, ASF, OGG, MPEG
- **Auto-play in tiles**: Videos play on hover (like in Viber)
- **Canvas animation**: Video frame rendering in tiles for preview
- **Modal viewing**: Built-in video player in modal window
- **Standard controls**: Using native browser controls
- **Error handling**: Graceful degradation for unsupported formats

### 2. Password Reset System

- **Secure tokens**: Cryptographically strong tokens
- **Limited time**: Tokens valid for 1 hour
- **Single use**: Each token can only be used once
- **Email notifications**: Beautiful HTML emails with instructions
- **Auto-cleanup**: Expired tokens automatically deleted

### 3. Email Service

- **SMTP support**: Sending through external SMTP servers
- **HTML templates**: Beautiful branded emails
- **Security**: Protected TLS/SSL connections
- **Logging**: All email operations logged
- **Fallback**: Backup sending methods

### 4. Enhanced Security

- **Token validation**: Multi-level token verification
- **Spam protection**: Request frequency limits
- **Security audit**: Detailed logging of all operations
- **Data cleanup**: Automatic removal of outdated data

## PHPMailer Installation (recommended)

### Via Composer (recommended)

```bash
# In project root execute:
composer require phpmailer/phpmailer
```

### Manual installation

1. Download PHPMailer from GitHub: [https://github.com/PHPMailer/PHPMailer]

2. Extract to folder `vendor/phpmailer/phpmailer/`
3. Structure should be:

```text
CloudStorageApp/
├── vendor/
│   └── phpmailer/
│       └── phpmailer/
│           ├── src/
│           └── ...
```

### Installation check

```php
// Create test_email.php file in project root:
<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
echo "PHPMailer installed correctly!";
```

## Email Provider Configuration

### Gmail

```php
'email' => [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your_email@gmail.com',
    'smtp_password' => 'your_app_password', // App password!
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

## Testing New Features

### Password Reset Testing

1. **Register test user**
2. **Configure SMTP in config.php**
3. **Request password reset:**

```bash
POST /users/password-reset-request
{
    "email": "test@example.com"
}
```

4. **Check email (and spam folder)**

5. **Copy token from email**

6. **Validate token:**

```bash
POST /users/password-reset-validate
{
    "token": "your_token_here"
}
```

7. **Set new password:**

```bash
POST /users/password-reset-confirm
{
    "token": "your_token_here",
    "password": "new_password",
    "confirm_password": "new_password"
}
```

### Database Check

```sql
-- Check tokens table
SELECT * FROM password_reset_tokens;

-- Check that tokens are cleaned up
SELECT COUNT(*) FROM password_reset_tokens WHERE expires_at < UNIX_TIMESTAMP();
```

## Monitoring and Maintenance

### Regular Tasks

1. **Cleanup expired tokens** (can set up cron):

```bash
# Add to crontab for daily cleanup:
0 2 * * * php /path/to/your/project/cleanup_tokens.php
```

2. **Log monitoring**

```bash
# Check logs for email errors:
grep -i "email\|smtp\|mail" logs/app_*.log
```

3. **Disk space check**

```bash
# Monitor uploads folder size:
du -sh uploads/
```

## License

This project is created for educational purposes and is intended to demonstrate development skills.

## About the Project

This project is developed as a demonstration of web application development skills using PHP with:

- **Clean Architecture** - controllers (2-3 lines), services (business logic), repositories (data access)
- **Repository and Service patterns** with interfaces
- **Dependency Injection** through container
- **Specialized services**:
  - `FileResponseService` - file delivery with Range request support
  - `FileTypeService` - file type detection and preview capabilities
  - `FileUtils` - file handling utilities
- **MySQL database work**
- **REST API creation**
- **Web application security**
- **Modern routing approaches**
- **Secure password reset**
- **SMTP integration for email notifications**

## System Testing

### Quick Diagnostics

To check system functionality:

1. **Browser console check** (F12):
   - Open developer tools
   - Check Console tab for errors
   - Check Network tab for HTTP requests

2. **Authorization check**:
   - Ensure you are logged into the system
   - Check file access permissions
   - Re-login if necessary

3. **Browser cache clearing**:
   - Use Ctrl+F5 for forced refresh
   - Clear cache through browser settings
   - Try incognito mode

### File Function Testing

#### **File icons in list mode**

- 🎬 **Video files**: blue play icon
- 🖼️ **Images**: blue image icon
- 📄 **PDF**: red PDF icon
- 🎵 **Audio**: yellow music icon
- 📝 **Word**: blue Word icon
- 📊 **Excel**: green Excel icon
- 📋 **PowerPoint**: yellow presentation icon
- 📄 **Text files**: light blue text icon
- 🗜️ **Archives**: gray archive icon
- 📄 **Other**: gray file icon

#### **Video function testing**

1. **Upload video files** (MP4, AVI, MOV)
2. **Switch to tile mode**
3. **Check Canvas animation** on hover
4. **Open preview** by clicking tile
5. **Ensure video player works correctly**
6. **Check icons** in list mode

*Project created for educational purposes and technical skills demonstration.*
