#!/bin/bash

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running or not installed"
    exit 1
fi

# Check if the container is running
if ! docker ps | grep -q navigo_app_dev; then
    echo "Error: The navigo_app_dev container is not running"
    echo "Start the containers with: docker-compose -f docker-compose.dev.yml up -d"
    exit 1
fi

# Execute artisan command in the container
echo "Running: php artisan $@"
docker exec -it navigo_app_dev php artisan "$@"
