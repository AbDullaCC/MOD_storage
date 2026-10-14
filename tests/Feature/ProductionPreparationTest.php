<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionPreparationTest extends TestCase
{
    public function test_login_has_security_headers_compiled_css_and_staging_can_disable_indexing(): void
    {
        config(['deployment.staging' => true]);
        $this->get('/login')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('/build/assets/app-', false)
            ->assertDontSee('cdn.tailwindcss.com');
        $this->assertStringContainsString('no-store', $this->get('/login')->headers->get('Cache-Control'));
        config(['deployment.staging' => false]);
        $this->get('/login')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_json_encrypted_sessions_can_preserve_flash_and_old_form_values(): void
    {
        config(['session.serialization' => 'json', 'session.encrypt' => true]);
        $store = app('session')->driver();
        $store->put('old_input', ['name' => 'حاسوب', 'quantity' => 14]);
        $store->flash('success', 'تم حفظ الصنف');
        $store->save();
        $store->start();
        $this->assertSame(['name' => 'حاسوب', 'quantity' => 14], $store->get('old_input'));
        $this->assertSame('تم حفظ الصنف', $store->get('success'));
    }
}
