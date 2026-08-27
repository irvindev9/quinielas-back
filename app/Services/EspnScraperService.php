<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspnScraperService
{
    /**
     * Fetch NFL games from ESPN API, ESPN HTML calendar, or Python fallback for a given week and year.
     *
     * @param int $week
     * @param int $year
     * @return array Array of [$team1Model, $team2Model]
     */
    public function getGames($week = 1, $year = 2022)
    {
        $games = [];
        $teams = Team::all();

        // 1. Primary: Direct fetch from ESPN API
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
                    $competitions = $event['competitions'][0]['competitors'] ?? [];
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

        // 2. Secondary: Scraping ESPN Mexico HTML calendar page
        if (count($games) === 0) {
            $games = $this->fetchFromHtmlCalendar($week, $year, $teams);
        }

        // 3. Fallback: Python script execution
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
     * @return array
     */
    private function fetchFromHtmlCalendar($week, $year, $teams)
    {
        $games = [];
        try {
            $pageUrl = "https://www.espn.com.mx/futbol-americano/nfl/calendario/_/semana/{$week}/ano/{$year}/tipodetemporada/2";
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
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
        } catch (\Exception $e) {
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
