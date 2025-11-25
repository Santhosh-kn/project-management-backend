# Project Management System - Backend API

RESTful API built with Laravel 11 for project and task management.

## Features

- User Authentication with JWT
- Project Management CRUD operations
- Task Management with tags and assignments
- Role-based Access Control
- File Upload Support
- Time Tracking
- Calendar Integration
- Comment System
- Real-time Notifications

## Tech Stack

- Framework: Laravel 11
- Database: MySQL 8.0+
- Authentication: JWT (tymon/jwt-auth)
- API: RESTful architecture
- Language: PHP 8.2+

## Installation

### Prerequisites
- PHP >= 8.2
- Composer
- MySQL >= 8.0
- Node.js and npm (for asset compilation)

### Setup

Clone the repository:
```bash
git clone https://github.com/Santhosh-kn/project-management-backend.git
cd project-management-backend
```

Install dependencies:
```bash
composer install
```

Configure environment:
```bash
cp .env.example .env
# Edit .env with your database credentials
```

Generate application key:
```bash
php artisan key:generate
```

Generate JWT secret:
```bash
php artisan jwt:secret
```

Run migrations:
```bash
php artisan migrate
```

Seed database (optional):
```bash
php artisan db:seed
```

Start development server:
```bash
php artisan serve
```

API runs at: http://localhost:8000

## Database Configuration

Edit .env file:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_management
DB_USERNAME=root
DB_PASSWORD=
```

## API Endpoints

### Authentication
- POST /api/auth/register - Register new user
- POST /api/auth/login - User login
- POST /api/auth/logout - User logout
- GET /api/auth/me - Get authenticated user

### Projects
- GET /api/projects - List all projects
- POST /api/projects - Create project
- GET /api/projects/{id} - Get project details
- PUT /api/projects/{id} - Update project
- DELETE /api/projects/{id} - Delete project

### Tasks
- GET /api/projects/{id}/tasks - List project tasks
- POST /api/projects/{id}/tasks - Create task
- GET /api/tasks/{id} - Get task details
- PUT /api/tasks/{id} - Update task
- DELETE /api/tasks/{id} - Delete task

### Files
- POST /api/projects/{id}/files - Upload file
- GET /api/projects/{id}/files - List project files
- DELETE /api/files/{id} - Delete file

### Time Tracking
- POST /api/tasks/{id}/time-logs - Log time
- GET /api/tasks/{id}/time-logs - Get time logs
- PUT /api/time-logs/{id} - Update time log
- DELETE /api/time-logs/{id} - Delete time log

See API_DOCUMENTATION.md for complete API reference.

## Project Structure

```
project-management-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/    # API Controllers
│   │   └── Middleware/     # Custom Middleware
│   ├── Models/             # Eloquent Models
│   └── Services/           # Business Logic
├── config/                 # Configuration files
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/            # Database seeders
├── routes/
│   └── api.php             # API routes
├── storage/                # File storage
└── tests/                  # Tests
```

## Running Tests

Run all tests:
```bash
php artisan test
```

Run specific test:
```bash
php artisan test --filter TestName
```

## Deployment

### Production Setup

Set environment to production:
```
APP_ENV=production
APP_DEBUG=false
```

Optimize application:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set up queue worker:
```bash
php artisan queue:work
```

## Security

- All passwords are hashed using bcrypt
- API authentication via JWT tokens
- CORS configured for frontend domain
- Input validation on all endpoints
- SQL injection protection via Eloquent ORM

## Contributing

1. Fork the repository
2. Create a feature branch (git checkout -b feature/amazing-feature)
3. Commit your changes (git commit -m 'Add amazing feature')
4. Push to the branch (git push origin feature/amazing-feature)
5. Open a Pull Request

## License

This project is licensed under the MIT License.

## Author

Santhosh KN
- GitHub: [@Santhosh-kn](https://github.com/Santhosh-kn)
- Repository: [project-management-backend](https://github.com/Santhosh-kn/project-management-backend)

## Related Repository

Frontend Application: [project-management-frontend](https://github.com/Santhosh-kn/project-management-frontend)

## Support

For support, open an issue in the [GitHub repository](https://github.com/Santhosh-kn/project-management-backend/issues).
