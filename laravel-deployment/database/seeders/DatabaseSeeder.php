<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        // Create regular user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Create sample contacts
        $this->createSampleContacts();
    }

    private function createSampleContacts(): void
    {
        // Create contacts with different statuses
        Contact::factory()->newStatus()->count(10)->create();
        Contact::factory()->inProgress()->count(5)->create();
        Contact::factory()->resolved()->count(8)->create();
        Contact::factory()->closed()->count(3)->create();

        // Create recent contacts
        Contact::factory()->recent()->count(5)->create();

        // Create contacts for specific services
        Contact::factory()->service('web_development')->count(8)->create();
        Contact::factory()->service('mobile_development')->count(6)->create();
        Contact::factory()->service('ui_ux_design')->count(4)->create();
        Contact::factory()->service('digital_marketing')->count(3)->create();
        Contact::factory()->service('consulting')->count(2)->create();
        Contact::factory()->service('other')->count(2)->create();

        // Create some contacts without phone numbers
        Contact::factory()->withoutPhone()->count(5)->create();
    }
}