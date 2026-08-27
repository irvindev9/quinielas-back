<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspnScraperService
{
    /**
     * Fetch NFL games from ESPN API or Python fallback for a given week and year.
     *
     * @param int $week
     * @param int $year
     * @return array Array of [$team1Model, $team2Model]
     */
    public function getGames($week = 1, $year = 2022)
    {
        $games = [];
        $teams = Team::all();

        // 1. Direct fetch from ESPN API (Fast & native in PHP)
        try {
            $apiUrl = "https://site.api.espn.com/apis/site/v2/sports/football/nfl/scoreboard?dates={$year}&seasontype=2&week={$week}";
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
            ])->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                $events = $data['events'] ?? [];
                foreach ($events as $event) {
                    $competitors = $event['competitions'][0]['competitors'] ?? [];
                    $team1_name = null;
                    $team2_name = null;
                    foreach ($competitors as $competitor) {
                        if (($competitor['homeAway'] ?? '') === 'away') {
                            $team1_name = $competitor['team']['displayName'] ?? null;
                        } elseif (($competitor['homeAway'] ?? '') === 'home') {
                            $team2_name = $competitor['team']['displayName'] ?? null;
                        }
                    }

                    if ($team1_name && $team2_name) {
                        $t1 = $this->matchTeam($teams, $team1_name);
                        $t2 = $this->matchTeam($teams, $team2_name);

                        if ($t1 && $t2) {
                            $games[] = [$t1, $t2];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error fetching ESPN games via API: ' . $e->getMessage());
        }

        // 2. Fallback: Python script execution if PHP API call returns no games
        if (count($games) === 0) {
            $games = $this->fetchFromPythonScript($week, $year, $teams);
        }

        return $games;
    }

    /**
     * Match a team name from ESPN with a Team model in DB.
     *
     * @param \Illuminate\Database\Eloquent\Collection $teams
     * @param string $teamName
     * @return \App\Models\Team|null
     */
    private function matchTeam($teams, $teamName)
    {
        $teamName = trim($teamName);
        return $teams->filter(function ($item) use ($teamName) {
            return stripos($item->name, $teamName) !== false || stripos($teamName, $item->name) !== false;
        })->first() ?? Team::where('name', 'like', '%' . $teamName . '%')->first();
    }

    /**
     * Fallback to python script execution.
     *
     * @param int $week
     * @param int $year
     * @param \Illuminate\Database\Eloquent\Collection $teams
     * @return array
     */
    private function fetchFromPythonScript($week, $year, $teams)
    {
        $scriptPath = public_path('commands/quiniela-scraper/espn_nfl.py');
        if (!file_exists($scriptPath)) {
            $scriptPath = base_path('commands/quiniela-scraper/espn_nfl.py');
        }

        if (!file_exists($scriptPath)) {
            return [];
        }

        $command = escapeshellcmd(env('PYTHON_VERSION', 'python3') . ' ' . $scriptPath . ' ' . intval($week) . ' ' . intval($year));
        $output = exec($command);

        $games = [];
        $jsonStr = str_replace("'", '"', $output);
        $json = json_decode($jsonStr, true);

        if (is_array($json) && count($json) > 0) {
            foreach ($json as $g) {
                if (!isset($g['team1']) || !isset($g['team2'])) {
                    continue;
                }
                $t1 = $this->matchTeam($teams, $g['team1']);
                $t2 = $this->matchTeam($teams, $g['team2']);

                if ($t1 && $t2) {
                    $games[] = [$t1, $t2];
                }
            }
        } else {
            $cleanOutput = str_replace(['[', ']'], '', $output);
            $outputArray = explode(',', $cleanOutput);
            $i = 0;
            $index = 0;

            foreach ($outputArray as $game) {
                $parts = explode(': ', $game);
                if (count($parts) < 2) {
                    continue;
                }
                $teamName = trim(str_replace(["}", "'", '"'], '', $parts[1]));

                if (($i % 2) == 0) {
                    $games[$index][0] = $this->matchTeam($teams, $teamName);
                } else {
                    $games[$index][1] = Team::where('name', 'like', '%' . $teamName . '%')->first();
                    $index++;
                }
                $i++;
            }
        }

        return $games;
    }
}
