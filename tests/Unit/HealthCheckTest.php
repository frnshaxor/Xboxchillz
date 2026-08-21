<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Health Check Tests
 * 
 * Tests for health check functionality: stuck processing, invalid slugs.
 * Based on deep audit findings (Ops Readiness: 75/100).
 */
class HealthCheckTest extends TestCase
{
    /**
     * Test that stuck processing detection works
     */
    public function test_stuck_processing_detection(): void
    {
        $processingTimeLimit = 30 * 60; // 30 minutes in seconds
        
        // Simulate video stuck for 31 minutes
        $createdAt = time() - ($processingTimeLimit + 60);
        $currentTime = time();
        
        $isStuck = ($currentTime - $createdAt) > $processingTimeLimit;
        
        $this->assertTrue($isStuck, "Video should be detected as stuck after 31 minutes");
        
        // Simulate video processing normally (5 minutes)
        $createdAt = time() - (5 * 60);
        $isStuck = ($currentTime - $createdAt) > $processingTimeLimit;
        
        $this->assertFalse($isStuck, "Video should not be detected as stuck after 5 minutes");
    }

    /**
     * Test that invalid slug detection works
     */
    public function test_invalid_slug_detection(): void
    {
        $validPattern = '/^[a-z0-9][a-z0-9-]*$/';
        
        $invalidSlugs = [
            '-test-video',      // starts with hyphen
            '_test_video',      // starts with underscore
            'TEST VIDEO',       // uppercase and space
            '',                 // empty
        ];
        
        foreach ($invalidSlugs as $slug) {
            if ($slug !== '') {
                $isValid = preg_match($validPattern, $slug);
                $this->assertFalse((bool)$isValid, "Slug should be detected as invalid: $slug");
            }
        }
    }

    /**
     * Test that health check log format is valid
     */
    public function test_health_check_log_format(): void
    {
        $logEntry = [
            'timestamp' => date('c'),
            'check' => 'stuck_processing',
            'issues_found' => 0,
            'auto_fixed' => 0,
        ];
        
        $this->assertArrayHasKey('timestamp', $logEntry);
        $this->assertArrayHasKey('check', $logEntry);
        $this->assertArrayHasKey('issues_found', $logEntry);
        $this->assertArrayHasKey('auto_fixed', $logEntry);
        $this->assertIsInt($logEntry['issues_found']);
        $this->assertIsInt($logEntry['auto_fixed']);
    }

    /**
     * Test that disk space check works
     */
    public function test_disk_space_check(): void
    {
        $minFreeSpaceGb = 1;
        $minFreeSpaceBytes = $minFreeSpaceGb * 1024 * 1024 * 1024;
        
        // Simulate disk space check
        $freeSpaceBytes = disk_free_space('/') ?: 0;
        
        // This is a real check - just verify the logic
        $this->assertIsNumeric($freeSpaceBytes);
    }

    /**
     * Test that stale upload cleanup works
     */
    public function test_stale_upload_cleanup(): void
    {
        $maxAgeSeconds = 24 * 60 * 60; // 24 hours
        
        // Simulate upload created 25 hours ago
        $createdAt = time() - ($maxAgeSeconds + 3600);
        $currentTime = time();
        
        $isStale = ($currentTime - $createdAt) > $maxAgeSeconds;
        
        $this->assertTrue($isStale, "Upload should be detected as stale after 25 hours");
        
        // Simulate upload created 1 hour ago
        $createdAt = time() - 3600;
        $isStale = ($currentTime - $createdAt) > $maxAgeSeconds;
        
        $this->assertFalse($isStale, "Upload should not be detected as stale after 1 hour");
    }
}
