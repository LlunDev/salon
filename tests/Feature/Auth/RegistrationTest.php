<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_tenant_user_registration_is_not_available(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }
}
