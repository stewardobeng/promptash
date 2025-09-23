<?php
/**
 * Database Migration Runner
 * Handles database schema updates for the membership system
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class MigrationRunner {
    private $db;
    private $migrations_table = 'schema_migrations';
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->ensureMigrationsTable();
    }
    
    /**
     * Ensure the migrations tracking table exists
     */
    private function ensureMigrationsTable() {
        $query = "CREATE TABLE IF NOT EXISTS `{$this->migrations_table}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `migration_name` VARCHAR(255) NOT NULL UNIQUE,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `execution_time_ms` INT DEFAULT 0,
            `status` ENUM('success', 'failed') DEFAULT 'success',
            `error_message` TEXT NULL,
            INDEX `idx_migration_name` (`migration_name`),
            INDEX `idx_executed_at` (`executed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($query);
    }
    
    /**
     * Check if a migration has been executed
     */
    private function isMigrationExecuted($migration_name) {
        $query = "SELECT COUNT(*) as count FROM {$this->migrations_table} 
                 WHERE migration_name = :migration_name AND status = 'success'";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':migration_name', $migration_name);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Record migration execution
     */
    private function recordMigration($migration_name, $execution_time_ms, $status = 'success', $error_message = null) {
        $query = "INSERT INTO {$this->migrations_table} 
                 (migration_name, execution_time_ms, status, error_message) 
                 VALUES (:migration_name, :execution_time_ms, :status, :error_message)";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':migration_name', $migration_name);
        $stmt->bindParam(':execution_time_ms', $execution_time_ms);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':error_message', $error_message);
        
        return $stmt->execute();
    }
    
    /**
     * Execute a SQL migration file
     */
    public function executeMigration($migration_file, $migration_name = null) {
        if (!$migration_name) {
            $migration_name = basename($migration_file, '.sql');
        }
        
        if ($this->isMigrationExecuted($migration_name)) {
            echo "Migration '{$migration_name}' already executed. Skipping.\n";
            return true;
        }
        
        if (!file_exists($migration_file)) {
            echo "Migration file '{$migration_file}' not found.\n";
            return false;
        }
        
        echo "Executing migration: {$migration_name}\n";
        
        $start_time = microtime(true);
        
        try {
            // Read and execute the migration file
            $sql_content = file_get_contents($migration_file);
            
            // Remove transaction statements as we handle them here
            $sql_content = str_replace(['START TRANSACTION;', 'COMMIT;'], '', $sql_content);
            
            // Split by semicolons and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $sql_content)));
            
            $this->db->beginTransaction();
            
            foreach ($statements as $statement) {
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue; // Skip empty statements and comments
                }
                
                $this->db->exec($statement);
            }
            
            $this->db->commit();
            
            $execution_time = round((microtime(true) - $start_time) * 1000);
            $this->recordMigration($migration_name, $execution_time, 'success');
            
            echo "Migration '{$migration_name}' executed successfully in {$execution_time}ms.\n";
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            $execution_time = round((microtime(true) - $start_time) * 1000);
            $error_message = $e->getMessage();
            $this->recordMigration($migration_name, $execution_time, 'failed', $error_message);
            
            echo "Migration '{$migration_name}' failed: {$error_message}\n";
            return false;
        }
    }
    
    /**
     * Get migration status
     */
    public function getMigrationStatus() {
        $query = "SELECT migration_name, executed_at, execution_time_ms, status, error_message 
                 FROM {$this->migrations_table} 
                 ORDER BY executed_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Display migration status
     */
    public function displayMigrationStatus() {
        $migrations = $this->getMigrationStatus();
        
        if (empty($migrations)) {
            echo "No migrations have been executed yet.\n";
            return;
        }
        
        echo "Migration Status:\n";
        echo "================\n";
        
        foreach ($migrations as $migration) {
            $status = $migration['status'] === 'success' ? '✓' : '✗';
            $time = $migration['execution_time_ms'] . 'ms';
            $date = date('Y-m-d H:i:s', strtotime($migration['executed_at']));
            
            echo sprintf("%s %-40s %s (%s)\n", 
                $status, 
                $migration['migration_name'], 
                $date, 
                $time
            );
            
            if ($migration['status'] === 'failed' && $migration['error_message']) {
                echo "   Error: {$migration['error_message']}\n";
            }
        }
    }
    
    /**
     * Run the membership system migration
     */
    public function runMembershipMigration() {
        echo "=== Membership System Migration ===\n\n";
        
        // Check if we're working with a fresh installation (has complete schema)
        $migration_file = __DIR__ . '/../database_complete.sql';
        
        if (!file_exists($migration_file)) {
            echo "\n✗ Migration failed: database_complete.sql not found!\n";
            echo "Please ensure you have the complete database schema file.\n";
            return false;
        }
        
        // For existing installations, we need to check what's missing rather than running full schema
        if ($this->hasExistingData()) {
            echo "Existing installation detected. Checking for missing components...\n";
            $result = $this->verifyAndUpdateSchema();
        } else {
            echo "Fresh installation detected. Running complete schema setup...\n";
            $result = $this->executeMigration($migration_file, 'membership_system_v1.1.0');
        }
        
        if ($result) {
            echo "\n✓ Membership system migration completed successfully!\n";
            echo "\nNext steps:\n";
            echo "1. Configure Paystack API keys in admin settings\n";
            echo "2. Test the membership functionality\n";
            echo "3. Set up usage limit notifications\n";
        } else {
            echo "\n✗ Membership system migration failed!\n";
            echo "Please check the error messages above and fix any issues.\n";
        }
        
        return $result;
    }
    
    /**
     * Check if installation has existing data
     */
    private function hasExistingData() {
        try {
            // Check if users table exists and has data
            $query = "SHOW TABLES LIKE 'users'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                return false; // No users table = fresh install
            }
            
            // Check if users table has data
            $query = "SELECT COUNT(*) as count FROM users";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false; // Assume fresh install on error
        }
    }
    
    /**
     * Verify and update schema for existing installations
     */
    private function verifyAndUpdateSchema() {
        echo "Checking for missing tables and columns...\n";
        
        $updates_needed = [];
        
        // Check for membership_tiers table
        if (!$this->tableExists('membership_tiers')) {
            $updates_needed[] = 'membership_tiers';
        }
        
        // Check for usage_tracking table
        if (!$this->tableExists('usage_tracking')) {
            $updates_needed[] = 'usage_tracking';
        }
        
        // Check for current_tier_id column in users table
        if (!$this->columnExists('users', 'current_tier_id')) {
            $updates_needed[] = 'users.current_tier_id';
        }
        
        if (empty($updates_needed)) {
            echo "✓ All required tables and columns exist!\n";
            return true;
        }
        
        echo "Missing components detected: " . implode(', ', $updates_needed) . "\n";
        echo "For existing installations, please manually add missing components or use the complete schema for fresh installs.\n";
        
        return false;
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($table_name) {
        try {
            $query = "SHOW TABLES LIKE :table_name";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':table_name', $table_name);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if column exists in table
     */
    private function columnExists($table_name, $column_name) {
        try {
            $query = "SHOW COLUMNS FROM `{$table_name}` LIKE :column_name";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':column_name', $column_name);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verify database schema integrity
     */
    public function verifySchema() {
        echo "=== Schema Verification ===\n\n";
        
        $required_tables = [
            'users',
            'membership_tiers',
            'user_subscriptions',
            'usage_tracking',
            'payment_transactions',
            'usage_notifications',
            'user_notifications'
        ];
        
        $missing_tables = [];
        
        foreach ($required_tables as $table) {
            $query = "SHOW TABLES LIKE '{$table}'";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                $missing_tables[] = $table;
            } else {
                echo "✓ Table '{$table}' exists\n";
            }
        }
        
        if (!empty($missing_tables)) {
            echo "\n✗ Missing tables: " . implode(', ', $missing_tables) . "\n";
            echo "Please run the membership migration to create missing tables.\n";
            return false;
        }
        
        echo "\n✓ All required tables exist!\n";
        return true;
    }
}

// Command line interface
if (isset($argv) && basename($argv[0]) === basename(__FILE__)) {
    $migration_runner = new MigrationRunner();
    
    $command = $argv[1] ?? 'help';
    
    switch ($command) {
        case 'membership':
            $migration_runner->runMembershipMigration();
            break;
            
        case 'status':
            $migration_runner->displayMigrationStatus();
            break;
            
        case 'verify':
            $migration_runner->verifySchema();
            break;
            
        case 'help':
        default:
            echo "Database Migration Runner\n";
            echo "Usage: php migrate.php [command]\n\n";
            echo "Commands:\n";
            echo "  membership  - Run the membership system migration\n";
            echo "  status      - Show migration execution status\n";
            echo "  verify      - Verify database schema integrity\n";
            echo "  help        - Show this help message\n";
            break;
    }
}
?>