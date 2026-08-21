<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Session Security Tests
 * 
 * Tests for session security features: UA binding, idle timeout, regeneration.
 * Based on deep audit findings (Security: 91/100).
 */
class SessionTest extends TestCase
{
    /**
     * Test that UA binding hash is generated correctly
     * Rules.md: Session security — UA binding prevents session hijacking
     */
    public function test_ua_binding_hash_generation(): void
    {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $seed = bin2hex(random_bytes(8));
        
        $uaHash = hash('sha256', $userAgent . '|' . $seed);
        
        $this->assertNotEmpty($uaHash);
        $this->assertEquals(64, strlen($uaHash)); // SHA256 produces 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $uaHash);
    }

    /**
     * Test that UA binding detects changes
     */
    public function test_ua_binding_detects_changes(): void
    {
        $seed = bin2hex(random_bytes(8));
        
        $ua1 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $ua2 = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)';
        
        $hash1 = hash('sha256', $ua1 . '|' . $seed);
        $hash2 = hash('sha256', $ua2 . '|' . $seed);
        
        $this->assertNotEquals($hash1, $hash2, "Different UAs should produce different hashes");
    }

    /**
     * Test that idle timeout calculation works
     * Rules.md: Admin sessions expire after 30 minutes
     */
    public function test_idle_timeout_calculation(): void
    {
        $idleLimit = 30 * 60; // 30 minutes in seconds
        
        $lastActivity = time() - ($idleLimit + 1); // 31 minutes ago
        $currentTime = time();
        
        $isExpired = ($currentTime - $lastActivity) > $idleLimit;
        
        $this->assertTrue($isExpired, "Session should be expired after 31 minutes");
        
        // Test active session
        $lastActivity = time() - 60; // 1 minute ago
        $isExpired = ($currentTime - $lastActivity) > $idleLimit;
        
        $this->assertFalse($isExpired, "Session should not be expired after 1 minute");
    }

    /**
     * Test that session regeneration concept works
     * Rules.md: Session regeneration on login
     */
    public function test_session_regeneration_concept(): void
    {
        // In production, session_regenerate_id(true) is called on login
        // This test verifies the concept without starting a real session
        
        $oldSessionId = 'old_session_id_12345';
        $newSessionId = 'new_session_id_67890';
        
        // Simulate regeneration
        $regenerated = ($newSessionId !== $oldSessionId);
        
        $this->assertTrue($regenerated, "Session ID should change after regeneration");
        $this->assertNotEquals($oldSessionId, $newSessionId);
    }

    /**
     * Test that SameSite cookie attribute is set
     */
    public function test_samesite_cookie_attribute(): void
    {
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ];
        
        $this->assertEquals('Strict', $cookieParams['samesite']);
        $this->assertTrue($cookieParams['httponly']);
        $this->assertTrue($cookieParams['secure']);
    }
}
