# Employee Onboarding System

A role-based system that manages a new employee's onboarding from acceptance to completion.

**Flow:** HR creates the employee → Employee submits data → HR reviews → IT sets up accounts & equipment → Manager approves → Completed.

## Tech Stack

- **Backend:** Laravel 13, MySQL, Laravel Sanctum
- **Frontend:** Next.js, TypeScript, Tailwind CSS *(in progress)*

## Roles

| Role | Responsibility |
|---|---|
| Admin | Manages users, departments, and has full access |
| HR | Creates employees, starts onboarding, reviews submissions |
| Employee | Completes their data and submits |
| IT | Completes setup tasks (email, laptop, accounts) |
| Manager | Gives the final approval |

## Onboarding Status Flow

```
employee_pending → hr_review → it_setup → manager_approval → completed
```

## Features

- Token-based authentication (Sanctum)
- Role-based access control (RBAC) via middleware
- Auto-generated onboarding checklist from task templates
- Status transitions with validation and database transactions

## API Endpoints

| Method | Endpoint | Role |
|---|---|---|
| POST | `/api/auth/login` | Public |
| GET | `/api/me` | Authenticated |
| GET/POST | `/api/departments` | All / Admin |
| GET/POST/PUT | `/api/employees` | Scoped / HR, Admin |
| POST | `/api/employees/{id}/onboarding` | HR, Admin |
| POST | `/api/onboardings/{id}/submit` | Employee |
| POST | `/api/onboardings/{id}/hr-decision` | HR, Admin |
| POST | `/api/tasks/{id}/complete` | IT, Admin |
| POST | `/api/onboardings/{id}/manager-decision` | Manager, Admin |

## Setup

```bash
git clone https://github.com/Radi1n/onboarding-system.git
cd onboarding-system
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env, then:
php artisan migrate --seed
php artisan db:seed --class=TaskTemplateSeeder
php artisan serve
```

## Roadmap

- [x] Authentication & roles
- [x] Departments & employees
- [x] Onboarding flow
- [ ] Document upload & HR review
- [ ] Notifications & audit logs
- [ ] Next.js frontend
- [ ] Deployment