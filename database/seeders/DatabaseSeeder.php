<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Run;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) return;

        $host = User::firstOrCreate(['email' => 'demo@pacekita.test'], [
            'name' => 'PaceKita Demo', 'password' => 'password12345', 'email_verified_at' => now(),
        ]);
        $host->profile()->firstOrCreate([], ['display_name' => 'PaceKita Demo', 'city' => 'Jakarta']);

        $clubs = [
            ['slug' => 'sunrise-striders', 'name' => 'Sunrise Striders', 'city' => 'Jakarta', 'description' => 'Early starts, easy miles, and coffee after. Everyone is welcome at our weekly city runs.'],
            ['slug' => 'bandung-trail-collective', 'name' => 'Bandung Trail Collective', 'city' => 'Bandung', 'description' => 'Fresh air, winding paths, and good company on the trails around Bandung.'],
            ['slug' => 'coastline-running', 'name' => 'Coastline Running', 'city' => 'Denpasar', 'description' => 'A laid-back coastal running crew. Come for the miles, stay for the sunrise.'],
        ];

        foreach ($clubs as $index => $details) {
            $club = Club::firstOrCreate(['slug' => $details['slug']], $details + ['owner_id' => $host->id, 'visibility' => 'public', 'join_policy' => 'open', 'status' => 'active']);
            $club->memberships()->firstOrCreate(['user_id' => $host->id], ['role' => 'owner', 'status' => 'active', 'requested_at' => now(), 'decided_at' => now()]);
            foreach ([1, 2] as $week) {
                $title = ['Easy Morning Miles', 'Trail Time Together', 'Coastal Sunrise Run'][$index];
                $run = Run::firstOrCreate(['club_id' => $club->id, 'title' => $title], [
                    'creator_id' => $host->id, 'description' => ['Start the day with a relaxed loop and friendly faces.', 'Explore the green side of Bandung at a comfortable pace.', 'Follow the shore as the city wakes up.'][$index],
                    'terrain' => $index === 1 ? 'Trail' : 'Road', 'route_summary' => ['A flat loop through the city park, with a water stop halfway.', 'Rolling trails with short climbs and shaded paths.', 'An out-and-back along the beachfront promenade.'][$index],
                ]);
                $local = CarbonImmutable::now('Asia/Jakarta')->addWeeks($week)->next(CarbonImmutable::SATURDAY)->setTime(6, 30);
                $start = $local->utc();
                $run->occurrences()->firstOrCreate(['start_at_utc' => $start], [
                    'timezone' => 'Asia/Jakarta', 'duration_minutes' => 60, 'distance_km' => [5, 8, 6][$index],
                    'pace_min_seconds_per_km' => [330, 390, 360][$index], 'pace_max_seconds_per_km' => [420, 540, 450][$index],
                    'meeting_area' => ['Main park gate', 'Trailhead parking area', 'Beach promenade entrance'][$index],
                    'safety_notes' => 'Bring water, wear suitable shoes, and follow your own pace.', 'capacity' => [24, 16, 30][$index],
                    'cutoff_at_utc' => $start->subHours(2), 'visibility' => 'public', 'status' => 'published',
                ]);
            }
        }
    }
}
