# Flash-Sale-Checkout

This project is Laravel-based application designed to handle flash sale checkouts. 

## Requirements
- PHP 8.2+
- Composer
- Laravel 12
- MySQL 8+
- docker-compose 

## Installation

1. Clone the repository:
   ```bash
   git clone git@github.com:ahmedaliv/Flash-Sale-Checkout.git
    cd Flash-Sale-Checkout
    ```
2. Install dependencies:
    ```bash
    composer install
    ```
3. Copy the example environment file and configure your environment variables:
    ```bash
    cp .env.example .env
    ```
    Update the `.env` file with your database and other configurations. (the .env.example is already configured for docker setup)
4. Generate an application key:
    ```bash
    php artisan key:generate
    ```
5. Start the Database using Docker:
    ```bash
    docker-compose up -d
    ```
6. Run migrations and seed the database:
    ```bash
    php artisan migrate --seed
    ```

7. Start the development server:
    ```bash
    php artisan serve
    ```
8. Access the application at `http://localhost:8000`.


## API Endpoints
 - `GET /api/products/{id}`: Retrieve product details by ID.
 - `POST /api/holds`: Create a hold on a product.
 - `POST /api/