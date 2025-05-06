# create new project
    laravel new laravel_2023
## How to run from git
    copy storage folder    
    composer install
    copy .env
    #php artisan key:generate
# Run as server
    php artisan serve
# database
    php artisan migrate:fresh --seed #for database with seed
    php artisan make:migration create_user_groups 
    php artisan make:seeder UserGroupsSeeder 

    #php artisan make:mail MailSender 
    #php artisan db:seed --class=SystemTasksSeeder #for specifiq seed
    #php artisan migrate --path=/database/migrations/2022_03_24_190538_create_company_users.php  # For specifiq migration
    ***********
    #vendor/laravel/sanctum/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php # delete this file
