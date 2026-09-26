<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Run;
use App\Models\RunOccurrence;
use App\Models\User;
use App\Services\RsvpService;
use App\Services\OccurrenceService;
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

    public function test_weekly_series_publishes_future_occurrences(): void
    {
        $owner = User::factory()->create();
        $club = $this->club($owner);
        $run = Run::create(['club_id' => $club->id, 'creator_id' => $owner->id, 'title' => 'Weekly 5K', 'description' => 'Every week.', 'terrain' => 'Road', 'route_summary' => 'Park loop.']);
        $start = now('Asia/Jakarta')->addDays(3);
        $occurrence = app(OccurrenceService::class)->create($run, [
            'start_local' => $start->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Jakarta', 'duration_minutes' => 60,
            'distance_km' => 5, 'pace_min_seconds_per_km' => 300, 'pace_max_seconds_per_km' => 420,
            'meeting_area' => 'Park gate', 'capacity' => 20, 'cutoff_hours' => 2,
            'visibility' => 'public', 'repeat_until' => $start->addWeeks(3)->format('Y-m-d'),
        ]);
        $this->assertGreaterThan(1, $run->occurrences()->count());
        $this->actingAs($owner)->post(route('organizer.occurrences.publish', $occurrence))->assertRedirect();
        $this->assertSame(0, $run->occurrences()->where('status', 'draft')->count());
        $originalStarts = $run->occurrences()->orderBy('id')->pluck('start_at_utc')->all();
        $updatedLocal = $occurrence->start_at_utc->timezone('Asia/Jakarta')->addHour();
        $this->actingAs($owner)->patch(route('organizer.occurrences.update', $occurrence), [
            'start_local' => $updatedLocal->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Jakarta',
            'cutoff_hours' => 2, 'scope' => 'future', 'meeting_area' => 'New park gate',
            'capacity' => 20,
        ])->assertRedirect();
        foreach ($run->occurrences()->orderBy('id')->get() as $index => $future) {
            $this->assertSame('New park gate', $future->meeting_area);
            $this->assertSame(\Carbon\Carbon::parse($originalStarts[$index])->addHour()->format('Y-m-d H:i:s'), $future->start_at_utc->format('Y-m-d H:i:s'));
        }
    }

    public function test_deletion_request_is_recorded_once_and_admin_can_review(): void
    {
        $runner = User::factory()->create();
        $this->actingAs($runner)->post(route('account.deletion-request'), ['confirm' => '1'])->assertRedirect();
        $this->actingAs($runner)->post(route('account.deletion-request'), ['confirm' => '1'])->assertRedirect();
        $this->assertDatabaseCount('account_deletion_requests', 1);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Deletion requests');
    }

    public function test_suspended_runner_cannot_log_in_or_reserve(): void
    {
        $owner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner));
        $runner = User::factory()->create(['status' => 'suspended']);
        $this->post(route('login'), ['email' => $runner->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($runner)->post(route('rsvps.store', $occurrence))->assertRedirect(route('login'));
    }
}
