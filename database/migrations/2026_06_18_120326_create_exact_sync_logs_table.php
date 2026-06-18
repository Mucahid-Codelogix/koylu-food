<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exact_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('syncable');
            $table->string('action');
            $table->string('status');
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exact_sync_logs');
    }
};
