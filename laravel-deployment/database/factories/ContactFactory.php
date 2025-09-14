<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $priority = $this->faker->randomElement([
            Contact::PRIORITY_LOW,
            Contact::PRIORITY_MEDIUM,
            Contact::PRIORITY_HIGH,
            Contact::PRIORITY_URGENT
        ]);

        $status = $this->faker->randomElement([
            Contact::STATUS_NEW,
            Contact::STATUS_IN_PROGRESS,
            Contact::STATUS_RESOLVED,
            Contact::STATUS_CLOSED
        ]);

        $services = [
            'technical_issue',
            'billing_dispute',
            'account_locked',
            'support',
            'complaint',
            'general_inquiry',
            'partnership',
            'sales_inquiry',
            'feature_request',
            'bug_report'
        ];

        $requestTimestamp = $this->faker->dateTimeBetween('-30 days', 'now');
        $slaHours = Contact::SLA_TIMES[$priority] ?? 24;
        $slaDeadline = Carbon::instance($requestTimestamp)->addHours($slaHours);

        return [
            'contact_id' => $this->generateUniqueContactId(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'service' => $this->faker->randomElement($services),
            'message' => $this->faker->paragraph(3),
            'status' => $status,
            'priority' => $priority,
            'sla_deadline' => $slaDeadline,
            'admin_notes' => $this->faker->optional(0.3)->sentence(),
            'handled_by' => $this->faker->optional(0.6)->randomElement(User::pluck('id')->toArray() ?: [null]),
            'request_timestamp' => $requestTimestamp,
            'updation_timestamp' => $this->faker->dateTimeBetween($requestTimestamp, 'now'),
        ];
    }

    /**
     * Generate a unique contact ID
     */
    private function generateUniqueContactId(): string
    {
        do {
            $contactId = 'CT-' . date('Y') . '-' . strtoupper($this->faker->lexify('??????'));
        } while (Contact::where('contact_id', $contactId)->exists());
        
        return $contactId;
    }

    /**
     * Create a new contact
     */
    public function new(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contact::STATUS_NEW,
            'handled_by' => null,
            'admin_notes' => null,
            'updation_timestamp' => null,
        ]);
    }

    /**
     * Create an in-progress contact
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contact::STATUS_IN_PROGRESS,
            'handled_by' => User::factory(),
            'admin_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * Create a resolved contact
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Contact::STATUS_RESOLVED,
            'handled_by' => User::factory(),
            'admin_notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Create an urgent priority contact
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Contact::PRIORITY_URGENT,
            'service' => $this->faker->randomElement(['technical_issue', 'account_locked', 'billing_dispute']),
            'sla_deadline' => Carbon::instance($attributes['request_timestamp'] ?? now())->addHour(),
        ]);
    }

    /**
     * Create a high priority contact
     */
    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Contact::PRIORITY_HIGH,
            'service' => $this->faker->randomElement(['support', 'complaint']),
            'sla_deadline' => Carbon::instance($attributes['request_timestamp'] ?? now())->addHours(4),
        ]);
    }

    /**
     * Create a medium priority contact
     */
    public function medium(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Contact::PRIORITY_MEDIUM,
            'service' => $this->faker->randomElement(['general_inquiry', 'partnership']),
            'sla_deadline' => Carbon::instance($attributes['request_timestamp'] ?? now())->addHours(24),
        ]);
    }

    /**
     * Create a low priority contact
     */
    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Contact::PRIORITY_LOW,
            'service' => $this->faker->randomElement(['sales_inquiry', 'feature_request']),
            'sla_deadline' => Carbon::instance($attributes['request_timestamp'] ?? now())->addHours(72),
        ]);
    }

    /**
     * Create an overdue contact
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $requestTimestamp = $this->faker->dateTimeBetween('-7 days', '-2 hours');
            $priority = $attributes['priority'] ?? Contact::PRIORITY_MEDIUM;
            $slaHours = Contact::SLA_TIMES[$priority] ?? 24;
            
            return [
                'request_timestamp' => $requestTimestamp,
                'sla_deadline' => Carbon::instance($requestTimestamp)->addHours($slaHours),
                'status' => $this->faker->randomElement([Contact::STATUS_NEW, Contact::STATUS_IN_PROGRESS]),
            ];
        });
    }

    /**
     * Create an unassigned contact
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'handled_by' => null,
            'status' => $this->faker->randomElement([Contact::STATUS_NEW, Contact::STATUS_IN_PROGRESS]),
            'admin_notes' => null,
        ]);
    }

    /**
     * Create contact from today
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'request_timestamp' => $this->faker->dateTimeBetween('today', 'now'),
            'created_at' => $this->faker->dateTimeBetween('today', 'now'),
        ]);
    }
}