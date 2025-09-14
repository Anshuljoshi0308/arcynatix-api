<?php

namespace Tests\Feature\Api;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class ContactApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user for admin operations
        $this->user = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com'
        ]);
    }

    /** @test */
    public function it_can_create_a_contact_with_auto_generated_fields()
    {
        $contactData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'service' => 'technical_issue',
            'message' => 'I need help with my account'
        ];

        $response = $this->postJson('/api/contacts', $contactData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data',
                    'contact_id',
                    'priority',
                    'sla_deadline'
                ])
                ->assertJson([
                    'success' => true,
                    'priority' => 'Urgent' // technical_issue should auto-assign urgent
                ]);

        // Verify database
        $this->assertDatabaseHas('contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'priority' => Contact::PRIORITY_URGENT,
            'status' => Contact::STATUS_NEW
        ]);

        // Verify auto-generated fields
        $contact = Contact::where('email', 'john@example.com')->first();
        $this->assertNotNull($contact->contact_id);
        $this->assertStringStartsWith('CT-', $contact->contact_id);
        $this->assertNotNull($contact->sla_deadline);
        $this->assertNotNull($contact->request_timestamp);
    }

    /** @test */
    public function it_prevents_duplicate_submissions()
    {
        $contactData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+0987654321',
            'service' => 'support',
            'message' => 'I have a question'
        ];

        // First submission
        $response1 = $this->postJson('/api/contacts', $contactData);
        $response1->assertStatus(201);

        // Duplicate submission within 5 minutes
        $response2 = $this->postJson('/api/contacts', $contactData);
        $response2->assertStatus(429)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Duplicate submission detected. Please wait before submitting again.'
                 ]);
    }

    /** @test */
    public function it_can_list_contacts_with_filters()
    {
        // Create test contacts
        $urgentContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_URGENT,
            'status' => Contact::STATUS_NEW,
            'service' => 'technical_issue'
        ]);

        $mediumContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_MEDIUM,
            'status' => Contact::STATUS_IN_PROGRESS,
            'handled_by' => $this->user->id
        ]);

        // Test basic listing
        $response = $this->getJson('/api/contacts');
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'meta',
                    'filters'
                ]);

        // Test priority filter
        $response = $this->getJson('/api/contacts?priority=urgent');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($urgentContact->contact_id, $data[0]['contact_id']);

        // Test status filter
        $response = $this->getJson('/api/contacts?status=in_progress');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);

        // Test handled_by filter
        $response = $this->getJson("/api/contacts?handled_by={$this->user->id}");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);

        // Test search
        $response = $this->getJson("/api/contacts?search={$urgentContact->contact_id}");
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    /** @test */
    public function it_can_filter_overdue_contacts()
    {
        // Create overdue contact
        $overdueContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_HIGH,
            'status' => Contact::STATUS_IN_PROGRESS,
            'sla_deadline' => Carbon::now()->subHour() // 1 hour overdue
        ]);

        // Create normal contact
        $normalContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_MEDIUM,
            'sla_deadline' => Carbon::now()->addHours(12)
        ]);

        $response = $this->getJson('/api/contacts?overdue_only=true');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($overdueContact->contact_id, $data[0]['contact_id']);
    }

    /** @test */
    public function it_can_show_contact_by_id_or_contact_id()
    {
        $contact = Contact::factory()->create();

        // Test by database ID
        $response = $this->getJson("/api/contacts/{$contact->id}");
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $contact->id,
                        'contact_id' => $contact->contact_id
                    ]
                ]);

        // Test by contact_id
        $response = $this->getJson("/api/contacts/{$contact->contact_id}");
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $contact->id,
                        'contact_id' => $contact->contact_id
                    ]
                ]);
    }

    /** @test */
    public function it_can_update_contact_status_and_priority()
    {
        $contact = Contact::factory()->create([
            'status' => Contact::STATUS_NEW,
            'priority' => Contact::PRIORITY_MEDIUM
        ]);

        $updateData = [
            'status' => Contact::STATUS_IN_PROGRESS,
            'priority' => Contact::PRIORITY_HIGH,
            'handled_by' => $this->user->id,
            'admin_notes' => 'Customer contacted, investigating issue'
        ];

        $response = $this->putJson("/api/contacts/{$contact->id}", $updateData);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Contact updated successfully'
                ]);

        // Verify database updates
        $contact->refresh();
        $this->assertEquals(Contact::STATUS_IN_PROGRESS, $contact->status);
        $this->assertEquals(Contact::PRIORITY_HIGH, $contact->priority);
        $this->assertEquals($this->user->id, $contact->handled_by);
        $this->assertEquals('Customer contacted, investigating issue', $contact->admin_notes);
        $this->assertNotNull($contact->updation_timestamp);
    }

    /** @test */
    public function it_can_assign_contact_to_user()
    {
        $contact = Contact::factory()->create([
            'status' => Contact::STATUS_NEW,
            'handled_by' => null
        ]);

        $response = $this->postJson("/api/contacts/{$contact->id}/assign", [
            'user_id' => $this->user->id
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Contact assigned successfully'
                ]);

        $contact->refresh();
        $this->assertEquals($this->user->id, $contact->handled_by);
        $this->assertEquals(Contact::STATUS_IN_PROGRESS, $contact->status);
    }

    /** @test */
    public function it_can_get_contact_statistics()
    {
        // Create test data
        Contact::factory()->count(5)->create(['status' => Contact::STATUS_NEW]);
        Contact::factory()->count(3)->create(['status' => Contact::STATUS_IN_PROGRESS]);
        Contact::factory()->count(2)->create(['priority' => Contact::PRIORITY_URGENT]);
        Contact::factory()->create([
            'sla_deadline' => Carbon::now()->subHour(),
            'status' => Contact::STATUS_IN_PROGRESS
        ]);

        $response = $this->getJson('/api/contacts/stats');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'by_status',
                        'by_priority',
                        'by_time',
                        'performance',
                        'recent'
                    ]
                ]);

        $stats = $response->json('data');
        $this->assertGreaterThan(0, $stats['by_status']['total']);
        $this->assertGreaterThan(0, $stats['performance']['overdue']);
    }

    /** @test */
    public function it_can_get_overdue_contacts()
    {
        // Create overdue contacts
        Contact::factory()->count(3)->create([
            'sla_deadline' => Carbon::now()->subHours(2),
            'status' => Contact::STATUS_IN_PROGRESS
        ]);

        // Create normal contacts
        Contact::factory()->count(2)->create([
            'sla_deadline' => Carbon::now()->addHours(4)
        ]);

        $response = $this->getJson('/api/contacts/overdue');
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'count' => 3
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_track_contact_by_contact_id()
    {
        $contact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_HIGH,
            'status' => Contact::STATUS_IN_PROGRESS
        ]);

        $response = $this->getJson("/api/contacts/track/{$contact->contact_id}");
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'contact_id',
                        'status',
                        'priority',
                        'submitted_at',
                        'last_updated',
                        'sla_status'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'contact_id' => $contact->contact_id,
                        'status' => 'In Progress',
                        'priority' => 'High'
                    ]
                ]);

        // Verify no sensitive admin data is exposed
        $this->assertArrayNotHasKey('admin_notes', $response->json('data'));
        $this->assertArrayNotHasKey('handled_by', $response->json('data'));
    }

    /** @test */
    public function it_can_delete_contact()
    {
        $contact = Contact::factory()->create();

        $response = $this->deleteJson("/api/contacts/{$contact->id}");
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Contact deleted successfully'
                ]);

        // Verify soft delete
        $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
    }

    /** @test */
    public function it_validates_priority_values()
    {
        $contactData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'service' => 'support',
            'message' => 'Test message',
            'priority' => 'invalid_priority'
        ];

        $response = $this->postJson('/api/contacts', $contactData);
        $response->assertStatus(422);
    }

    /** @test */
    public function it_auto_sets_sla_deadline_based_on_priority()
    {
        $urgentContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_URGENT
        ]);

        $lowContact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_LOW
        ]);

        // Urgent should have 1 hour SLA
        $urgentSLA = $urgentContact->request_timestamp->addHour();
        $this->assertEquals(
            $urgentSLA->format('Y-m-d H:i'),
            $urgentContact->sla_deadline->format('Y-m-d H:i')
        );

        // Low should have 72 hours SLA
        $lowSLA = $lowContact->request_timestamp->addHours(72);
        $this->assertEquals(
            $lowSLA->format('Y-m-d H:i'),
            $lowContact->sla_deadline->format('Y-m-d H:i')
        );
    }

    /** @test */
    public function it_updates_sla_deadline_when_priority_changes()
    {
        $contact = Contact::factory()->create([
            'priority' => Contact::PRIORITY_LOW
        ]);

        $originalSLA = $contact->sla_deadline;

        // Update priority to urgent
        $this->putJson("/api/contacts/{$contact->id}", [
            'priority' => Contact::PRIORITY_URGENT
        ]);

        $contact->refresh();
        
        // SLA should be updated to 1 hour from request time
        $expectedSLA = $contact->request_timestamp->addHour();
        $this->assertEquals(
            $expectedSLA->format('Y-m-d H:i'),
            $contact->sla_deadline->format('Y-m-d H:i')
        );
        $this->assertNotEquals($originalSLA, $contact->sla_deadline);
    }

    /** @test */
    public function it_sorts_contacts_by_priority_correctly()
    {
        Contact::factory()->create(['priority' => Contact::PRIORITY_LOW]);
        Contact::factory()->create(['priority' => Contact::PRIORITY_URGENT]);
        Contact::factory()->create(['priority' => Contact::PRIORITY_MEDIUM]);
        Contact::factory()->create(['priority' => Contact::PRIORITY_HIGH]);

        $response = $this->getJson('/api/contacts?sort_by=priority&sort_order=desc');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be sorted: urgent, high, medium, low
        $this->assertEquals('urgent', $data[0]['priority']);
        $this->assertEquals('high', $data[1]['priority']);
        $this->assertEquals('medium', $data[2]['priority']);
        $this->assertEquals('low', $data[3]['priority']);
    }

    /** @test */
    public function it_handles_contact_not_found()
    {
        $response = $this->getJson('/api/contacts/999');
        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Contact not found'
                ]);

        $response = $this->getJson('/api/contacts/track/INVALID-ID');
        $response->assertStatus(404);
    }
}