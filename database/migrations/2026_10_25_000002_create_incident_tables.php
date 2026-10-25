<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->string('type', 30)->index();
            $table->unsignedTinyInteger('severity')->default(1);
            $table->unsignedTinyInteger('likelihood')->nullable();   // potensi terburuk
            $table->unsignedTinyInteger('consequence')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_level', 10)->nullable();

            $table->dateTime('occurred_at')->index();
            $table->foreignId('location_id')->constrained('locations');
            $table->string('area_detail', 160)->nullable();
            $table->string('activity', 160)->nullable();
            $table->string('equipment', 160)->nullable();
            $table->string('title', 160);
            $table->text('description');
            $table->text('immediate_action')->nullable();

            // Pelapor: salinan identitas saat melapor (sumber HRIS), supaya laporan tetap utuh walau data karyawan berubah.
            $table->string('reporter_nik', 30)->nullable()->index();
            $table->string('reporter_name', 120);
            $table->string('reporter_department', 120)->nullable();
            $table->string('reporter_position', 120)->nullable();
            $table->string('reporter_email', 160)->nullable();
            $table->string('created_by_id', 30)->nullable()->index();   // ID akun Portal

            $table->string('status', 20)->default('submitted')->index();

            // Dampak
            $table->unsignedSmallInteger('injured_count')->default(0);
            $table->unsignedInteger('lost_days')->default(0);
            $table->decimal('cost_estimate', 15, 2)->nullable();
            $table->text('environmental_impact')->nullable();
            $table->boolean('reported_to_authority')->default(false);
            $table->string('authority_note', 200)->nullable();

            // Investigasi
            $table->string('investigator_nik', 30)->nullable();
            $table->string('investigator_name', 120)->nullable();
            $table->string('investigator_email', 160)->nullable();
            $table->date('investigation_due')->nullable();
            $table->dateTime('investigation_started_at')->nullable();
            $table->text('immediate_cause')->nullable();
            $table->string('root_cause_category', 20)->nullable();
            $table->text('root_cause')->nullable();
            $table->json('why_chain')->nullable();
            $table->text('contributing_factors')->nullable();
            $table->text('lessons_learned')->nullable();

            $table->dateTime('closed_at')->nullable();
            $table->string('closed_by', 120)->nullable();
            $table->text('closure_note')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('incident_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->string('role', 12)->default('korban');
            $table->string('nik', 30)->nullable()->index();
            $table->string('name', 120);
            $table->string('company', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('position', 120)->nullable();
            $table->string('injury_type', 80)->nullable();
            $table->string('body_part', 80)->nullable();
            $table->text('treatment')->nullable();
            $table->unsignedInteger('lost_days')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_persons');
        Schema::dropIfExists('incidents');
    }
};
