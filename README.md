# Tutorvio Portal

This is a monorepo containing the Tutorvio platform, separated into a Laravel API backend and a Vue 3 frontend.

## Project Structure

- `backend/`: Laravel 11 API Backend
- `frontend/`: Vue 3 + Vite + TypeScript Frontend
- `docker-compose.yml`: Docker configuration for local development

## Local Development Setup

1. Make sure you have Docker and Docker Compose installed.
2. Clone the repository.
3. Start the containers:
   ```bash
   docker compose up -d
   ```
4. Setup Backend:
   ```bash
   docker compose exec backend composer install
   docker compose exec backend php artisan key:generate
   docker compose exec backend php artisan migrate
   ```
5. Setup Frontend:
   ```bash
   docker compose exec frontend npm install
   docker compose exec frontend npm run dev
   ```

## Services

- **Backend API**: http://localhost:8000
- **Frontend App**: http://localhost:5173
- **MariaDB Database**: localhost:3307 (User: tutorvio, Pass: tutorvio_password)
