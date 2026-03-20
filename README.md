# 📚 NEU Library Visitor Log System

A web-based visitor log and management system for the **New Era University (NEU) Library**, 
built as part of an Information Management course project.

---

## 🌐 Live Demo
🔗 <a href= "https://neu-library.infinityfreeapp.com"> Neu Library Visitor Log </a>

---

## 🔑 Default Admin Account

| Field | Value |
|---|---|
| Email | jcesperanza@neu.edu.ph |
| Password | admin123 |

---

## 👥 User Types

| Type | Access |
|---|---|
| **Student** | Visitor login only |
| **Faculty** | Visitor login only |
| **Staff** | Visitor login only |
| **Admin** | Full dashboard access |

---

## 📌 Input Validation

- RFID must follow format: `##-#####-###` (e.g. `24-98463-352`)
- Email must be `@neu.edu.ph` institutional email
- Google Sign-In restricted to `@neu.edu.ph` accounts only
- Blocked visitors cannot log in

---

## 📋 Features

### 👤 Visitor Side
- Sign in using **RFID number** or **institutional email** (@neu.edu.ph)
- **Google Sign-In** support for @neu.edu.ph accounts
- **First-time visitors** fill in complete details (name, year level, college, program)
- **Returning visitors** only need to enter their purpose of visit
- Welcome screen displayed after successful check-in with **Philippine Standard Time** timestamp

### 🔐 Admin Side
- Secure login via **email + password** or **Google Sign-In**
- **Dashboard** with real-time statistics:
  - Visitors per day, week, and month
  - Breakdown by student, faculty, and staff
  - Total registered visitors and blocked accounts
- **Visitor Logs** with:
  - Live search (no page reload) by name, email, program, or reason
  - Filter by Today, This Week, This Month, or Custom Date Range
  - Export logs to **PDF**
- **Visitor Management**:
  - View all registered visitors
  - Live search visitors
  - Block or unblock visitors

---

## 🛠️ Tech Stack

| Technology | Purpose |
|---|---|
| PHP | Backend / Server-side logic |
| MySQL | Database |
| JavaScript (Fetch API) | Live search / Dynamic UI |
| HTML + CSS | Frontend design |
| Google OAuth 2.0 | Google Sign-In |
| InfinityFree | Free web hosting |

---

## 📁 File Structure
```
neu-library/
├── index.php      → Visitor login page
├── welcome.php    → Welcome screen after check-in
├── admin.php      → Admin dashboard
├── search.php     → Live search API (JSON)
├── db.php         → Database connection
└── database.sql   → Database setup script
```
---

## 🌍 How to Deploy (InfinityFree)

1. Create a free account at **infinityfree.com**
2. Create a MySQL database and run `database.sql` in phpMyAdmin
3. Update `db.php` with InfinityFree credentials
4. Upload all PHP files to the `htdocs` folder via File Manager
5. Access your site at your InfinityFree subdomain

---

## 🏫 About

Developed by **AI Christian Limos**
New Era University — BSIT
Subject: Information Management 2
Academic Year: 2025–2026
