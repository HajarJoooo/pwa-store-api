<?php

namespace Tests\Unit\Rules;

use App\Rules\SafePublicUrl;
use Tests\TestCase;

class SafePublicUrlTest extends TestCase
{
    protected function validateUrl(mixed $url): bool
    {
        $rule = new SafePublicUrl;
        $failed = false;

        $rule->validate('url', $url, function () use (&$failed): void {
            $failed = true;
        });

        return ! $failed;
    }

    public function test_valid_public_https_urls_pass(): void
    {
        $this->assertTrue($this->validateUrl('https://example.com'));
        $this->assertTrue($this->validateUrl('https://example.com/manifest.json'));
        $this->assertTrue($this->validateUrl('https://sub.domain.example.com/path?q=1#hash'));
        $this->assertTrue($this->validateUrl('https://example.com:443/manifest.json'));
    }

    public function test_non_https_and_protocols_rejected(): void
    {
        $this->assertFalse($this->validateUrl('http://example.com'));
        $this->assertFalse($this->validateUrl('javascript:alert(1)'));
        $this->assertFalse($this->validateUrl('data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='));
        $this->assertFalse($this->validateUrl('file:///etc/passwd'));
        $this->assertFalse($this->validateUrl('ftp://example.com'));
    }

    public function test_localhost_and_internal_suffixes_rejected(): void
    {
        $this->assertFalse($this->validateUrl('https://localhost/m.json'));
        $this->assertFalse($this->validateUrl('https://app.localhost/m.json'));
        $this->assertFalse($this->validateUrl('https://my-app.local/m.json'));
        $this->assertFalse($this->validateUrl('https://service.internal/m.json'));
        $this->assertFalse($this->validateUrl('https://router.lan/m.json'));
        $this->assertFalse($this->validateUrl('https://device.home.arpa/m.json'));
        $this->assertFalse($this->validateUrl('https://singlename/m.json'));
    }

    public function test_ip_addresses_rejected(): void
    {
        $this->assertFalse($this->validateUrl('https://127.0.0.1/m.json'));
        $this->assertFalse($this->validateUrl('https://192.168.1.1/m.json'));
        $this->assertFalse($this->validateUrl('https://10.0.0.1/m.json'));
        $this->assertFalse($this->validateUrl('https://169.254.169.254/m.json'));
        $this->assertFalse($this->validateUrl('https://0.0.0.0/m.json'));
        $this->assertFalse($this->validateUrl('https://[::1]/m.json'));
        $this->assertFalse($this->validateUrl('https://2130706433/m.json'));
        $this->assertFalse($this->validateUrl('https://0x7f.0.0.1/m.json'));
        $this->assertFalse($this->validateUrl('https://0177.0.0.1/m.json'));
    }

    public function test_userinfo_and_ports_and_malformed_rejected(): void
    {
        $this->assertFalse($this->validateUrl('https://user:pass@example.com/m.json'));
        $this->assertFalse($this->validateUrl('https://example.com:8443/m.json'));
        $this->assertFalse($this->validateUrl('https://example.com:80/m.json'));
        $this->assertFalse($this->validateUrl('https://example.com:8080/m.json'));
        $this->assertFalse($this->validateUrl("https://example.com\n/m.json"));
        $this->assertFalse($this->validateUrl('https://example .com/m.json'));
        $this->assertFalse($this->validateUrl(['array']));
        $this->assertFalse($this->validateUrl(null));
    }
}
