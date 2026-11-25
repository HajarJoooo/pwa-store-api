<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present_on_responses(): void
    {
        $response = $this->getJson('/api/v1/pwas');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_health_check_endpoint_is_clean_and_does_not_leak_secrets(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
        $content = $response->getContent();

        // Must not leak sensitive environment or database strings
        $this->assertStringNotContainsString('tidbcloud', $content);
        $this->assertStringNotContainsString('DB_PASSWORD', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);

        // Security headers attached to /up
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }
}
