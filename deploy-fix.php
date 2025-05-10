<?php

/**
 * Laravel Deployment Helper Script
 * 
 * This script helps with deployment by clearing caches and optimizing the application.
 * Run this script after composer install if you encounter timeout issues.
 */

echo "Starting deployment fix script...\n";

// Increase memory limit and execution time
ini_set('memory_limit', '2G');
ini_set('max_execution_time', 900);

// Define the artisan command function
function runArtisanCommand($command) {
    echo "Running: php artisan $command\n";
    $output = [];
    $return_var = 0;
    exec("php artisan $command 2>&1", $output, $return_var);
    
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

// Clear all caches
echo "Clearing caches...\n";
runArtisanCommand('cache:clear');
runArtisanCommand('config:clear');
runArtisanCommand('route:clear');
runArtisanCommand('view:clear');

// Run package discovery manually
echo "Running package discovery...\n";
runArtisanCommand('package:discover --ansi');

// Optimize the application
echo "Optimizing application...\n";
runArtisanCommand('optimize');
runArtisanCommand('view:cache');

// Cache routes in production
if (getenv('APP_ENV') === 'production') {
    echo "Caching routes for production...\n";
    runArtisanCommand('route:cache');
}

echo "Deployment fix script completed!\n";
echo "If you still encounter issues, try running 'composer install --no-scripts' followed by 'php deploy-fix.php'\n"; 