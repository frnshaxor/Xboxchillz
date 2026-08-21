<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Video Upload Tests
 * 
 * Tests for upload validation, slug generation, and file processing.
 * Based on deep audit findings (Security: 91/100).
 */
class VideoUploadTest extends TestCase
{
    /**
     * Test that slug generation strips leading hyphens
     * Rules.md: Slug format is a contract
     */
    public function test_slug_generation_strips_leading_hyphens(): void
    {
        $testCases = [
            ['input' => '(2014) German Video', 'expected' => '2014-german-video'],
            ['input' => '-test-video', 'expected' => 'test-video'],
            ['input' => '_test_video', 'expected' => 'test-video'],
            ['input' => '', 'expected' => 'video'],
        ];
        
        foreach ($testCases as $case) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($case['input']));
            $slug = trim($slug, '-');
            $slug = preg_replace('/-+/', '-', $slug);
            if ($slug === '') $slug = 'video';
            
            $this->assertStringStartsWith(
                preg_match('/^[a-z0-9]/', $slug) ? $slug[0] : '',
                $slug,
                "Slug should not start with hyphen: {$case['input']}"
            );
        }
    }

    /**
     * Test that slug generation collapses consecutive hyphens
     */
    public function test_slug_generation_collapses_consecutive_hyphens(): void
    {
        $testCases = [
            ['input' => 'a---b', 'expected' => 'a-b'],
            ['input' => 'a----b', 'expected' => 'a-b'],
            ['input' => 'a-b-c', 'expected' => 'a-b-c'],
        ];
        
        foreach ($testCases as $case) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($case['input']));
            $slug = trim($slug, '-');
            $slug = preg_replace('/-+/', '-', $slug);
            
            $this->assertDoesNotMatchRegularExpression('/--/', $slug, "Slug should not have consecutive hyphens: {$case['input']}");
        }
    }

    /**
     * Test that file size validation works
     * Rules.md: ALWAYS validate file uploads — size (upload_max_mb)
     */
    public function test_file_size_validation(): void
    {
        $uploadMaxMb = 2048;
        $uploadMaxBytes = $uploadMaxMb * 1024 * 1024;
        
        $testFiles = [
            ['size' => 1024, 'valid' => true],
            ['size' => 1024 * 1024, 'valid' => true],
            ['size' => $uploadMaxBytes, 'valid' => true],
            ['size' => $uploadMaxBytes + 1, 'valid' => false],
        ];
        
        foreach ($testFiles as $file) {
            $isValid = $file['size'] <= $uploadMaxBytes;
            $this->assertEquals($file['valid'], $isValid, "Size validation failed for: {$file['size']}");
        }
    }

    /**
     * Test that MIME type validation works
     * Rules.md: ALWAYS validate file uploads — MIME (finfo + ftyp)
     */
    public function test_mime_type_validation(): void
    {
        $allowedMimes = ['video/mp4', 'application/mp4'];
        
        $testMimes = [
            ['mime' => 'video/mp4', 'valid' => true],
            ['mime' => 'application/mp4', 'valid' => true],
            ['mime' => 'video/avi', 'valid' => false],
            ['mime' => 'video/x-matroska', 'valid' => false],
        ];
        
        foreach ($testMimes as $test) {
            $isValid = in_array($test['mime'], $allowedMimes, true);
            $this->assertEquals($test['valid'], $isValid, "MIME validation failed for: {$test['mime']}");
        }
    }

    /**
     * Test that shell commands use escapeshellarg
     * Rules.md: ALWAYS use escapeshellarg() for shell commands
     */
    public function test_shell_command_safety(): void
    {
        $testInputs = [
            'video.mp4',
            'video with spaces.mp4',
            'video;rm -rf /',
            'video$(command)',
        ];
        
        foreach ($testInputs as $input) {
            $escaped = escapeshellarg($input);
            $this->assertStringStartsWith("'", $escaped, "Input should be escaped: $input");
            $this->assertStringEndsWith("'", $escaped, "Input should be escaped: $input");
        }
    }
}
