# Tutorvio Monorepo

This repository contains the backend and frontend applications for Tutorvio.

## Structure

- `/backend` - Laravel API application.
- `/frontend` - Vue 3 + Vite + TypeScript application.

## Prerequisites

- Node.js & npm
- PHP & Composer
- Docker (optional, for local environment)

## Getting Started

### Backend
1. `cd backend`
2. `composer install`
3. `cp .env.example .env`
4. `php artisan key:generate`
5. `php artisan serve`

### Frontend
1. `cd frontend`
2. `npm install`
3. `npm run dev`
