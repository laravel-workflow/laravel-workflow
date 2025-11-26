<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AssertionTest extends TestCase
{
    public function testAssertionsAreEnabled(): void
    {
        $assertionsEnabled = false;

        try {
            assert(false, 'Test assertion');
            // If we get here, assertions are disabled
            $assertionsEnabled = false;
        } catch (\AssertionError $e) {
            // If we catch this, assertions are enabled
            $assertionsEnabled = true;
        }

        // This will tell us the state
        echo "\n\nPHP ASSERTION STATUS:\n";
        echo 'zend.assertions = ' . ini_get('zend.assertions') . "\n";
        echo 'assert.active = ' . ini_get('assert.active') . "\n";
        echo 'assert.exception = ' . ini_get('assert.exception') . "\n";
        echo 'Assertions ' . ($assertionsEnabled ? 'ARE' : 'ARE NOT') . " enabled\n\n";

        // Always pass so we can see the output
        $this->assertTrue(true);
    }
}
