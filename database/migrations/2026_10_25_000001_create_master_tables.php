<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Jam kerja bulanan per lokasi: dasar perhitungan LTIFR, TRIR, dan severity rate.
        Schema::create('man_hours', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedBigInteger('hours')->default(0);
            $table->unsignedInteger('headcount')->nullable();
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'location_id']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->text('to');
            $table->string('subject', 200);
            $table->longText('body');
            $table->string('status', 12)->default('pending')->index();
            $table->string('channel', 12)->nullable();
            $table->string('remote_id', 64)->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('related', 60)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('man_hours');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('locations');
    }
};
