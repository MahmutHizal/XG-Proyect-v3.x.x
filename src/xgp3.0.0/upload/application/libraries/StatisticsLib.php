<?php
/**
 * Statistics Library
 *
 * PHP Version 5.5+
 *
 * @category Library
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */

namespace application\libraries;

use application\core\XGPCore;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * StatisticsLib Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class StatisticsLib extends XGPCore
{

    private static $time;

    /**
     * calculatePoints
     *
     * @param string $element Element
     * @param int    $level   Level
     * @param string $type    Type
     *
     * @return int
     */
    public static function calculatePoints($element, $level, $type = '')
    {
        switch ($type) {
            case 'tech':
                $current_level = $level;

                break;

            case '':
            default:
                $current_level = ( $level - 1 < 0 ) ? 0 : $level - 1;

                break;
        }

        $element            = parent::$objects->getPrice($element);
        $resources_total    = $element['metal'] + $element['crystal'] + $element['deuterium'];
        $level_mult         = pow($element['factor'], $current_level);
        $points             = ($resources_total * $level_mult) / FunctionsLib::readConfig('stat_points');

        return $points;
    }
    
    /**
     * Rebuild the user points for the current planet and specific structure type.
     * 
     * @param int    $user_id   The user ID
     * @param int    $planet_id The planet ID
     * @param string $what      The structure type (buildings|defenses|research|ships)
     * 
     * @return boolean
     */
    public static function rebuildPoints($user_id, $planet_id, $what)
    {
        if (!in_array($what, [BUILDINGS, DEFENSES, RESEARCH, SHIPS])) {
            return false;
        }
        
        $points = 0;
        $query  = '';
        
        if ($what == 'research') {
            $query = Capsule::table(RESEARCH)
                ->where('research_user_id', '=', $user_id);
        } else {
            $query = Capsule::table($what)
                ->where($what . '.' . rtrim($what, 's') . '_planet_id', '=', $planet_id);
        }
        
        
        $objectsToUpdate    = get_object_vars($query->first());
        $objects            = parent::$objects->getObjects();

        if (!is_null($objects)) {
            
            foreach ($objects as $id => $object) {
                
                if (isset($objectsToUpdate[$object])) {
                    
                    $price  = parent::$objects->getPrice($id);
                    $total  = $price['metal'] + $price['crystal'] + $price['deuterium'];
                    $level  = $objectsToUpdate[$object];
                    
                    if ($price['factor'] > 1) {

                        $s  = (pow($price['factor'], $level) - 1) / ($price['factor'] - 1);
                    } else {

                        $s  = $price['factor'] * $level;
                    }

                    $points += ($total * $s) / 1000;
                }
            }
            
            if ($points >= 0) {

                $what   = strtr($what, ['research' => 'technology']);

                Capsule::table(USERS_STATISTICS)
                    ->where('user_statistic_user_id', '=', $user_id)
                    ->update([
                       "user_statistic_" . $what . "_points" => $points
                    ]);
                return true;
            }
        }
        
        return false;
    }

    /**
     * makeStats
     *
     * @return array
     */
    public static function makeStats()
    {
        // INITIAL TIME
        $mtime      = microtime();
        $mtime      = explode(' ', $mtime);
        $mtime      = $mtime[1] + $mtime[0];
        $starttime  = $mtime;
        self::$time = Carbon::now();

        // INITIAL MEMORY
        $result['initial_memory'] = [round(memory_get_usage() / 1024, 1), round(memory_get_usage(1) / 1024, 1)];
        // MAKE STATISTICS FOR USERS
        self::makeUserRank();

        // MAKE STATISTICS FOR ALLIANCE
        self::makeAllyRank();
        // END STATISTICS BUILD
        $mtime      = microtime();
        $mtime      = explode(" ", $mtime);
        $mtime      = $mtime[1] + $mtime[0];
        $endtime    = $mtime;

        $result['stats_time']   = self::$time->timestamp;
        $result['totaltime']    = ($endtime - $starttime);
        $result['memory_peak']  = [round(memory_get_peak_usage() / 1024, 1), round(memory_get_peak_usage(1) / 1024, 1)];
        $result['end_memory']   = [round(memory_get_usage() / 1024, 1), round(memory_get_usage(1) / 1024, 1)];

        return $result;
    }

    /**
     * makeUserRank
     *
     * @return void
     */
    private static function makeUserRank()
    {
        // GET ALL DATA FROM THE USERS TO UPDATE
        $all_stats_data = Capsule::table(USERS_STATISTICS)
            ->select([
                "user_statistic_user_id",
                "user_statistic_technology_rank",
                "user_statistic_technology_points",
                "user_statistic_buildings_rank",
                "user_statistic_buildings_points",
                "user_statistic_defenses_rank",
                "user_statistic_defenses_points",
                "user_statistic_ships_rank",
                "user_statistic_ships_points",
                "user_statistic_total_rank",
                Capsule::connection()
                    ->raw('(user_statistic_buildings_points + user_statistic_defenses_points + user_statistic_ships_points + user_statistic_technology_points) AS total_points')
            ])
            ->orderBy('user_statistic_user_id');

        // BUILD ALL THE ARRAYS
        foreach ($all_stats_data->get() as $CurUser)
        {
            $tech['old_rank'][$CurUser->user_statistic_user_id]   = $CurUser->user_statistic_technology_rank;
            $tech['points'][$CurUser->user_statistic_user_id]     = $CurUser->user_statistic_technology_points;

            $build['old_rank'][$CurUser->user_statistic_user_id]  = $CurUser->user_statistic_buildings_rank;
            $build['points'][$CurUser->user_statistic_user_id]    = $CurUser->user_statistic_buildings_points;

            $defs['old_rank'][$CurUser->user_statistic_user_id]   = $CurUser->user_statistic_defenses_rank;
            $defs['points'][$CurUser->user_statistic_user_id]     = $CurUser->user_statistic_defenses_points;

            $ships['old_rank'][$CurUser->user_statistic_user_id]  = $CurUser->user_statistic_ships_rank;
            $ships['points'][$CurUser->user_statistic_user_id]    = $CurUser->user_statistic_ships_points;

            $total['old_rank'][$CurUser->user_statistic_user_id]  = $CurUser->user_statistic_total_rank;
            $total['points'][$CurUser->user_statistic_user_id]    = $CurUser->total_points;
        }

        // ORDER THEM FROM HIGHEST TO LOWEST
        arsort($tech['points']);
        arsort($build['points']);
        arsort($defs['points']);
        arsort($ships['points']);
        arsort($total['points']);

        // ALL RANKS SHOULD START ON 1
        $rank['tech']   = 1;
        $rank['buil']   = 1;
        $rank['defe']   = 1;
        $rank['ship']   = 1;
        $rank['tota']   = 1;

        // TECH
        foreach ($tech as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $user_id => $data) {

                    $tech['rank'][$user_id] = $rank['tech'] ++;
                }
            }
        }

        // BUILDINGS
        foreach ($build as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $user_id => $data) {

                    $build['rank'][$user_id]    = $rank['buil'] ++;
                }
            }
        }

        // DEFENSES
        foreach ($defs as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $user_id => $data) {

                    $defs['rank'][$user_id] = $rank['defe'] ++;
                }
            }
        }

        // SHIPS
        foreach ($ships as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $user_id => $data) {

                    $ships['rank'][$user_id]    = $rank['ship'] ++;
                }
            }
        }

        // TOTAL POINTS
        // UPDATE QUERY DYNAMIC BLOCK
        foreach ($total as $key => $value) {
            if ($key == 'points') {
                foreach ($value as $user_id => $data) {
                    Capsule::table(USERS_STATISTICS)
                        ->updateOrInsert(["user_statistic_user_id" => $user_id],[
                            "user_statistic_buildings_old_rank" => $build['old_rank'][$user_id],
                            "user_statistic_buildings_rank" => $build['rank'][$user_id],

                            "user_statistic_defenses_old_rank" => $defs['old_rank'][$user_id],
                            "user_statistic_defenses_rank" => $defs['rank'][$user_id],

                            "user_statistic_ships_old_rank" => $ships['old_rank'][$user_id],
                            "user_statistic_ships_rank" => $ships['rank'][$user_id],

                            "user_statistic_technology_old_rank" => $tech['old_rank'][$user_id],
                            "user_statistic_technology_rank" => $tech['rank'][$user_id],

                            "user_statistic_total_points" => $total['points'][$user_id],
                            "user_statistic_total_old_rank" => $total['old_rank'][$user_id],
                            "user_statistic_total_rank" => $rank['tota'] ++,

                            "updated_at" => self::$time
                        ]);
                }
            }
        }

        // MEMORY CLEAN UP
        unset($all_stats_data, $build, $defs, $ships, $tech, $rank, $update_query, $values);
    }

    /**
     * makeAllyRank
     *
     * @return void
     */
    private static function makeAllyRank()
    {
        // GET ALL DATA FROM THE USERS TO UPDATE
        $all_stats_data = Capsule::table(ALLIANCE)
            ->select([
                ALLIANCE . ".alliance_id",
                ALLIANCE_STATISTICS . ".alliance_statistic_technology_rank",
                ALLIANCE_STATISTICS . ".alliance_statistic_buildings_rank",
                ALLIANCE_STATISTICS . ".alliance_statistic_defenses_rank",
                ALLIANCE_STATISTICS . ".alliance_statistic_ships_rank",
                ALLIANCE_STATISTICS . ".alliance_statistic_total_rank",
            ])
            ->selectRaw("SUM(`" . Capsule::connection()->getTablePrefix() . USERS_STATISTICS . "`.`user_statistic_buildings_points`) as `buildings_points`")
            ->selectRaw("SUM(`" . Capsule::connection()->getTablePrefix() . USERS_STATISTICS . "`.`user_statistic_defenses_points`) as `defenses_points`")
            ->selectRaw("SUM(`" . Capsule::connection()->getTablePrefix() . USERS_STATISTICS . "`.`user_statistic_ships_points`) as `ships_points`")
            ->selectRaw("SUM(`" . Capsule::connection()->getTablePrefix() . USERS_STATISTICS . "`.`user_statistic_technology_points`) as `technology_points`")
            ->selectRaw("SUM(`" . Capsule::connection()->getTablePrefix() . USERS_STATISTICS . "`.`user_statistic_total_points`) as `total_points`")
            ->leftJoin(USERS, ALLIANCE . ".alliance_id", '=', USERS . ".user_ally_id")
            ->leftJoin(USERS_STATISTICS, USERS_STATISTICS . ".user_statistic_user_id", '=', USERS . ".user_id")
            ->leftJoin(ALLIANCE_STATISTICS, ALLIANCE_STATISTICS . ".alliance_statistic_alliance_id", '=', ALLIANCE . ".alliance_id")
            ->groupBy(["alliance_id"]);
        if (!$all_stats_data->exists())
            return;
        // BUILD ALL THE ARRAYS
        foreach ($all_stats_data->get() as $CurAlliance)
        {
            $tech['old_rank'][$CurAlliance->alliance_id]   = $CurAlliance->alliance_statistic_technology_rank;
            $tech['points'][$CurAlliance->alliance_id]     = $CurAlliance->technology_points;

            $build['old_rank'][$CurAlliance->alliance_id]  = $CurAlliance->alliance_statistic_buildings_rank;
            $build['points'][$CurAlliance->alliance_id]    = $CurAlliance->buildings_points;

            $defs['old_rank'][$CurAlliance->alliance_id]   = $CurAlliance->alliance_statistic_defenses_rank;
            $defs['points'][$CurAlliance->alliance_id]     = $CurAlliance->ships_points;

            $ships['old_rank'][$CurAlliance->alliance_id]  = $CurAlliance->alliance_statistic_ships_rank;
            $ships['points'][$CurAlliance->alliance_id]    = $CurAlliance->ships_points;

            $total['old_rank'][$CurAlliance->alliance_id]  = $CurAlliance->alliance_statistic_total_rank;
            $total['points'][$CurAlliance->alliance_id]    = $CurAlliance->total_points;
        }
        // ORDER THEM FROM HIGHEST TO LOWEST
        arsort($tech['points']);
        arsort($build['points']);
        arsort($defs['points']);
        arsort($ships['points']);
        arsort($total['points']);

        // ALL RANKS SHOULD START ON 1
        $rank['tech']   = 1;
        $rank['buil']   = 1;
        $rank['defe']   = 1;
        $rank['ship']   = 1;
        $rank['tota']   = 1;

        // TECH
        foreach ($tech as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $alliance_id => $data) {

                    $tech['rank'][$alliance_id] = $rank['tech'] ++;
                }
            }
        }

        // BUILDINGS
        foreach ($build as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $alliance_id => $data) {

                    $build['rank'][$alliance_id]    = $rank['buil'] ++;
                }
            }
        }

        // DEFENSES
        foreach ($defs as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $alliance_id => $data) {

                    $defs['rank'][$alliance_id] = $rank['defe'] ++;
                }
            }
        }

        // SHIPS
        foreach ($ships as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $alliance_id => $data) {

                    $ships['rank'][$alliance_id]    = $rank['ship'] ++;
                }
            }
        }

        // TOTAL POINTS
        // UPDATE QUERY DYNAMIC BLOCK
        foreach ($total as $key => $value) {

            if ($key == 'points') {

                foreach ($value as $alliance_id => $data) {
                    Capsule::table(ALLIANCE_STATISTICS)
                        ->updateOrInsert(["alliance_statistic_alliance_id" => $alliance_id],[
                            "alliance_statistic_buildings_old_rank" => $build['old_rank'][$alliance_id],
                            "alliance_statistic_buildings_rank" => $build['rank'][$alliance_id],
                            "alliance_statistic_buildings_points" => $build['points'][$alliance_id],

                            "alliance_statistic_defenses_old_rank" => $defs['old_rank'][$alliance_id],
                            "alliance_statistic_defenses_rank" => $defs['rank'][$alliance_id],
                            "alliance_statistic_defenses_points" => $defs['points'][$alliance_id],

                            "alliance_statistic_ships_old_rank" => $ships['old_rank'][$alliance_id],
                            "alliance_statistic_ships_rank" => $ships['rank'][$alliance_id],
                            "alliance_statistic_ships_points" => $ships['points'][$alliance_id],

                            "alliance_statistic_technology_old_rank" => $tech['old_rank'][$alliance_id],
                            "alliance_statistic_technology_rank" => $tech['rank'][$alliance_id],
                            "alliance_statistic_technology_points" => $tech['rank'][$alliance_id],

                            "alliance_statistic_total_points" => $total['points'][$alliance_id],
                            "alliance_statistic_total_old_rank" => $total['old_rank'][$alliance_id],
                            "alliance_statistic_total_rank" => $rank['tota'] ++,

                            "updated_at" => self::$time
                        ]);
                }
            }
        }
        // MEMORY CLEAN UP
        unset($all_stats_data, $build, $defs, $ships, $tech, $rank, $update_query, $values);
    }
}

/* end of StatisticsLib.php */
