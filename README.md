# AWS Laravel Vue Document Management System

A comprehensive document management system showcasing enterprise-grade development with AWS integration, Laravel backend, and Vue 3 frontend.

## Features

- **Document Upload & Storage**: S3-based storage with intelligent tiering
- **AI-Powered Analysis**: OCR via AWS Textract and sentiment analysis via AWS Comprehend  
- **Asynchronous Processing**: SQS/SNS-based job queues for scalable document processing
- **Real-time Status Updates**: Live processing status tracking
- **Advanced Search**: Full-text search and filtering capabilities
- **Enterprise Security**: IAM-based access control and signed URLs
- **Modern UI**: Vue 3 + TypeScript with Reka UI components

## Architecture

- **Backend**: Laravel 12 with SOLID principles and dependency injection
- **Frontend**: Vue 3 + TypeScript + Inertia.js + Tailwind CSS
- **Database**: SQLite (development) / PostgreSQL/MySQL (production)
- **Queue**: SQS for job processing
- **Storage**: S3 with CloudFront CDN
- **Testing**: Pest (PHP) + comprehensive test coverage

## Development

### Prerequisites
- PHP 8.2+
- Node.js 18+
- Composer
- npm/yarn
- Docker & Docker Compose (for local S3/Redis)

### Quick Start
```bash
composer install
npm install

# Run docker services
docker-compose up -d

# Setup environment
cp .env.local .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Start development servers
composer dev
```

### Individual Services
```bash
# Backend only
php artisan serve

# Frontend only  
npm run dev

# Queue worker
php artisan queue:work

# Tests
composer test
```

### Access Points
- **Application**: http://localhost:8000
- **Register**: http://localhost:8000/register
- **Login**: http://localhost:8000/login
- **Documents**: http://localhost:8000/documents (after login)

### Creating a User
```bash
# Create a user via registration page at /register, or use Tinker:
php artisan tinker
>>> User::factory()->create(['email' => 'admin@example.com', 'password' => Hash::make('password')])
>>> exit
```

### Docker Services (Local Development)
- **MinIO S3**: http://localhost:9000 (API) / http://localhost:9001 (Console)
  - Credentials: `laravel` / `password123`
  - Bucket: `laravel-documents`
- **Redis**: localhost:6379
- **MySQL**: localhost:3306 (`laravel` / `password`)

### Testing
```bash
# Run all tests
composer test

# Frontend linting
npm run lint

# Code formatting
npm run format
```

## Project Structure

```
app/
├── Contracts/           # Interfaces and service contracts
├── Services/            # AWS integrations and business logic
├── Jobs/               # Queue-based background jobs
├── Models/             # Eloquent models
├── Http/Controllers/   # Request handlers
└── Repositories/       # Data access layer

resources/js/
├── components/         # Vue components
├── pages/             # Inertia.js pages
├── layouts/           # Page layouts
└── composables/       # Shared logic

tests/
├── Feature/           # Integration tests
└── Unit/             # Unit tests
```

## AWS Services Used

- **S3**: Document storage and retrieval
- **Textract**: OCR and document analysis
- **Comprehend**: Text analysis and sentiment detection
- **SQS**: Asynchronous job processing
- **SNS**: Event notifications
- **CloudFront**: CDN for document delivery
- **IAM**: Access control and security

## TODO

1. Ensure FakeComprehendService, FakeTextractService are consistent with actual AWS inputs-outputs, test