<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghl_email_stat_workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('workflow_id')->unique();
            $table->string('source_id')->nullable();
            $table->string('name');
            $table->string('status')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(999);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghl_email_stat_workflows');
    }
};
