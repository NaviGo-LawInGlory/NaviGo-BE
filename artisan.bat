@echo off
REM Check if Docker is running by attempting to execute a simple command
docker info >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Docker is not running or not installed
    exit /b 1
)

REM Check if the container is running
docker ps | findstr navigo_app_dev >nul
if %errorlevel% neq 0 (
    echo Error: The navigo_app_dev container is not running
    echo Start the containers with: docker-compose -f docker-compose.dev.yml up -d
    exit /b 1
)

REM Execute artisan command in the container
docker exec -it navigo_app_dev php artisan %*
