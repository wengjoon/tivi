<?php

/**
 * This script temporarily modifies composer.json to disable package discovery
 * which is causing memory issues during deployment.
 */

echo "Modifying composer.json to disable package discovery...\n";

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

// Write the modified composer.json
file_put_contents($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Modified composer.json to disable package discovery.\n";
echo "Now run: composer install --no-dev --optimize-autoloader\n";
echo "After deployment is complete, run restore-discovery.php to restore original settings.\n"; 