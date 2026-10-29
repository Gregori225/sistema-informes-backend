<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                // Neon provee la variable completa: postgresql://user:pass@ep-pooler.region.aws.neon.tech/dbname?sslmode=require
                $databaseUrl = getenv('DATABASE_URL');

                if ($databaseUrl) {
                    $dbparts = parse_url($databaseUrl);
                    $host = $dbparts['host'];
                    $port = $dbparts['port'] ?? '5432';
                    $user = $dbparts['user'];
                    $pass = $dbparts['pass'];
                    $db   = ltrim($dbparts['path'], '/');
                } else {
                    $host = getenv('DB_HOST') ?: 'localhost';
                    $port = getenv('DB_PORT') ?: '5432';
                    $db   = getenv('DB_NAME') ?: 'neondb';
                    $user = getenv('DB_USER') ?: 'postgres';
                    $pass = getenv('DB_PASS') ?: '';
                }

                $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    // Timeout preventivo para conexiones serverless
                    PDO::ATTR_TIMEOUT            => 5,
                ];

                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error de conexión con la base de datos Neon: ' . $e->getMessage()
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
