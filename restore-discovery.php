<?php

/**
 * This script restores the original package discovery settings in composer.json
 * after deployment is complete.
 */

echo "Restoring original package discovery settings in composer.json...\n";

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

// Check if we have original settings to restore
if (!isset($composer['extra']['laravel']['original-dont-discover'])) {
    echo "No original settings found to restore.\n";
    exit(0);
}

// Restore the original dont-discover setting
$composer['extra']['laravel']['dont-discover'] = $composer['extra']['laravel']['original-dont-discover'];

// Remove the backup setting
unset($composer['extra']['laravel']['original-dont-discover']);

// Write the modified composer.json
file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Restored original package discovery settings.\n";
echo "Now run the following commands to complete the setup:\n";
echo "/opt/plesk/php/8.2/bin/php -d memory_limit=1G artisan cache:clear\n";
echo "/opt/plesk/php/8.2/bin/php -d memory_limit=1G artisan config:clear\n";
echo "/opt/plesk/php/8.2/bin/php -d memory_limit=1G artisan route:clear\n";
echo "/opt/plesk/php/8.2/bin/php -d memory_limit=1G artisan view:clear\n";
echo "/opt/plesk/php/8.2/bin/php -d memory_limit=1G artisan optimize\n"; 