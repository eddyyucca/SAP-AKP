<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hazards', function (Blueprint $table) {
            $table->id();
            $table->string('number', 24)->unique();
            $table->dateTime('observed_at')->index();
            $table->foreignId('location_id')->constrained('locations');
            $table->string('area_detail', 160)->nullable();
            $table->string('category', 30)->index();
            $table->string('title', 160);
            $table->text('description');
            $table->unsignedTinyInteger('likelihood');
            $table->unsignedTinyInteger('consequence');
            $table->unsignedTinyInteger('risk_score');
            $table->string('risk_level', 10)->index();
            $table->text('immediate_action')->nullable();
            $table->text('suggestion')->nullable();

            $table->string('reporter_nik', 30)->nullable()->index();
            $table->string('reporter_name', 120);
            $table->string('reporter_department', 120)->nullable();
            $table->string('reporter_position', 120)->nullable();
            $table->string('reporter_email', 160)->nullable();
            $table->string('created_by_id', 30)->nullable()->index();

            $table->string('pic_nik', 30)->nullable();
            $table->string('pic_name', 120)->nullable();
            $table->string('pic_department', 120)->nullable();
            $table->string('pic_email', 160)->nullable();
            $table->date('due_date')->nullable()->index();

            $table->string('status', 20)->default('open')->index();
            $table->dateTime('closed_at')->nullable();
            $table->string('closed_by', 120)->nullable();
            $table->text('closure_note')->nullable();
            $table->text('reject_reason')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        // Tindakan korektif/pencegahan, melekat ke insiden atau hazard.
        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('type', 20)->default('corrective');
            $table->text('description');
            $table->string('pic_nik', 30)->nullable()->index();
            $table->string('pic_name', 120);
            $table->string('pic_department', 120)->nullable();
            $table->string('pic_email', 160)->nullable();
            $table->date('due_date')->index();
            $table->string('status', 20)->default('open')->index();
            $table->dateTime('completed_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('verified_by', 120)->nullable();
            $table->text('verification_note')->nullable();
            $table->date('reminded_on')->nullable();
            $table->string('created_by', 120)->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 20);
            $table->unsignedBigInteger('attachable_id');
            $table->string('path', 255);
            $table->string('original_name', 200);
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->string('uploaded_by', 120)->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('loggable_type', 20);
            $table->unsignedBigInteger('loggable_id');
            $table->string('actor_id', 30)->nullable();
            $table->string('actor_name', 120);
            $table->string('event', 40);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('hazards');
    }
};
