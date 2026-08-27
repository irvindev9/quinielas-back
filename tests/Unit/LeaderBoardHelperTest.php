<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Utils\LeaderBoardHelper;
use App\Models\Season;
use App\Models\Week;
use App\Models\Play;
use App\Models\Team;
use App\Models\Leaderboard;
use ReflectionClass;

class LeaderBoardHelperTest extends TestCase
{
    public function test_add_last_position_handles_missing_user_gracefully()
    {
        $this->artisan('migrate');

        // Create season, week 1, and week 2
        $season = Season::create(['name' => '2026', 'is_active' => 1]);
        $week1 = Week::create(['season_id' => $season->id, 'name' => 'Week 1', 'end_date' => '2026-08-01']);
        $week2 = Week::create(['season_id' => $season->id, 'name' => 'Week 2', 'end_date' => '2026-08-08']);

        $team1 = Team::create(['name' => 'Team 1']);
        $team2 = Team::create(['name' => 'Team 2']);

        // Create a play in week2 so $matches->isEmpty() is false
        Play::create(['week_id' => $week2->id, 'team_id' => $team1->id, 'team_id_2' => $team2->id]);

        // Save last leaderboard for week 1 with only user 100 at position 1
        Leaderboard::create([
            'season_id' => $season->id,
            'week_id' => $week1->id,
            'leaderboard' => json_encode([
                ['user_id' => 100, 'name' => 'Old User', 'points' => 5, 'position' => 1]
            ]),
        ]);

        $reflection = new ReflectionClass(LeaderBoardHelper::class);
        $method = $reflection->getMethod('addLastPosition');
        $method->setAccessible(true);

        // Input leaderboard contains user 100 AND user 999 (user 999 is missing from week 1 leaderboard)
        $inputLeaderboard = [
            [
                'user_id' => 100,
                'name' => 'Old User',
                'points' => 10,
                'position' => 1,
            ],
            [
                'user_id' => 999,
                'name' => 'New User',
                'points' => 8,
                'position' => 2,
            ]
        ];

        $result = $method->invoke(null, $inputLeaderboard);

        $this->assertIsArray($result);
        // User 100 was position 1, now position 1 => diff 0
        $this->assertEquals(0, $result[0]['diff_from_last_week']);
        // User 999 was NOT in week 1 leaderboard => should default to 0 without throwing null property exception!
        $this->assertEquals(999, $result[1]['user_id']);
        $this->assertEquals(0, $result[1]['diff_from_last_week']);
    }
}
