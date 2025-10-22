<?php

// Load environment variables based on test suite
if (getenv('GITHUB_ACTIONS') !== 'true') {
    $envFile = null;
    
    // Try to determine which env file to load
    if (defined('TESTBENCH_WORKING_PATH')) {
        $testsPath = TESTBENCH_WORKING_PATH . '/tests';
        
        // Check if we're running feature tests
        if (file_exists($testsPath . '/.env.feature')) {
            $envFile = $testsPath . '/.env.feature';
        } elseif (file_exists($testsPath . '/.env.unit')) {
            $envFile = $testsPath . '/.env.unit';
        }
    }
    
    // Load the environment file if found
    if ($envFile && file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                if (!getenv($name)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}
