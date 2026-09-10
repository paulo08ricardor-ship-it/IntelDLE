<?php

class Database
{
    private static ?PDO $instance = null;

    /**
     * Retorna uma instância PDO com SQLite e chaves estrangeiras ativadas.
     *
     * @param bool $singleton Se true, reutiliza a mesma conexão no ciclo da requisição
     * @return PDO
     */
    public function conectar(bool $singleton = false): PDO
    {
        if ($singleton && self::$instance !== null) {
            return self::$instance;
        }

        try {
            $dbFile = __DIR__ . '/inteldle.sqlite';
            $pdo = new PDO("sqlite:" . $dbFile, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Garante ativação de constraints, concorrência (WAL) e integridade referencial no SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA busy_timeout = 10000;");

            if ($singleton) {
                self::$instance = $pdo;
            }

            return $pdo;
        } catch (PDOException $e) {
            // Em caso de erro em chamadas de API ou páginas, logar e exibir erro controlado
            error_log("Database connection error: " . $e->getMessage());
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            http_response_code(500);
            die(json_encode([
                "sucesso" => false,
                "erro" => "Erro interno de conexão com o banco de dados."
            ], JSON_UNESCAPED_UNICODE));
        }
    }
}
