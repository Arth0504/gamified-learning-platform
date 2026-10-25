# Installation and Setup Guide (XAMPP local server)

This document provides step-by-step instructions to deploy the **Gamified Learning Platform for Rural Education** on a local Windows XAMPP stack.

---

## 📋 Prerequisites

* **OS:** Windows 10 / 11
* **Local Stack:** XAMPP (Apache + MySQL/MariaDB) running PHP 8.0 or newer.
* **Web Browser:** Google Chrome, Microsoft Edge, or Mozilla Firefox.

---

## 🛠️ Step-by-Step Installation

### Step 1: Copy Project files
* Copy the entire directory `gamified learning platform` and paste it inside the htdocs folder:
  `C:\xampp\htdocs\gamified learning platform\`

### Step 2: Run local servers
1. Open the **XAMPP Control Panel** (run as administrator).
2. Click the **Start** button next to **Apache**.
3. Click the **Start** button next to **MySQL**.
4. Confirm that both labels turn green and show active port numbers (typically `80, 443` for Apache and `3306` for MySQL).

### Step 3: Set Up Database Schema & Initial Seeds
1. Open **Command Prompt** (cmd) or **PowerShell**.
2. Run the SQL schema import command:
   ```bash
   Get-Content "C:\xampp\htdocs\gamified learning platform\database\schema.sql" | C:\xampp\mysql\bin\mysql.exe -u root
   ```
3. Run the SQL seed import command to generate admin, teacher, and student logins:
   ```bash
   Get-Content "C:\xampp\htdocs\gamified learning platform\database\seed.sql" | C:\xampp\mysql\bin\mysql.exe -u root
   ```

### Step 4: Open Application in Web Browser
* Open your browser and navigate to:
  `http://localhost/gamified%20learning%20platform/`
* You should see the public portal homepage.

---

## 🔧 Troubleshooting and Fixes

### 1. Port 80 Conflict (Apache Fails to Start)
* **Issue:** Another program (like Skype, IIS, or VMware) is listening on Port 80.
* **Fix:** Open XAMPP Control Panel -> Apache -> Config -> `httpd.conf`. Search for `Listen 80` and replace it with `Listen 8080`. You will then access the site via `http://localhost:8080/gamified%20learning%20platform/`.

### 2. Database Connection Failures
* **Issue:** PHP outputs database connection errors.
* **Fix:** Inspect the configuration file [db.php](file:///c:/xampp/htdocs/gamified%20learning%20platform/config/db.php). Confirm that `DB_HOST` is set to `localhost` and `DB_USER` is `root` with empty password `""` (default XAMPP settings).

### 3. File Upload limits
* **Issue:** Teachers fail to upload large PDF files.
* **Fix:** Open XAMPP Control Panel -> Apache -> Config -> `php.ini`. Search for `upload_max_filesize` and `post_max_size` and change their values to e.g. `20M` or `50M`. Restart Apache.
