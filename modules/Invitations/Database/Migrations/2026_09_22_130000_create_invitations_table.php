<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('label');                         // pour qui / pourquoi
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_uses')->nullable(); // nombre max d'ouvertures du lien (null = illimité)
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('lunar_staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
