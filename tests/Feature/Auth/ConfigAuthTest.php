<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Tests\TestCase;

class ConfigAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_id_existe_e_features_sem_verificacao(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
        $this->assertTrue(Features::enabled(Features::registration()));
        $this->assertTrue(Features::enabled(Features::resetPasswords()));
        $this->assertFalse(Features::enabled(Features::emailVerification()));
    }
}
