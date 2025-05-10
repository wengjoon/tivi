<?php

/**
 * Manual Deployment Script for Laravel
 * 
 * This script handles the deployment process manually without relying on package discovery
 * which is causing errors with the Tinker command.
 */

echo "Starting manual deployment process...\n";

// Define the PHP binary path and memory limit
$php_binary = '/opt/plesk/php/8.2/bin/php';
$memory_limit = '1G';

// Function to run commands with proper error handling
function runCommand($command) {
    echo "Running: $command\n";
    $output = [];
    $return_var = 0;
    exec($command . " 2>&1", $output, $return_var);
    
    foreach ($output as $line) {
        echo "  $line\n";
    }
    
    if ($return_var !== 0) {
        echo "Warning: Command exited with status $return_var\n";
        return false;
    } else {
        echo "Command completed successfully\n";
        return true;
    }
}

// Step 1: Modify composer.json to disable package discovery
echo "\n=== STEP 1: Disabling package discovery ===\n";

$composerFile = __DIR__ . '/composer.json';

if (!file_exists($composerFile)) {
    echo "Error: composer.json not found!\n";
    exit(1);
}

// Read the current composer.json
$composerJson = file_get_contents($composerFile);
$composer = json_decode($composerJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error parsing composer.json: " . json_last_error_msg() . "\n";
    exit(1);
}

// Backup the original file
$backupFile = $composerFile . '.bak';
if (!file_exists($backupFile)) {
    file_put_contents($backupFile, $composerJson);
    echo "Created backup at composer.json.bak\n";
}

// Modify the laravel extra section to disable discovery
if (!isset($composer['extra'])) {
    $composer['extra'] = [];
}

if (!isset($composer['extra']['laravel'])) {
    $composer['extra']['laravel'] = [];
}

// Save the current dont-discover setting
$currentDontDiscover = isset($composer['extra']['laravel']['dont-discover']) 
    ? $composer['extra']['laravel']['dont-discover'] 
    : [];

// Store the original setting if not already stored
if (!isset($composer['extra']['laravel']['original-dont-discover'])) {
    $composer['extra']['laravel']['original-dont-discover'] = $currentDontDiscover;
}

// Set dont-discover to all packages
$composer['extra']['laravel']['dont-discover'] = ['*'];

// Temporarily modify the post-autoload-dump script to avoid package discovery
if (isset($composer['scripts']['post-autoload-dump'])) {
    $composer['scripts']['original-post-autoload-dump'] = $composer['scripts']['post-autoload-dump'];
    $composer['scripts']['post-autoload-dump'] = [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump"
    ];
}

// Write the modified composer.json
file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Modified composer.json to disable package discovery and skip problematic scripts\n";

// Step 2: Run composer install with no-scripts option
echo "\n=== STEP 2: Installing dependencies ===\n";
runCommand("composer install --no-dev --optimize-autoloader --no-scripts");

// Step 3: Generate autoload files manually
echo "\n=== STEP 3: Generating autoload files ===\n";
runCommand("composer dump-autoload --optimize");

// Step 4: Clear all caches
echo "\n=== STEP 4: Clearing caches ===\n";
runCommand("$php_binary -d memory_limit=$memory_limit artisan cache:clear");
runCommand("$php_binary -d memory_limit=$memory_limit artisan config:clear");
runCommand("$php_binary -d memory_limit=$memory_limit artisan route:clear");
runCommand("$php_binary -d memory_limit=$memory_limit artisan view:clear");

// Step 5: Optimize the application
echo "\n=== STEP 5: Optimizing application ===\n";
runCommand("$php_binary -d memory_limit=$memory_limit artisan optimize");

// Step 6: Restore original composer.json
echo "\n=== STEP 6: Restoring original composer.json ===\n";

// Read the current composer.json again (it might have changed)
$composerJson = file_get_contents($composerFile);
$composer = json_decode($composerJson, true);

// Restore the original dont-discover setting
if (isset($composer['extra']['laravel']['original-dont-discover'])) {
    $composer['extra']['laravel']['dont-discover'] = $composer['extra']['laravel']['original-dont-discover'];
    unset($composer['extra']['laravel']['original-dont-discover']);
}

// Restore the original post-autoload-dump script
if (isset($composer['scripts']['original-post-autoload-dump'])) {
    $composer['scripts']['post-autoload-dump'] = $composer['scripts']['original-post-autoload-dump'];
    unset($composer['scripts']['original-post-autoload-dump']);
}

// Write the restored composer.json
file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Restored original composer.json settings\n";

echo "\n=== Deployment completed! ===\n";
echo "Your application should now be ready to use.\n";
echo "If you encounter any issues, try running these commands manually:\n";
echo "$php_binary -d memory_limit=$memory_limit artisan optimize\n";
echo "$php_binary -d memory_limit=$memory_limit artisan config:cache\n";
echo "$php_binary -d memory_limit=$memory_limit artisan route:cache\n"; 