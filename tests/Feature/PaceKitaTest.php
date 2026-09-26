<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Run;
use App\Models\RunOccurrence;
use App\Models\User;
use App\Services\RsvpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaceKitaTest extends TestCase
{
    use RefreshDatabase;

    private function club(User $owner, array $attributes = []): Club
    {
        $club = Club::create(array_merge([
            'owner_id' => $owner->id, 'name' => 'Morning Crew', 'slug' => 'morning-crew-'.uniqid(),
            'city' => 'Jakarta', 'description' => 'Easy miles together.', 'visibility' => 'public',
            'join_policy' => 'open', 'status' => 'active',
        ], $attributes));
        $club->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'status' => 'active']);
        return $club;
    }

    private function occurrence(Club $club, array $attributes = []): RunOccurrence
    {
        $run = Run::create(['club_id' => $club->id, 'creator_id' => $club->owner_id, 'title' => 'Easy 5K', 'description' => 'Morning easy run.', 'terrain' => 'Road', 'route_summary' => 'A loop in the park.']);
        return $run->occurrences()->create(array_merge([
            'start_at_utc' => now()->addDays(3), 'timezone' => 'Asia/Jakarta', 'duration_minutes' => 45,
            'distance_km' => 5, 'pace_min_seconds_per_km' => 300, 'pace_max_seconds_per_km' => 420,
            'meeting_area' => 'Park gate', 'capacity' => 1, 'cutoff_at_utc' => now()->addDays(2),
            'visibility' => 'public', 'status' => 'published',
        ], $attributes));
    }

    public function test_discovery_combines_filters_and_hides_drafts(): void
    {
        $club = $this->club(User::factory()->create());
        $visible = $this->occurrence($club);
        $this->occurrence($club, ['status' => 'draft']);
        $this->get('/runs?city=Jakarta&distance=5&pace=360&club='.$club->id)
            ->assertOk()->assertSee('Easy 5K')->assertSee('1 found');
        $this->get('/runs?city=Bandung')->assertOk()->assertSee('No runs match yet.');
        $this->get('/runs/'.$visible->id)->assertOk();
    }

    public function test_verified_runner_gets_confirmed_then_waitlisted_and_promotion(): void
    {
        Notification::fake();
        $occurrence = $this->occurrence($this->club(User::factory()->create()));
        $first = User::factory()->create();
        $second = User::factory()->create();
        $service = app(RsvpService::class);
        $this->assertTrue($first->hasVerifiedEmail());
        $this->assertSame('active', $first->status);
        $this->assertSame('published', $occurrence->status);
        $this->assertTrue(app(\App\Policies\RunOccurrencePolicy::class)->view($first, $occurrence));
        $this->assertTrue(app(\App\Policies\RunOccurrencePolicy::class)->reserve($first, $occurrence));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($first)->allows('reserve', $occurrence));
        $this->assertSame('confirmed', $service->reserve($first, $occurrence)->status);
        $this->assertSame('confirmed', $service->reserve($first, $occurrence)->status);
        $this->assertSame('waitlisted', $service->reserve($second, $occurrence)->status);
        $this->assertSame(1, $occurrence->rsvps()->where('status', 'confirmed')->count());
        $service->withdraw($first, $occurrence);
        $this->assertSame('confirmed', $second->rsvps()->first()->fresh()->status);
    }

    public function test_unverified_and_cross_club_organizer_cannot_modify_run(): void
    {
        $owner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner));
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('organizer.occurrences.cancel', $occurrence), ['reason' => 'Valid reason here'])->assertForbidden();
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('rsvps.store', $occurrence))->assertRedirect(route('verification.notice'));
    }

    public function test_member_only_run_requires_active_membership(): void
    {
        $club = $this->club(User::factory()->create());
        $occurrence = $this->occurrence($club, ['visibility' => 'members']);
        $runner = User::factory()->create();
        $this->actingAs($runner)->get(route('runs.show', $occurrence))->assertNotFound();
        $this->actingAs($runner)->post(route('rsvps.store', $occurrence))->assertForbidden();
        $club->memberships()->create(['user_id' => $runner->id, 'role' => 'member', 'status' => 'active']);
        $this->actingAs($runner)->get(route('runs.show', $occurrence))->assertOk();
    }

    public function test_cancel_closes_registration_and_records_audit(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner));
        $this->actingAs($owner)->post(route('organizer.occurrences.cancel', $occurrence), ['reason' => 'Unsafe weather conditions'])->assertRedirect();
        $this->assertSame('canceled', $occurrence->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['subject_id' => $occurrence->id, 'action' => 'occurrence.cancel']);
    }
}
