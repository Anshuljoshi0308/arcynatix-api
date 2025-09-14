<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_id')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('service');
            $table->text('message');
            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('request_timestamp')->nullable();
            $table->timestamp('updation_timestamp')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('contact_id');
            $table->index('status');
            $table->index('priority');
            $table->index('sla_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};