# 🎯 Event-Based Membership Management System (EBM)

A role-based Laravel application designed to streamline user approvals, event registration, and event creation workflows within an organization or college setup. Built to support scalable backend operations for Membership Heads, EBMs, Credit Managers, and regular users.

---

## 🚀 Features

- 🔐 **Role-based Access Control** using Laravel Gates and Middleware
- ✅ **EBM Approvals**: Approve/reject users assigned to an EBM
- 📊 **EBM Dashboard**: View registration stats and activities
- 📝 **Event Registration Viewer**: EBM and Credit Manager can view registration data
- 🎉 **Event Creation**: EBMs can propose and manage events
- 🧾 **Credit Manager Access**: View and verify event attendance for credit purposes
- 🧑‍💼 **Membership Head Dashboard**: Oversee all EBM approvals and override decisions
- ✉️ **Mail Testing** with Mailpit
- 🔄 **User Management**: EBMs can register users on their behalf
- ⚙️ **API Ready** with rate limiting and authentication

---

## 🛠 Tech Stack

| Layer         | Technology             |
|---------------|------------------------|
| Backend       | Laravel 12.14.1        |
| Authentication| Sanctum (Token-based)  |
| Database      | MySQL (via Aiven)      |
| Deployment    | Render + Docker + Nginx|
| Mail Testing  | Mailpit                |
| Version Control | Git + GitHub         |

---

## ⚙️ Installation Guide

```bash
# Clone the repository
git clone https://github.com/your-username/ebm-system.git
cd lolo-backend

# Install dependencies
composer install
npm install && npm run build

# Copy and configure your .env file
cp .env.example .env
php artisan key:generate

# Run migrations and seeders
php artisan migrate --seed

# Start the development server
php artisan serve
