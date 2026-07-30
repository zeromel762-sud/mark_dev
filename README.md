# LEMON_DEV System - Setup Instructions

## 📁 Files Created:
- `database.sql` - Database schema (users, chat_messages, transactions)
- `db.php` - Database connection (PDO MySQL)
- `auth.php` - Login/Register/Logout backend
- `chat.php` - Chat send/fetch/online users backend
- `transaction.php` - Transaction create/list backend
- `login.html` - Login page
- `register.html` - Registration page
- `chat.html` - Chat interface

## 🚀 Setup Steps:

### 1. Start XAMPP
- Open XAMPP Control Panel
- Start **Apache** and **MySQL**

### 2. Copy to htdocs
- Copy the `lemon_dev_system` folder to:
  `C:\xampp\htdocs\`

### 3. Create Database
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click "Import" tab
- Choose `database.sql` file
- Click "Go" to create the database

### 4. Access the System
- Profile: Open `profile.html` in browser
- Login: http://localhost/lemon_dev_system/login.html
- Register: http://localhost/lemon_dev_system/register.html
- Chat: http://localhost/lemon_dev_system/chat.html

## 👤 Default Admin Account:
- Username: `admin`
- Password: `admin123`

## 🔄 Flow:
1. User visits profile.html → clicks "Contact Us for Transaction"
2. Redirected to login.html
3. If no account → clicks "Register here" → register.html
4. After registration → redirected to login.html
5. After login → redirected to chat.html
6. Users can chat in real-time with other online users