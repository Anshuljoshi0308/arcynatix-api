<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/api/contact');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'name',
            'email',
            'service',
            'message',
        ]);
    }
}
