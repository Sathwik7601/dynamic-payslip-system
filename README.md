# Dynamic Payslip Management System

A web-based payroll application for employee management and automated payslip generation.

## Tech Stack

| Technology | Usage |
|---|---|
| PHP | Backend logic & server-side processing |
| MySQL | Database management |
| HTML / CSS | Frontend structure & styling |
| JavaScript | Client-side interactivity |
| AdminLTE | Admin dashboard UI framework |
| FPDF | PDF payslip generation |

## Features

- 🔐 Role-based login (Admin / Employee)
- 👥 Employee management with full CRUD operations
- 💰 Salary & pay component configuration
- 🧾 Automated payslip generation (PDF & Excel export)
- 📊 Responsive AdminLTE admin dashboard
- 📋 Payslip history listing

## Project Structure

```
payslip/
├── config/
│   ├── connection.example.php   # DB config template
├── adminlte/                    # AdminLTE assets
├── lib/                         # Libraries
├── font/                        # Fonts for PDF
├── index.php                    # Main dashboard
├── login.php                    # Login page
├── logout.php                   # Logout handler
├── employee_details.php         # Employee listing
├── edit_employee.php            # Edit employee
├── generate_payslip.php         # Payslip generator
├── payslip_list.php             # Payslip history
├── pay_componets.php            # Salary components
├── export_excel.php             # Excel export
├── exportpdf.php                # PDF export
├── fpdf.php                     # PDF library
├── payslip_db.sql               # Database schema
└── README.md
```

## Setup Instructions

### 1. Requirements
- XAMPP / WAMP / LAMP
- PHP >= 7.4
- MySQL

### 2. Installation

```bash
# Clone the repository
git clone https://github.com/Sathwik7601/dynamic-payslip-system.git

# Move to your server's web root
# e.g., C:/xampp/htdocs/payslip
```

### 3. Database Setup

1. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Create a new database: `payslip_db`
3. Import the file: `payslip_db.sql`

### 4. Configure Database Connection

1. Copy the example config:
   ```
   config/connection.example.php  →  config/connection.php
   ```
2. Update your credentials in `config/connection.php`:
   ```php
   $host     = 'localhost';
   $username = 'root';
   $password = '';
   $database = 'payslip_db';
   ```

### 5. Run the Application

Open your browser:
```
http://localhost/payslip/login.php
```

## Screenshots

> Add screenshots of your dashboard, payslip generation, and employee pages here.

## License

This project is for educational and portfolio purposes.
