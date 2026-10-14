<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspnScraperService
{
    /**
     * Fetch NFL games from ESPN API, HTML calendar, or Python fallback for a given week and year.
     *
     * @param int $week
     * @param int $year
     * @return array Array of [$team1Model, $team2Model]
     */
    public function getGames($week = 1, $year = 2022)
    {
        $games = [];
        $teams = Team::all();

        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';

        // 1. Primary: ESPN CDN Scoreboard API (Returns exact games for specified week and year)
        try {
            $cdnUrl = "https://cdn.espn.com/core/nfl/scoreboard?xhr=1&render=false&userab=1&year={$year}&week={$week}&seasontype=2";
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => $ua,
                'Accept' => 'application/json, text/plain, */*',
            ])->get($cdnUrl);

            if ($response->successful()) {
                $data = $response->json();
                $events = $data['content']['sbData']['events'] ?? $data['events'] ?? [];
                foreach ($events as $event) {
                    // Verify week if available
                    if (isset($event['week']['number']) && (int)$event['week']['number'] !== (int)$week) {
                        continue;
                    }

                    $competitors = $event['competitions'][0]['competitors'] ?? [];
                    $team1_name = null;
                    $team2_name = null;
                    foreach ($competitors as $competitor) {
                        if (($competitor['homeAway'] ?? '') === 'away') {
                            $team1_name = $competitor['team']['displayName'] ?? $competitor['team']['name'] ?? null;
                        } elseif (($competitor['homeAway'] ?? '') === 'home') {
                            $team2_name = $competitor['team']['displayName'] ?? $competitor['team']['name'] ?? null;
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
        } catch (\Throwable $e) {
            Log::error('Error fetching ESPN CDN API: ' . $e->getMessage());
        }

        // 2. Secondary: ESPN Web Header API (Filtered by week)
        if (count($games) === 0) {
            try {
                $webUrl = "https://site.web.api.espn.com/apis/v2/scoreboard/header?sport=football&league=nfl&region=mx&lang=es&contentorigin=espn&dates={$year}&seasontype=2&week={$week}";
                $response = Http::timeout(10)->withHeaders([
                    'User-Agent' => $ua,
                    'Accept' => 'application/json, text/plain, */*',
                ])->get($webUrl);

                if ($response->successful()) {
                    $data = $response->json();
                    $sports = $data['sports'] ?? [];
                    $leagues = $sports[0]['leagues'] ?? [];
                    $events = $leagues[0]['events'] ?? [];
                    foreach ($events as $event) {
                        if (isset($event['week']) && (int)$event['week'] !== (int)$week) {
                            continue;
                        }

                        $competitors = $event['competitors'] ?? [];
                        $team1_name = null;
                        $team2_name = null;
                        foreach ($competitors as $competitor) {
                            if (($competitor['homeAway'] ?? '') === 'away') {
                                $team1_name = $competitor['displayName'] ?? $competitor['name'] ?? null;
                            } elseif (($competitor['homeAway'] ?? '') === 'home') {
                                $team2_name = $competitor['displayName'] ?? $competitor['name'] ?? null;
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
            } catch (\Throwable $e) {
                Log::error('Error fetching ESPN Web Header API: ' . $e->getMessage());
            }
        }

        // 3. Tertiary: Scraping ESPN Mexico HTML calendar page
        if (count($games) === 0) {
            $games = $this->fetchFromHtmlCalendar($week, $year, $teams, $ua);
        }

        // 4. Quaternary: Python script execution fallback
        if (count($games) === 0) {
            $games = $this->fetchFromPythonScript($week, $year, $teams);
        }

        return $games;
    }

    /**
     * Fetch games from ESPN website calendar HTML as fallback.
     *
     * @param int $week
     * @param int $year
     * @param \Illuminate\Database\Eloquent\Collection $teams
     * @param string $ua
     * @return array
     */
    private function fetchFromHtmlCalendar($week, $year, $teams, $ua)
    {
        $games = [];
        try {
            $pageUrl = "https://www.espn.com.mx/futbol-americano/nfl/calendario/_/semana/{$week}/ano/{$year}/tipodetemporada/2";
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => $ua,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-MX,es;q=0.9,en-US;q=0.8,en;q=0.7',
            ])->get($pageUrl);

            if ($response->successful()) {
                $html = $response->body();

                $dom = new \DOMDocument();
                @$dom->loadHTML($html);
                $xpath = new \DOMXPath($dom);

                $rows = $xpath->query('//tr[contains(@class, "Table__TR")]');
                foreach ($rows as $row) {
                    $awayLink = $xpath->query('.//td[contains(@class, "events__col")]//span[contains(@class, "Table__Team")]//a[contains(@href, "/nombre/")]', $row);
                    $homeLink = $xpath->query('.//td[contains(@class, "colspan__col")]//span[contains(@class, "Table__Team")]//a[contains(@href, "/nombre/")]', $row);

                    if ($awayLink->length > 0 && $homeLink->length > 0) {
                        $awayHref = $awayLink->item(0)->getAttribute('href');
                        $homeHref = $homeLink->item(0)->getAttribute('href');

                        $awayParts = explode('/', trim($awayHref, '/'));
                        $homeParts = explode('/', trim($homeHref, '/'));

                        $awaySlug = end($awayParts);
                        $homeSlug = end($homeParts);

                        $awayName = str_replace('-', ' ', $awaySlug);
                        $homeName = str_replace('-', ' ', $homeSlug);

                        $t1 = $this->matchTeam($teams, $awayName);
                        $t2 = $this->matchTeam($teams, $homeName);

                        if ($t1 && $t2) {
                            $games[] = [$t1, $t2];
                        }
                    }
                }

                // Fallback Regex strategy if DOM parsing found no rows
                if (count($games) === 0) {
                    if (preg_match_all('/<tr[^>]*data-idx="[^"]*"[^>]*>.*?<\/tr>/s', $html, $trMatches)) {
                        foreach ($trMatches[0] as $trHtml) {
                            if (preg_match_all('/href="[^"]*\/nombre\/[^\/]+\/([a-z0-9-]+)"/i', $trHtml, $hrefMatches)) {
                                if (count($hrefMatches[1]) >= 2) {
                                    $awayName = str_replace('-', ' ', $hrefMatches[1][0]);
                                    $homeName = str_replace('-', ' ', $hrefMatches[1][1]);

                                    $t1 = $this->matchTeam($teams, $awayName);
                                    $t2 = $this->matchTeam($teams, $homeName);

                                    if ($t1 && $t2) {
                                        $games[] = [$t1, $t2];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error fetching ESPN HTML calendar: ' . $e->getMessage());
        }

        return $games;
    }

    /**
     * Match a team name/slug from ESPN with a Team model in DB.
     *
     * @param \Illuminate\Database\Eloquent\Collection $teams
     * @param string $teamName
     * @return \App\Models\Team|null
     */
    private function matchTeam($teams, $teamName)
    {
        $teamName = trim($teamName);
        if (empty($teamName)) {
            return null;
        }

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
