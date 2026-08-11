<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            /*
             * DB 세션 타임존을 앱과 같은 UTC 로 못박는다.
             *
             * 없으면 세션 타임존이 SYSTEM 이라 서버 OS 시계를 따라간다. 그러면
             * PHP 가 넣은 값은 UTC, DB 가 채운 값(DEFAULT current_timestamp,
             * NOW())은 서버 지역시간이 되어 한 표에 9시간 어긋난 값이 섞인다.
             * 화면은 둘 다 UTC 로 보고 다시 +9 하므로 어떤 행은 9시간,
             * 어떤 행은 18시간 뒤로 보인다.
             *
             * 이름 있는 지역(Asia/Seoul)이 아니라 숫자 오프셋을 쓴다 — 이름은
             * MySQL 시간대 표(mysql.time_zone)가 적재돼 있어야 먹는데 xampp 는
             * 비어 있다. '+00:00' 은 표 없이도 언제나 통한다.
             */
            'timezone' => env('DB_TIMEZONE', '+00:00'),
            // PHP 8.5 에서 PDO::MYSQL_ATTR_SSL_CA 가 deprecated 됐다. 상수를 직접
            // 쓰면 경고가 모든 요청·테스트 출력에 섞인다(운영에서 display_errors 가
            // 켜져 있으면 JSON 응답까지 깨진다). 8.5 이상은 새 상수를 쓴다.
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            /*
             * DB 세션 타임존을 앱과 같은 UTC 로 못박는다.
             *
             * 없으면 세션 타임존이 SYSTEM 이라 서버 OS 시계를 따라간다. 그러면
             * PHP 가 넣은 값은 UTC, DB 가 채운 값(DEFAULT current_timestamp,
             * NOW())은 서버 지역시간이 되어 한 표에 9시간 어긋난 값이 섞인다.
             * 화면은 둘 다 UTC 로 보고 다시 +9 하므로 어떤 행은 9시간,
             * 어떤 행은 18시간 뒤로 보인다.
             *
             * 이름 있는 지역(Asia/Seoul)이 아니라 숫자 오프셋을 쓴다 — 이름은
             * MySQL 시간대 표(mysql.time_zone)가 적재돼 있어야 먹는데 xampp 는
             * 비어 있다. '+00:00' 은 표 없이도 언제나 통한다.
             */
            'timezone' => env('DB_TIMEZONE', '+00:00'),
            // PHP 8.5 에서 PDO::MYSQL_ATTR_SSL_CA 가 deprecated 됐다. 상수를 직접
            // 쓰면 경고가 모든 요청·테스트 출력에 섞인다(운영에서 display_errors 가
            // 켜져 있으면 JSON 응답까지 깨진다). 8.5 이상은 새 상수를 쓴다.
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
