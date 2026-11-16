<?php

namespace Tests\Unit;

use App\Support\Html\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_strips_script_tags_and_event_handlers(): void
    {
        $clean = HtmlSanitizer::clean('<p>hi</p><script>alert(1)</script><img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('<p>hi</p>', $clean);
    }

    public function test_it_removes_javascript_and_data_urls(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a><img src="data:text/html;base64,PHNjcmlwdD4=">');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('data:text/html', $clean);
    }

    public function test_it_unwraps_disallowed_containers_without_losing_text(): void
    {
        $clean = HtmlSanitizer::clean('<svg><desc>text</desc></svg><p>keep</p>');

        $this->assertStringNotContainsString('<svg', $clean);
        $this->assertStringContainsString('keep', $clean);
    }

    public function test_it_keeps_safe_formatting_and_forces_rel_on_blank_links(): void
    {
        $clean = HtmlSanitizer::clean('<p style="text-align:center;position:fixed"><b>bold</b></p><a href="https://x.test" target="_blank">t</a>');

        $this->assertStringContainsString('text-align: center', $clean);
        $this->assertStringNotContainsString('position', $clean);
        $this->assertStringContainsString('<b>bold</b>', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer"', $clean);
    }

    public function test_it_removes_comments_that_could_hide_payloads(): void
    {
        $clean = HtmlSanitizer::clean('<!-- <script>alert(1)</script> --><p>ok</p>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('ok', $clean);
    }
}
