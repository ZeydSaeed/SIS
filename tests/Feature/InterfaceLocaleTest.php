<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InterfaceLocaleTest extends TestCase
{
    #[Test]
    public function english_cookie_renders_the_page_left_to_right(): void
    {
        $response = $this->withUnencryptedCookie('locale', 'en')->get('/login');

        $response->assertSuccessful();
        $this->assertStringContainsString('lang="en"', $response->getContent());
        $this->assertStringContainsString('dir="ltr"', $response->getContent());
    }

    #[Test]
    public function arabic_cookie_renders_the_page_right_to_left(): void
    {
        $response = $this->withUnencryptedCookie('locale', 'ar')->get('/login');

        $response->assertSuccessful();
        $this->assertStringContainsString('lang="ar"', $response->getContent());
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }

    #[Test]
    public function unsupported_locale_cookie_keeps_arabic(): void
    {
        $response = $this->withUnencryptedCookie('locale', 'fr')->get('/login');

        $response->assertSuccessful();
        $this->assertStringContainsString('lang="ar"', $response->getContent());
        $this->assertStringContainsString('dir="rtl"', $response->getContent());
    }
}
