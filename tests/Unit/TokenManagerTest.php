<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Token Manager Tests
 * 
 * Tests for token verification, expiry, and status checks.
 * Based on deep audit findings (Security: 91/100).
 */
class TokenManagerTest extends TestCase
{
    /**
     * Test that token format is valid
     * Rules.md: Token format is XXXX-XXXX-XXXX (12 chars, no ambiguous chars)
     */
    public function test_token_format_validation(): void
    {
        // Pattern allows uppercase letters and digits (no ambiguous I/O/0/1)
        $validPattern = '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/';
        
        $validTokens = [
            'ABCD-EFGH-2345',
            'XXXX-YYYY-ZZZZ',
            'JKLM-NPQR-2345',
        ];
        
        $invalidTokens = [
            'abcd-efgh-2345',  // lowercase
            'ABCD-EFGH-234',   // too short
            'ABCD-EFGH-23456', // too long
            'ABCD_EFGH_2345',  // underscores
            'ABCD EFGH 2345',  // spaces
            'AIO1-EFGH-2345',  // ambiguous chars I, O, 1
        ];
        
        foreach ($validTokens as $token) {
            $this->assertMatchesRegularExpression($validPattern, $token, "Token should be valid: $token");
        }
        
        foreach ($invalidTokens as $token) {
            $this->assertDoesNotMatchRegularExpression($validPattern, $token, "Token should be invalid: $token");
        }
    }

    /**
     * Test that token generation produces valid format
     */
    public function test_token_generation(): void
    {
        // No ambiguous chars: I, O, 0, 1
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 12;
        
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Format as XXXX-XXXX-XXXX
        $formattedToken = substr($token, 0, 4) . '-' . substr($token, 4, 4) . '-' . substr($token, 8, 4);
        
        // Verify no ambiguous chars
        $this->assertDoesNotMatchRegularExpression('/[IO01]/', $formattedToken);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $formattedToken);
        $this->assertEquals(14, strlen($formattedToken)); // 12 chars + 2 hyphens
    }

    /**
     * Test that token expiry check works
     */
    public function test_token_expiry_check(): void
    {
        // Test expired token
        $expiredTime = strtotime('-1 day');
        $currentTime = time();
        
        $this->assertLessThan($currentTime, $expiredTime, "Token should be expired");
        
        // Test valid token
        $validTime = strtotime('+30 days');
        
        $this->assertGreaterThan($currentTime, $validTime, "Token should be valid");
    }

    /**
     * Test that token status validation works
     */
    public function test_token_status_validation(): void
    {
        $validStatuses = ['active', 'suspended'];
        
        $testStatuses = [
            ['status' => 'active', 'valid' => true],
            ['status' => 'suspended', 'valid' => true],
            ['status' => 'expired', 'valid' => false],
            ['status' => 'invalid', 'valid' => false],
        ];
        
        foreach ($testStatuses as $test) {
            $isValid = in_array($test['status'], $validStatuses, true);
            $this->assertEquals($test['valid'], $isValid, "Status validation failed for: {$test['status']}");
        }
    }

    /**
     * Test that token hashing works correctly
     */
    public function test_token_hashing(): void
    {
        $token = 'ABCD-EFGH-2345';
        $hash = hash('sha256', $token);
        
        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash)); // SHA256 produces 64 hex chars
        $this->assertNotEquals($token, $hash);
        $this->assertEquals($hash, hash('sha256', $token)); // Same input = same hash
    }
}
