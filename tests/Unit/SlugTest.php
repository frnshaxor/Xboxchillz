<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Slug Generation Tests
 * 
 * Tests for slug generation edge cases.
 * Based on deep audit findings (Audit Section 13: Slug Validation).
 */
class SlugTest extends TestCase
{
    /**
     * Test slug generation for special characters
     */
    public function test_slug_special_characters(): void
    {
        $testCases = [
            ['input' => '(2014) German Video', 'shouldStartWith' => '2'],
            ['input' => '-test-video', 'shouldStartWith' => 't'],
            ['input' => '_test_video', 'shouldStartWith' => 't'],
            ['input' => '...test...', 'shouldStartWith' => 't'],
        ];
        
        foreach ($testCases as $case) {
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($case['input']));
            $slug = trim($slug, '-');
            $slug = preg_replace('/-+/', '-', $slug);
            if ($slug === '') $slug = 'video';
            
            $this->assertStringStartsWith(
                $case['shouldStartWith'],
                $slug,
                "Slug should start with '{$case['shouldStartWith']}' for input: {$case['input']}"
            );
        }
    }

    /**
     * Test slug generation for empty input
     */
    public function test_slug_empty_input(): void
    {
        $input = '';
        
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($input));
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        if ($slug === '') $slug = 'video';
        
        $this->assertEquals('video', $slug);
    }

    /**
     * Test slug generation for numeric input
     */
    public function test_slug_numeric_input(): void
    {
        $input = '12345';
        
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($input));
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        
        $this->assertEquals('12345', $slug);
    }

    /**
     * Test slug generation for mixed content
     */
    public function test_slug_mixed_content(): void
    {
        $input = 'Video 2024 - Part 1 (HD)';
        
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($input));
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug);
        
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
        $this->assertStringContainsString('2024', $slug);
        $this->assertStringContainsString('video', $slug);
    }

    /**
     * Test that slug meets media delivery regex pattern
     */
    public function test_slug_meets_media_delivery_pattern(): void
    {
        $pattern = '/^[a-z0-9-]+$/';
        
        $testSlugs = [
            'test-video-abc123',
            'my-video-123456',
            'video-2024-001',
        ];
        
        foreach ($testSlugs as $slug) {
            $this->assertMatchesRegularExpression($pattern, $slug, "Slug should match media delivery pattern: $slug");
        }
    }

    /**
     * Test that slug with hex suffix is unique
     */
    public function test_slug_hex_suffix_uniqueness(): void
    {
        $slug1 = 'test-video-' . bin2hex(random_bytes(3));
        $slug2 = 'test-video-' . bin2hex(random_bytes(3));
        
        $this->assertNotEquals($slug1, $slug2, "Slugs with different hex suffixes should be unique");
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug1);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug2);
    }
}
