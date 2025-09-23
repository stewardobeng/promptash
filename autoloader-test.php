<?php

echo "<h1>Autoloader Test</h1>";

// Define the path to your main config file
$configPath = __DIR__ . '/config/config.php';
echo "<p>Looking for config file at: <code>" . $configPath . "</code></p>";

if (!file_exists($configPath)) {
    die("<p style='color: red; font-weight: bold;'>Error: config.php not found. Please check the path.</p>");
}

// Attempt to include the config file, which should load the autoloader
require_once $configPath;
echo "<p style='color: green; font-weight: bold;'>Successfully included config.php.</p>";

// Now, let's check if the WebAuthn class is available
$className = 'WebAuthn\\WebAuthn';
echo "<p>Checking if class exists: <code>" . $className . "</code></p>";

if (class_exists($className)) {
    echo "<h2 style='color: green; font-weight: bold;'>✅ SUCCESS!</h2>";
    echo "<p>The autoloader is working correctly and the 'WebAuthn' class was found.</p>";
} else {
    echo "<h2 style='color: red; font-weight: bold;'>❌ FAILURE!</h2>";
    echo "<p>The autoloader is NOT working correctly. The 'WebAuthn' class could not be found, even though the autoloader file was included.</p>";
    echo "<p><b>This confirms the problem is with server file permissions, ownership, or a corrupted vendor directory.</b></p>";
}

?>