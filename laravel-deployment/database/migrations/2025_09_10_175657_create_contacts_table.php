<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            
            // Unique contact identifier
            $table->string('contact_id')->unique();
            
            // Contact information
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('service');
            $table->text('message');
            
            // Status and priority
            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // SLA and timing
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('request_timestamp')->nullable();
            $table->timestamp('updation_timestamp')->nullable();
            
            // Assignment and admin
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->text('admin_notes')->nullable();
            
            // Standard timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('contact_id');
            $table->index('email');
            $table->index('status');
            $table->index('priority');
            $table->index('service');
            $table->index('sla_deadline');
            $table->index('handled_by');
            $table->index('request_timestamp');
            $table->index(['status', 'priority']); // Composite index
            $table->index(['sla_deadline', 'status']); // For overdue queries
            $table->index(['created_at', 'status']); // For time-based filtering
            
            // Foreign key constraint (if users table exists)
            // $table->foreign('handled_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};