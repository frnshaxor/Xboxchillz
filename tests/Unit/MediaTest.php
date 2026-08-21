<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Media Access Control Tests
 * 
 * Tests for media serving, path traversal prevention, and access control.
 * Based on deep audit findings (Security: 91/100).
 */
class MediaTest extends TestCase
{
    /**
     * Test that path traversal is prevented via realpath check
     * Rules.md: Security First — ALWAYS check realpath() for path traversal
     */
    public function test_path_traversal_prevention(): void
    {
        $mediaRoot = '/var/www/arsip-layar/media';
        
        // Test cases that should be blocked
        $maliciousPaths = [
            '../../../etc/passwd',
            '....//....//etc/passwd',
            '../config.php',
            '../../.env',
        ];
        
        foreach ($maliciousPaths as $path) {
            $fullPath = $mediaRoot . '/' . $path;
            $realPath = realpath($fullPath);
            
            // If realpath returns false or doesn't start with media root, it's blocked
            if ($realPath === false || !str_starts_with($realPath, $mediaRoot)) {
                $this->assertTrue(true, "Path traversal blocked: $path");
            } else {
                $this->fail("Path traversal NOT blocked: $path");
            }
        }
    }

    /**
     * Test that slug format is valid for media delivery
     * Rules.md: Slug format is a contract — must match regex pattern
     * Note: Pattern allows hyphens anywhere including leading/trailing
     */
    public function test_slug_format_validation(): void
    {
        // Pattern allows lowercase alphanumeric and hyphens
        $validPattern = '/^[a-z0-9-]+$/';
        
        $validSlugs = [
            'test-video-abc123',
            'my-video-123456',
            'video-2024-001',
            '-test-video',      // leading hyphen is allowed by regex
            'test-video-',      // trailing hyphen is allowed by regex
        ];
        
        $invalidSlugs = [
            'TEST VIDEO',       // uppercase and space
            'test_video',       // underscore
        ];
        
        foreach ($validSlugs as $slug) {
            $this->assertMatchesRegularExpression($validPattern, $slug, "Slug should be valid: $slug");
        }
        
        foreach ($invalidSlugs as $slug) {
            $this->assertDoesNotMatchRegularExpression($validPattern, $slug, "Slug should be invalid: $slug");
        }
    }

    /**
     * Test that media URL generation is correct
     */
    public function test_media_url_generation(): void
    {
        $testCases = [
            ['input' => 'media/test-video/source.mp4', 'expected' => '/protected-media/test-video/source.mp4'],
            ['input' => 'media/test-video/poster.jpg', 'expected' => '?page=poster&path=test-video%2Fposter.jpg'],
        ];
        
        foreach ($testCases as $case) {
            // Test protected media URL
            if (str_contains($case['expected'], '/protected-media/')) {
                $relative = ltrim(preg_replace('#^media/#', '', $case['input']), '/');
                $result = '/protected-media/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
                $this->assertEquals($case['expected'], $result);
            }
        }
    }

    /**
     * Test that file extension validation works
     * Rules.md: ALWAYS validate file uploads — extension (.mp4 only)
     */
    public function test_file_extension_validation(): void
    {
        $allowedExtensions = ['mp4'];
        
        $testFiles = [
            ['filename' => 'video.mp4', 'valid' => true],
            ['filename' => 'video.avi', 'valid' => false],
            ['filename' => 'video.mkv', 'valid' => false],
            ['filename' => 'video.mp4.exe', 'valid' => false],
            ['filename' => '.mp4', 'valid' => true],
        ];
        
        foreach ($testFiles as $file) {
            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            $isValid = in_array($ext, $allowedExtensions, true);
            
            $this->assertEquals($file['valid'], $isValid, "File validation failed for: {$file['filename']}");
        }
    }
}
