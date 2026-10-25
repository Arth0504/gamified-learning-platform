# EduQuest — Gamified Learning Platform for Rural Education

A complete PHP + MySQL web application designed to deliver curriculum content to rural regions, utilizing gamification mechanics (streaks, points, badges, dynamic standings, and rewards) alongside proctored testing, doubt clearance, and multilingual support.

---

## 🛠️ Technology Requirements

* **Web Stack:** [XAMPP](https://www.apachefriends.org/) (Recommended) or standalone Apache + MariaDB / MySQL
* **PHP Version:** PHP 8.0 or higher
* **Database:** MySQL 5.7+ / MariaDB 10.4+
* **Required PHP Extensions:**
  * `pdo_mysql` (Database PDO driver)
  * `mbstring` (Multilingual Unicode string handling)
  * `curl` (External AI Quiz generation)
  * `gd` (Image processing & PDF certification generation)
* **Frontend:** Bootstrap 5, Bootstrap Icons, Chart.js, Google Fonts (Outfit, Inter)

---

## 💻 Second-PC Setup & Installation Guide

Follow these steps to set up and run this project on another computer:

### Step 1: Install XAMPP
1. Download and install **XAMPP** (with PHP 8.0+) from [apachefriends.org](https://www.apachefriends.org/).
2. During installation, ensure **Apache** and **MySQL** components are selected.

### Step 2: Clone or Place the Repository
Place the project repository directly inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\gamified learning platform\
```
> **Important Folder Name Notice:** Keep the folder name as `gamified learning platform` so that all internal relative assets and session routing match the configured `BASE_URL` out of the box.

Using Git:
```bash
cd "C:\xampp\htdocs"
git clone https://github.com/Arth0504/gamified-learning-platform.git "gamified learning platform"
```

### Step 3: Start Apache and MySQL
1. Launch **XAMPP Control Panel**.
2. Click **Start** for both **Apache** and **MySQL**.
3. Verify both display green indicators.

---

### Step 4: Import the Database

Choose one of the two options below depending on your goal:

#### Option A: Full Database Restore (Same Data as PC 1)
If you securely transferred the full backup file (`gamified_learning_platform_full_backup.sql`) from PC 1:

**Via PowerShell / Terminal:**
```powershell
# Run from XAMPP root:
Get-Content "C:\xampp\htdocs\gamified learning platform\database\gamified_learning_platform_full_backup.sql" | C:\xampp\mysql\bin\mysql.exe -u root
```

**Via phpMyAdmin:**
1. Open `http://localhost/phpmyadmin/` in your browser.
2. Click on the **Import** tab at the top.
3. Click **Choose File** and select `gamified_learning_platform_full_backup.sql`.
4. Click **Go** at the bottom.

---

#### Option B: Clean Starter Database (From Repository)
If setting up a fresh development instance using the repository's clean schema and demo seed data:

**Via PowerShell / Terminal:**
```powershell
# 1. Import complete 30-table schema:
Get-Content "C:\xampp\htdocs\gamified learning platform\database\schema.sql" | C:\xampp\mysql\bin\mysql.exe -u root

# 2. Import demo seed data:
Get-Content "C:\xampp\htdocs\gamified learning platform\database\seed.sql" | C:\xampp\mysql\bin\mysql.exe -u root
```

---

### Step 5: Verify Database Connection Configuration
Open `config/db.php`. Default XAMPP settings work immediately:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gamified_learning_platform');
```
*If your target PC's MySQL has a custom password or port, edit `config/db.php` accordingly (a template is provided at `config/db.example.php`).*

---

### Step 6: Launch the Application
Open your web browser and navigate to:
```
http://localhost/gamified%20learning%20platform/
```

---

## 🔑 Default Login Accounts (Clean Seed Data)

*All seed accounts use password:* `password123`

| Role | Username | Email |
| :--- | :--- | :--- |
| **System Admin** | `admin` | `admin@rurallearn.org` |
| **Teacher** | `teacher` | `teacher@rurallearn.org` |
| **Student** | `student` | `student@rurallearn.org` |

---

## 🔒 Security & Privacy Notice

To protect personal student information (names, email addresses, contact numbers, birth dates, academic records, and activity logs), live database backup dumps (`*backup*.sql`) are excluded from GitHub version control via `.gitignore`. 

For private data migration between PCs:
1. Keep the exported `gamified_learning_platform_full_backup.sql` on a private, secure USB flash drive or encrypted cloud storage.
2. Copy it into `database/` on the destination PC and follow **Option A** above.
