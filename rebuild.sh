#!/bin/bash

echo "Stopping containers..."
docker-compose down

echo "Removing any old images..."
docker rmi $(docker images -q ${DOCKERHUB_USERNAME:-username}/navigo:dev) 2>/dev/null || true

echo "Cleaning Docker build cache..."
docker builder prune -f

echo "Checking entrypoint script exists..."
if [ ! -f "docker/scripts/entrypoint.sh" ]; then
  echo "ERROR: entrypoint.sh does not exist at docker/scripts/entrypoint.sh"
  exit 1
fi

echo "Setting executable permissions on entrypoint.sh script..."
chmod +x docker/scripts/entrypoint.sh

echo "Building with new Dockerfile..."
docker-compose build --no-cache app

echo "Starting containers..."
docker-compose up -d

echo "Done! Application is now running with the new minimal Dockerfile."
