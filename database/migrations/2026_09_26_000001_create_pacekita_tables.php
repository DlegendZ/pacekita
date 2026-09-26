<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('city')->nullable();
            $table->string('photo_path')->nullable();
            $table->decimal('preferred_distance_km', 6, 2)->nullable();
            $table->unsignedInteger('preferred_pace_seconds_per_km')->nullable();
            $table->text('bio')->nullable();
            $table->text('emergency_contact')->nullable();
            $table->timestamps();
        });

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->index();
            $table->text('description');
            $table->string('visibility')->default('public');
            $table->string('join_policy')->default('open');
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('club_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('member');
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['club_id', 'user_id']);
            $table->index(['club_id', 'status']);
        });

        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->restrictOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('terrain');
            $table->text('route_summary');
            $table->json('recurrence_rule')->nullable();
            $table->timestamps();
        });

        Schema::create('run_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained()->restrictOnDelete();
            $table->timestamp('start_at_utc');
            $table->string('timezone');
            $table->unsignedInteger('duration_minutes');
            $table->decimal('distance_km', 6, 2);
            $table->unsignedInteger('pace_min_seconds_per_km');
            $table->unsignedInteger('pace_max_seconds_per_km');
            $table->string('meeting_area');
            $table->text('safety_notes')->nullable();
            $table->unsignedInteger('capacity');
            $table->timestamp('cutoff_at_utc');
            $table->string('visibility')->default('public');
            $table->string('status')->default('draft');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'start_at_utc']);
            $table->index(['status', 'start_at_utc']);
        });

        Schema::create('rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('run_occurrences')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->timestamp('waitlisted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->unique(['occurrence_id', 'user_id']);
            $table->index(['occurrence_id', 'status', 'waitlisted_at', 'id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->text('reason');
            $table->string('status')->default('open');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reports', 'rsvps', 'run_occurrences', 'runs', 'club_memberships', 'clubs', 'profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
