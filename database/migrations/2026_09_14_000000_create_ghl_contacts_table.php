<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghl_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('ghl_contact_id')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('business')->nullable();
            $table->string('website')->nullable();
            $table->timestamp('created_at_ghl')->nullable();
            $table->date('created_date')->nullable()->index();
            $table->json('tags');
            $table->json('custom_fields')->nullable();
            $table->text('audit_report_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghl_contacts');
    }
};
