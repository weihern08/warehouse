<?php
declare(strict_types=1);

/**
 * PDO database connection factory.
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $candidates = [DB_NAME];
    if (defined('DB_NAME_CANDIDATES') && is_array(DB_NAME_CANDIDATES)) {
        foreach (DB_NAME_CANDIDATES as $name) {
            if (is_string($name) && $name !== '' && !in_array($name, $candidates, true)) {
                $candidates[] = $name;
            }
        }
    }

    $errors = [];

    foreach ($candidates as $dbName) {
        $dsnList = [
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, $dbName, DB_CHARSET),
            sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, $dbName, DB_CHARSET),
        ];

        foreach ($dsnList as $dsn) {
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                return $pdo;
            } catch (PDOException $e) {
                $errors[] = $dbName . ': ' . $e->getMessage();
            }
        }
    }

    error_log('Database connection failed: ' . implode(' | ', $errors));

    if (defined('APP_ENV') && APP_ENV === 'development') {
        throw new PDOException(implode(' | ', $errors));
    }

    http_response_code(500);
    exit('Unable to connect to the database. Please try again later.');
}
