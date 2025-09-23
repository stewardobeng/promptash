<?php
// #############################################################################################
// #
// # SERVER TROUBLESHOOTING SCRIPT
// #
// #############################################################################################

// --- CONFIGURATION ---
// No need to change anything here.
define('ROOT_PATH', __DIR__);
define('VENDOR_PATH', ROOT_PATH . '/vendor');
define('AUTOLOAD_PATH', VENDOR_PATH . '/autoload.php');

// --- HELPER FUNCTIONS ---
function check_path_exists($path, $type = 'File') {
    if (file_exists($path)) {
        return '<span class="success">Exists</span>';
    }
    return '<span class="error">MISSING</span>';
}

function check_writable($path) {
    if (is_writable($path)) {
        return '<span class="success">Writable</span>';
    }
    return '<span class="error">NOT Writable</span>';
}

function check_function_exists($function_name) {
    if (function_exists($function_name) && is_callable($function_name)) {
        $disabled = false;
        $disabled_functions = @ini_get('disable_functions');
        if ($disabled_functions) {
            $disabled_array = array_map('trim', explode(',', $disabled_functions));
            if (in_array($function_name, $disabled_array)) {
                $disabled = true;
            }
        }
        if ($disabled) {
            return '<span class="error">DISABLED in php.ini</span>';
        }
        return '<span class="success">ENABLED</span>';
    }
    return '<span class="error">DOES NOT EXIST</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server Troubleshooter</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; background-color: #f8f9fa; color: #212529; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1, h2 { color: #007bff; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
        th { background-color: #e9ecef; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        code { background: #e9ecef; padding: 2px 5px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Server Environment Report</h1>

        <h2>PHP &amp; Server Configuration</h2>
        <table>
            <tr>
                <th>Check</th>
                <th>Result</th>
            </tr>
            <tr>
                <td>PHP Version</td>
                <td><b><?php echo phpversion(); ?></b></td>
            </tr>
            <tr>
                <td><code>shell_exec</code> Status</td>
                <td><?php echo check_function_exists('shell_exec'); ?></td>
            </tr>
             <tr>
                <td>Memory Limit</td>
                <td><?php echo @ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <td>Max Execution Time</td>
                <td><?php echo @ini_get('max_execution_time'); ?> seconds</td>
            </tr>
        </table>

        <h2>File System &amp; Permissions</h2>
        <table>
            <tr>
                <th>Check</th>
                <th>Result</th>
            </tr>
            <tr>
                <td>Project Root Directory Writable?</td>
                <td><?php echo check_writable(ROOT_PATH); ?></td>
            </tr>
            <tr>
                <td><code>vendor</code> Directory Exists?</td>
                <td><?php echo check_path_exists(VENDOR_PATH, 'Directory'); ?></td>
            </tr>
            <tr>
                <td><code>vendor/autoload.php</code> Exists?</td>
                <td><?php echo check_path_exists(AUTOLOAD_PATH, 'File'); ?></td>
            </tr>
        </table>
        
        <h2>Conclusion &amp; Next Steps</h2>
        <?php
            $shell_enabled = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', @ini_get('disable_functions'))));
            $autoload_exists = file_exists(AUTOLOAD_PATH);

            if (!$shell_enabled) {
                echo "<p class='error'><b>Primary Issue:</b> The <code>shell_exec</code> function is disabled on your server. This means the Composer installer script could not run, which is why the required libraries were never installed correctly.</p>";
                echo "<p><b>Next Step:</b> Contact your hosting provider's support and ask them to enable the <code>shell_exec</code> function for your account. If they cannot, you will need to install the vendor directory locally and upload it as a ZIP file (as described previously).</p>";
            } elseif (!$autoload_exists) {
                echo "<p class='error'><b>Primary Issue:</b> The <code>vendor/autoload.php</code> file is missing. This means the Composer installation did not complete.</p>";
                echo "<p><b>Next Step:</b> This could be due to a timeout or a memory limit. Try running the <code>composer-install.php</code> script again. If it still fails, your server may not have enough resources (memory/time) to complete the installation. Contact your host to ask about increasing the PHP <code>memory_limit</code> and <code>max_execution_time</code>.</p>";
            } else {
                echo "<p class='success'><b>Good News:</b> Your server environment looks OK! The <code>shell_exec</code> function is enabled and the <code>vendor/autoload.php</code> file exists.</p>";
                echo "<p>If you are still seeing the 'Class not found' error, it means the installation was likely incomplete or corrupted. The safest next step is to perform a clean installation:</p>";
                echo "<ol><li>Using your file manager, <b>delete the entire `vendor` directory</b>.</li><li>Run the <code>composer-install.php</code> script again to have it rebuild everything from scratch.</li></ol>";
            }
        ?>
        <hr>
        <p class="warning">
            IMPORTANT: For security reasons, please delete this file (troubleshoot.php) from your server once you are done.
        </p>
    </div>
</body>
</html>