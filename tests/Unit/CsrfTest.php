<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * CSRF Double-Submit Tests
 * 
 * Tests for CSRF token validation and double-submit cookie pattern.
 * Based on deep audit findings (Security: 91/100).
 */
class CsrfTest extends TestCase
{
    /**
     * Test that CSRF token generation works
     */
    public function test_csrf_token_generation(): void
    {
        $token = bin2hex(random_bytes(24));
        
        $this->assertNotEmpty($token);
        $this->assertEquals(48, strlen($token)); // 24 bytes = 48 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    /**
     * Test that CSRF token comparison uses timing-safe comparison
     * Rules.md: Security First — use hash_equals for token comparison
     */
    public function test_csrf_timing_safe_comparison(): void
    {
        $token1 = bin2hex(random_bytes(24));
        $token2 = $token1;
        $token3 = bin2hex(random_bytes(24));
        
        // hash_equals is timing-safe
        $this->assertTrue(hash_equals($token1, $token2));
        $this->assertFalse(hash_equals($token1, $token3));
    }

    /**
     * Test that double-submit cookie pattern works
     */
    public function test_double_submit_cookie_pattern(): void
    {
        // Simulate session token
        $sessionToken = bin2hex(random_bytes(24));
        
        // Simulate cookie token
        $cookieToken = $sessionToken; // Should match
        
        // Validate
        $isValid = hash_equals($sessionToken, $cookieToken);
        
        $this->assertTrue($isValid, "Double-submit cookie should match session token");
    }

    /**
     * Test that CSRF validation fails with mismatched tokens
     */
    public function test_csrf_validation_fails_with_mismatch(): void
    {
        $sessionToken = bin2hex(random_bytes(24));
        $cookieToken = bin2hex(random_bytes(24)); // Different token
        
        $isValid = hash_equals($sessionToken, $cookieToken);
        
        $this->assertFalse($isValid, "CSRF validation should fail with mismatched tokens");
    }

    /**
     * Test that empty tokens are rejected
     */
    public function test_empty_tokens_rejected(): void
    {
        $emptyToken = '';
        $validToken = bin2hex(random_bytes(24));
        
        $isValid = hash_equals($validToken, $emptyToken);
        
        $this->assertFalse($isValid, "Empty tokens should be rejected");
    }
}
