# 🚀 NaviGo BE - The Powerhouse Behind Your Legal Solution ⚖️

[![Final Project Pemrograman Web Lanjut - [Law in Glory / Navigo]](https://i.ytimg.com/vi/Xup02FQcCCw/maxresdefault.jpg)](http://www.youtube.com/watch?v=Xup02FQcCCw8)

Welcome to the **NaviGo Backend** repository! This is the core service that powers the NaviGo platform, providing all the necessary backend functionalities. Built with Laravel, this backend is designed to be robust, secure, and scalable, handling everything from authentication to AI-powered document processing.

## ✨ Backend Features

-   📄 **Legal Document Generator**: API to dynamically create custom legal documents based on user input.
-   🔍 **MOU Document Analyzer**: Upload and analyze documents (PDF, DOCX) to extract key information and generate summaries using AI.
-   🤖 **AI Law Chatbot**: Provides the backend for interactive chat sessions with an AI legal assistant powered by Google Gemini.
-   👨‍⚖️ **Find a Lawyer**: Endpoints for searching and filtering certified lawyers based on various criteria.
-   📊 **User Dashboard**: Manages user profile data, activity history, and document statistics.
-   🔒 **Secure Authentication**: A secure, token-based login and registration system using Laravel Sanctum, with support for Google login.

## 🛠️ Tech Stack

-   **Framework**: Laravel
-   **Language**: PHP
-   **Database**: MySQL
-   **Web Server**: Nginx
-   **Containerization**: Docker & Docker Compose
-   **Authentication**: Laravel Sanctum, Laravel Socialite
-   **External APIs**: Google Gemini API
-   **File Processing**: `phpoffice/phpword`, `smalot/pdfparser`

## 🏁 Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

Make sure you have the following installed on your machine:

-   Docker
-   Docker Compose
-   Git

### ⚙️ Installation

1.  **Clone the repository**
    ```sh
    git clone <YOUR_REPOSITORY_URL>
    ```

2.  **Navigate to the project directory**
    ```sh
    cd navigo-be
    ```

3.  **Create and configure your environment file**
    ```sh
    cp .env.example .env
    ```
    Next, open the `.env` file and update it with your credentials, especially for `GEMINI_API_KEY`, `GOOGLE_CLIENT_ID`, and `GOOGLE_CLIENT_SECRET`.

4.  **Run the Docker containers**
    ```sh
    docker-compose -f docker-compose.dev.yml up -d
    ```

5.  **Install Composer dependencies**
    ```sh
    docker-compose -f docker-compose.dev.yml exec app composer install
    ```

6.  **Generate the application key**
    ```sh
    docker-compose -f docker-compose.dev.yml exec app php artisan key:generate
    ```

7.  **Run database migrations and seeders**
    ```sh
    docker-compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed
    ```

Now, your API is running! You can access it at `http://localhost:9091`. ✨

## 📜 Available Scripts

You can run `artisan` commands inside the `app` container.

-   **Running Artisan Commands**:
    ```sh
    # Using the helper script
    ./artisan.sh <your-command>
    
    # Example: Clear the cache
    ./artisan.sh cache:clear
    ```

-   **Stopping the Environment**:
    ```sh
    docker-compose -f docker-compose.dev.yml down
    ```

## 📁 Project Structure

The project follows a standard Laravel structure.

```
.
├── app
│  ├── Console/Commands   # Custom Artisan commands
│  ├── Http/Controllers   # Controllers for handling requests
│  ├── Models             # Eloquent models for database interaction
│  └── Traits             # Reusable traits (e.g., DocumentUtilityTrait)
├── config                # Application configuration files
├── database
│  ├── migrations         # Database schema
│  └── seeders            # Initial data seeders
├── docker                # Docker configurations (Nginx, PHP, scripts)
├── routes
│  └── api.php            # API endpoint definitions
├── docker-compose.dev.yml  # Docker configuration for development
└── docker-compose.prod.yml # Docker configuration for production
```
