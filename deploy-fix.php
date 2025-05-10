<?php

/**
 * Laravel Deployment Helper Script
 * 
 * This script helps with deployment by clearing caches and optimizing the application.
 * Run this script after composer install if you encounter timeout issues.
 */

echo "Starting deployment fix script...\n";

// Define the artisan command function with memory limit
function runArtisanCommand($command) {
    $php_binary = '/opt/plesk/php/8.2/bin/php'; // Use the specific PHP binary path
    $memory_limit = '1G';
    
    echo "Running: $php_binary -d memory_limit=$memory_limit artisan $command\n";
    $output = [];
    $return_var = 0;
    exec("$php_binary -d memory_limit=$memory_limit artisan $command 2>&1", $output, $return_var);
    
    foreach ($output as $line) {
        echo "  $line\n";
    }
    
    if ($return_var !== 0) {
        echo "Warning: Command exited with status $return_var\n";
    } else {
        echo "Command completed successfully\n";
    }
    
    echo "\n";
    return $return_var === 0;
}

// Run each command with a small delay between them
echo "Clearing caches...\n";
runArtisanCommand('cache:clear');
sleep(2);
runArtisanCommand('config:clear');
sleep(2);
runArtisanCommand('route:clear');
sleep(2);
runArtisanCommand('view:clear');
sleep(2);

// Run package discovery manually
echo "Running package discovery...\n";
runArtisanCommand('package:discover --ansi');
sleep(2);

// Optimize the application
echo "Optimizing application...\n";
runArtisanCommand('optimize');
sleep(2);
runArtisanCommand('view:cache');
sleep(2);

// Cache routes in production
if (getenv('APP_ENV') === 'production') {
    echo "Caching routes for production...\n";
    runArtisanCommand('route:cache');
}

echo "Deployment fix script completed!\n";
echo "If you still encounter issues, try running these commands individually:\n";
echo "$php_binary -d memory_limit=1G artisan cache:clear\n";
echo "$php_binary -d memory_limit=1G artisan config:clear\n";
echo "$php_binary -d memory_limit=1G artisan route:clear\n";
echo "$php_binary -d memory_limit=1G artisan view:clear\n";
echo "$php_binary -d memory_limit=1G artisan package:discover --ansi\n";
echo "$php_binary -d memory_limit=1G artisan optimize\n";
echo "$php_binary -d memory_limit=1G artisan view:cache\n"; 