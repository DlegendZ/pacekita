<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Run;
use App\Models\RunOccurrence;
use App\Models\User;
use App\Notifications\RunNotice;
use App\Policies\RunOccurrencePolicy;
use App\Services\OccurrenceService;
use App\Services\RsvpService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaceKitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_isolated_sqlite_database(): void
    {
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

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
        $this->assertTrue(app(RunOccurrencePolicy::class)->view($first, $occurrence));
        $this->assertTrue(app(RunOccurrencePolicy::class)->reserve($first, $occurrence));
        $this->assertTrue(Gate::forUser($first)->allows('reserve', $occurrence));
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
            $this->assertSame(Carbon::parse($originalStarts[$index])->addHour()->format('Y-m-d H:i:s'), $future->start_at_utc->format('Y-m-d H:i:s'));
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

    public function test_date_filter_uses_selected_local_timezone(): void
    {
        $club = $this->club(User::factory()->create());
        $local = CarbonImmutable::now('Asia/Jakarta')->addDays(3)->startOfDay()->addHour();
        $this->occurrence($club, ['start_at_utc' => $local->utc()]);
        $this->get(route('runs.index', ['city' => 'jakarta', 'date' => $local->format('Y-m-d'), 'timezone' => 'Asia/Jakarta']))
            ->assertOk()->assertSee('1 found');
        $this->get(route('runs.index', ['date' => $local->subDay()->format('Y-m-d'), 'timezone' => 'Asia/Jakarta']))
            ->assertOk()->assertSee('0 found');
    }

    public function test_organizer_update_notifies_attendee_once_and_remains_visible(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $runner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner));
        app(RsvpService::class)->reserve($runner, $occurrence);
        $message = 'Bring a light jacket because the morning will be cool.';
        $this->actingAs($owner)->post(route('organizer.occurrences.updates.store', $occurrence), ['message' => $message])->assertRedirect();
        $this->assertDatabaseHas('run_updates', ['occurrence_id' => $occurrence->id, 'message' => $message]);
        Notification::assertSentToTimes($runner, RunNotice::class, 2);
        $this->get(route('runs.show', $occurrence))->assertOk()->assertSee($message);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('organizer.occurrences.updates.store', $occurrence), ['message' => $message])->assertForbidden();
    }

    public function test_canceled_run_remains_visible_to_affected_runner(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $runner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner));
        app(RsvpService::class)->reserve($runner, $occurrence);
        $this->actingAs($owner)->post(route('organizer.occurrences.cancel', $occurrence), ['reason' => 'Heavy rain makes the route unsafe.'])->assertRedirect();
        $this->actingAs($runner)->get(route('runs.show', $occurrence))->assertOk()->assertSee('Run canceled')->assertSee('Heavy rain');
        $this->get(route('runs.index'))->assertDontSee('Easy 5K');
    }

    public function test_approval_membership_controls_member_only_runs(): void
    {
        $owner = User::factory()->create();
        $club = $this->club($owner, ['join_policy' => 'approval']);
        $occurrence = $this->occurrence($club, ['visibility' => 'members']);
        $runner = User::factory()->create();

        $this->actingAs($runner)->post(route('clubs.join', $club))->assertRedirect();
        $membership = $club->memberships()->where('user_id', $runner->id)->firstOrFail();
        $this->assertSame('pending', $membership->status);
        $this->actingAs($runner)->post(route('clubs.join', $club))->assertRedirect();
        $this->assertSame(1, $club->memberships()->where('user_id', $runner->id)->count());
        $this->actingAs($runner)->get(route('runs.show', $occurrence))->assertNotFound();

        $otherOwner = User::factory()->create();
        $this->club($otherOwner);
        $this->actingAs($otherOwner)->patch(route('organizer.memberships.update', [$club, $membership]), ['status' => 'active'])->assertForbidden();
        $this->actingAs($owner)->patch(route('organizer.memberships.update', [$club, $membership]), ['status' => 'active'])->assertRedirect();
        $this->actingAs($runner)->get(route('runs.show', $occurrence))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.decide', 'subject_id' => $membership->id]);
    }

    public function test_capacity_cannot_drop_below_confirmed_places(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $occurrence = $this->occurrence($this->club($owner), ['capacity' => 2]);
        app(RsvpService::class)->reserve(User::factory()->create(), $occurrence);
        app(RsvpService::class)->reserve(User::factory()->create(), $occurrence);
        $this->actingAs($owner)->patch(route('organizer.occurrences.update', $occurrence), [
            'start_local' => $occurrence->start_at_utc->timezone($occurrence->timezone)->format('Y-m-d\TH:i'),
            'timezone' => $occurrence->timezone, 'cutoff_hours' => 2, 'scope' => 'occurrence',
            'meeting_area' => 'Park gate', 'capacity' => 1,
        ])->assertSessionHasErrors('capacity');
        $this->assertSame(2, $occurrence->fresh()->capacity);
    }

    public function test_profile_emergency_contact_is_encrypted_at_rest(): void
    {
        $runner = User::factory()->create();
        $this->actingAs($runner)->patch(route('profile.update'), [
            'display_name' => 'Runner One', 'city' => 'Jakarta',
            'emergency_contact' => 'Call Sam at 0800000000',
        ])->assertRedirect();
        $this->assertSame('Call Sam at 0800000000', $runner->profile->emergency_contact);
        $this->assertNotSame('Call Sam at 0800000000', DB::table('profiles')->where('user_id', $runner->id)->value('emergency_contact'));
    }

    public function test_registration_requires_email_verification_before_club_creation(): void
    {
        Notification::fake();
        $this->post(route('register'), [
            'name' => 'New Runner', 'email' => 'runner@example.test',
            'password' => 'securepassword123', 'password_confirmation' => 'securepassword123',
        ])->assertRedirect(route('verification.notice'));

        $runner = User::where('email', 'runner@example.test')->firstOrFail();
        $this->assertNull($runner->email_verified_at);
        $this->assertSame('New Runner', $runner->profile->display_name);
        $this->actingAs($runner)->get(route('clubs.create'))->assertRedirect(route('verification.notice'));

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $runner->id, 'hash' => sha1($runner->email),
        ]);
        $this->get($link)->assertRedirect(route('dashboard'));
        $this->assertTrue($runner->fresh()->hasVerifiedEmail());
        $this->get(route('clubs.create'))->assertOk();
    }
}
