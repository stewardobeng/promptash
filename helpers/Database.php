<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        if (file_exists(__DIR__ . '/../config/database.php')) {
            require_once __DIR__ . '/../config/database.php';
            $this->host = DB_HOST;
            $this->db_name = DB_NAME;
            $this->username = DB_USER;
            $this->password = DB_PASS;
        }
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Log the error instead of displaying it
            error_log("Database connection error: " . $exception->getMessage());
            // Only display error in development mode or if it's from install script
            if (defined('INSTALL_MODE') || (defined('APP_DEBUG') && APP_DEBUG)) {
                echo "Connection error: " . $exception->getMessage();
            }
            $this->conn = null;
        }

        return $this->conn;
    }

    public function testConnection($host, $username, $password, $database = null) {
        try {
            $dsn = "mysql:host=" . $host;
            if ($database) {
                $dsn .= ";dbname=" . $database;
            }
            
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;
        } catch(PDOException $exception) {
            return false;
        }
    }

    public function createDatabase($host, $username, $password, $database) {
        try {
            $conn = new PDO("mysql:host=" . $host, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = "CREATE DATABASE IF NOT EXISTS `" . $database . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $conn->exec($sql);
            return true;
        } catch(PDOException $exception) {
            return false;
        }
    }

    public function executeSqlFile($sqlFile) {
        try {
            if (!file_exists($sqlFile)) {
                error_log("SQL file not found: " . $sqlFile);
                return false;
            }
            
            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                error_log("Could not read SQL file: " . $sqlFile);
                return false;
            }
            
            // Use shared hosting compatible execution
            return $this->executeSqlViaPdoSharedHosting($sql);
            
        } catch(Exception $exception) {
            error_log("SQL file execution error: " . $exception->getMessage());
            if (defined('INSTALL_MODE')) {
                echo "<div class='alert alert-danger'>Error executing SQL file: " . htmlspecialchars($exception->getMessage()) . "</div>";
            }
            return false;
        }
    }
    
    /**
     * Execute schema file on Windows systems using command line
     */
    private function executeSchemaFileWindows($originalFile, $sql) {
        try {
            // For shared hosting compatibility, avoid command line execution
            // Instead, use the improved PDO method
            return $this->executeSqlViaPdoSharedHosting($sql);
        } catch(Exception $e) {
            error_log("Windows schema execution error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute SQL via PDO - Shared hosting compatible
     */
    private function executeSqlViaPdoSharedHosting($sql) {
        try {
            $conn = $this->getConnection();
            if (!$conn) {
                throw new Exception("Database connection failed");
            }
            
            // Clean up SQL for shared hosting
            $sql = $this->cleanSqlForSharedHosting($sql);
            
            // Split into statements
            $statements = $this->splitSqlStatements($sql);
            
            $executed = 0;
            $errors = [];
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                try {
                    $conn->exec($statement);
                    $executed++;
                } catch(PDOException $e) {
                    $error_msg = "Statement failed: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage();
                    $errors[] = $error_msg;
                    error_log($error_msg);
                    
                    if (defined('INSTALL_MODE')) {
                        // For critical errors during installation, show detailed error
                        if (stripos($e->getMessage(), 'syntax error') !== false || 
                            stripos($e->getMessage(), 'unknown column') !== false) {
                            throw new Exception($e->getMessage());
                        }
                    }
                }
            }
            
            if (defined('INSTALL_MODE')) {
                error_log("SQL execution completed: {$executed} statements executed, " . count($errors) . " errors");
                if (!empty($errors) && $executed === 0) {
                    throw new Exception("No statements executed successfully. First error: " . $errors[0]);
                }
            }
            
            return $executed > 0;
            
        } catch(Exception $exception) {
            error_log("SQL execution failed: " . $exception->getMessage());
            if (defined('INSTALL_MODE')) {
                echo "<div class='alert alert-danger'>Database Error: " . htmlspecialchars($exception->getMessage()) . "</div>";
            }
            return false;
        }
    }
    
    /**
     * Clean SQL for shared hosting compatibility
     */
    private function cleanSqlForSharedHosting($sql) {
        // Remove MySQL-specific SET statements that might not be allowed
        $sql = preg_replace('/^\s*SET\s+(SQL_MODE|AUTOCOMMIT|time_zone)\s*=.*?;/im', '', $sql);
        
        // Remove MySQL version-specific comments
        $sql = preg_replace('/\/\*!\d+.*?\*\//s', '', $sql);
        
        // Remove standalone transaction control (we'll handle it differently)
        $sql = preg_replace('/^\s*(START\s+TRANSACTION|COMMIT)\s*;/im', '', $sql);
        
        // Remove comment lines
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        return $sql;
    }
    
    /**
     * Split SQL into individual statements safely
     */
    private function splitSqlStatements($sql) {
        $statements = [];
        $current_statement = '';
        $in_string = false;
        $string_char = '';
        $escaped = false;
        
        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];
            
            if ($escaped) {
                $escaped = false;
                $current_statement .= $char;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                $current_statement .= $char;
                continue;
            }
            
            if (!$in_string && ($char === '"' || $char === "'")) {
                $in_string = true;
                $string_char = $char;
            } elseif ($in_string && $char === $string_char) {
                $in_string = false;
                $string_char = '';
            }
            
            if (!$in_string && $char === ';') {
                $statement = trim($current_statement);
                if (!empty($statement) && !preg_match('/^\s*(--|#)/', $statement)) {
                    $statements[] = $statement;
                }
                $current_statement = '';
            } else {
                $current_statement .= $char;
            }
        }
        
        // Add final statement if exists
        $statement = trim($current_statement);
        if (!empty($statement) && !preg_match('/^\s*(--|#)/', $statement)) {
            $statements[] = $statement;
        }
        
        return $statements;
    }
}
?>

