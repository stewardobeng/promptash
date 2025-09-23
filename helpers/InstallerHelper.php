<?php

class InstallerHelper
{
    /**
     * Check if Composer dependencies look installed.
     */
    public static function dependenciesInstalled(): bool
    {
        $basePath = dirname(__DIR__);
        $autoload = $basePath . '/vendor/autoload.php';

        if (!file_exists($autoload)) {
            return false;
        }

        $expectedDirs = [
            $basePath . '/vendor/composer',
        ];

        foreach ($expectedDirs as $dir) {
            if (!is_dir($dir)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Provide a summary of dependency status and missing pieces.
     */
    public static function getDependencyStatus(): array
    {
        $basePath = dirname(__DIR__);
        $missing = [];

        if (!file_exists($basePath . '/composer.json')) {
            $missing[] = 'composer.json';
        }

        if (!file_exists($basePath . '/composer.phar')) {
            $missing[] = 'composer.phar';
        }

        if (!file_exists($basePath . '/vendor/autoload.php')) {
            $missing[] = 'vendor/autoload.php';
        }

        return [
            'ready' => self::dependenciesInstalled(),
            'missing' => $missing,
        ];
    }

    /**
     * Attempt to install Composer dependencies using PHP processes.
     * Returns an array with success flag, output log, and an optional error message.
     */
    public static function ensureDependencies(): array
    {
        if (self::dependenciesInstalled()) {
            return [
                'success' => true,
                'message' => 'Dependencies are already installed.',
                'output' => '',
            ];
        }

        $basePath = dirname(__DIR__);
        $composerPhar = $basePath . '/composer.phar';
        $composerJson = $basePath . '/composer.json';

        if (!file_exists($composerPhar) || !file_exists($composerJson)) {
            return [
                'success' => false,
                'message' => 'composer.phar or composer.json is missing. Upload both files to continue.',
                'output' => '',
            ];
        }

        $command = self::buildComposerCommand($composerPhar);
        $env = self::buildComposerEnvironment($basePath);
        $result = self::runCommand($command, $basePath, $env);

        if (!$result['executed']) {
            return [
                'success' => false,
                'message' => $result['message'],
                'output' => $result['output'],
            ];
        }

        if (!self::dependenciesInstalled()) {
            return [
                'success' => false,
                'message' => 'Composer command finished but vendor files are still missing. Please check the output and run the command manually if needed.',
                'output' => $result['output'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Composer dependencies installed successfully.',
            'output' => $result['output'],
        ];
    }

    private static function buildComposerCommand(string $composerPhar): string
    {
        $phpBinary = PHP_BINARY;
        if (!$phpBinary || !is_executable($phpBinary)) {
            $phpBinary = 'php';
        }

        $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($composerPhar) . ' install --no-dev --no-interaction --optimize-autoloader';
        return $cmd;
    }

    private static function buildComposerEnvironment(string $basePath): array
    {
        $composerHome = $basePath . '/.composer';
        $composerCache = $composerHome . '/cache';

        if (!is_dir($composerHome)) {
            @mkdir($composerHome, 0775, true);
        }

        if (!is_dir($composerCache)) {
            @mkdir($composerCache, 0775, true);
        }

        $env = [
            'COMPOSER_HOME' => $composerHome,
            'COMPOSER_CACHE_DIR' => $composerCache,
        ];

        // Merge with existing environment variables.
        return array_merge($_ENV ?? [], $_SERVER ?? [], $env);
    }

    private static function runCommand(string $command, string $cwd, array $env): array
    {
        $disabled = explode(',', ini_get('disable_functions') ?: '');
        $disabled = array_map('trim', $disabled);

        $stdout = '';
        $stderr = '';
        $executed = false;
        $message = '';

        if (function_exists('proc_open') && !in_array('proc_open', $disabled, true)) {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                $executed = ($exitCode === 0);
                if (!$executed) {
                    $message = 'Composer exited with code ' . $exitCode . '. View the output for details.';
                }
            }
        } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
            $output = shell_exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1');
            if ($output !== null) {
                $executed = true;
                $stdout = $output;
            } else {
                $message = 'shell_exec returned no output. It may be disabled on this host.';
            }
        } elseif (function_exists('exec') && !in_array('exec', $disabled, true)) {
            $lines = [];
            $exitCode = 1;
            exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $lines, $exitCode);
            $stdout = implode(PHP_EOL, $lines);
            $executed = ($exitCode === 0);
            if (!$executed) {
                $message = 'exec exited with code ' . $exitCode . '.';
            }
        } else {
            return [
                'executed' => false,
                'output' => '',
                'message' => 'Server disables required PHP functions (proc_open, shell_exec, exec). Run composer manually or use the composer-install.php helper.',
            ];
        }

        return [
            'executed' => $executed,
            'output' => trim($stdout . (strlen($stderr) ? PHP_EOL . $stderr : '')),
            'message' => $message,
        ];
    }
}
