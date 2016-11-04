<?php
/**
 * Users Library
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
 * UsersLib Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class UsersLib extends XGPCore
{

    private $user_data;
    private $planet_data;
    private $langs;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->langs    = parent::$lang;

        if ($this->isSessionSet()) {

            // Get user data and check it
            $this->setUserData();

            // Check game close
            FunctionsLib::checkServer($this->user_data);

            // Set the changed planet
            $this->setPlanet();

            // Get planet data and check it
            $this->setPlanetData();

            // Update resources, ships, defenses & technologies
            UpdateResourcesLib::updateResources($this->user_data, $this->planet_data, time());

            // Update buildings queue
            UpdateLib::updateBuildingsQueue($this->planet_data, $this->user_data);
        }
    }

    /**
     * userLogin
     *
     * @param int    $user_id   User ID
     * @param string $user_name User name
     * @param string $password  Password
     *
     * @return boolean
     */
    public function userLogin($user_id = 0, $user_name = '', $password = '')
    {
        if ($user_id != 0 && !empty($user_name) && !empty($password)) {

            $_SESSION['user_id']        = $user_id;
            $_SESSION['user_name']      = $user_name;
            $_SESSION['user_password']  = sha1($password . '-' . SECRETWORD);
            return true;
        } else {

            return false;
        }

    }

    /**
     * getUserData
     *
     * @return array
     */
    public function getUserData()
    {
        return $this->user_data;
    }

    /**
     * getPlanetData
     *
     * @return array
     */
    public function getPlanetData()
    {
        return $this->planet_data;
    }

    /**
     * checkSession
     *
     * @return void
     */
    public function checkSession()
    {
        if (!$this->isSessionSet()) {

            FunctionsLib::redirect(XGP_ROOT);
        }
    }

    /**
     * deleteUser
     *
     * @param int $user_id User ID
     *
     * @return void
     */
    public function deleteUser($user_id)
    {
        $user_data = Capsule::table(USERS)
            ->select([
                "user_ally_id"
            ])
            ->where('user_id', '=', $user_id)
            ->first();
        if ($user_data && $user_data->user_ally_id != 0) {
            $theAlliance = Capsule::table(ALLIANCE)
                ->select([
                    ALLIANCE . ".alliance_id",
                    ALLIANCE . ".alliance_ranks",
                    Capsule::connection()->raw("(" .
                        Capsule::table(USERS)
                            ->select([
                                Capsule::connection()->raw("COUNT(user_id) AS `ally_members`")
                            ])->toSql()
                        ."WHERE `user_ally_id` = '" . $user_data['user_ally_id'] . ") AS `ally_members`")
                ])
                ->where(ALLIANCE . ".alliance_id", "=", $user_data['user_ally_id'])
                ->first();
            if ( $theAlliance->ally_members > 1
                && ( isset( $theAlliance->alliance_ranks )
                    && !is_null ( $theAlliance->alliance_ranks ) ) ) {
                $ranks = unserialize($theAlliance->alliance_ranks);
                $userRank = NULL;

                foreach ($ranks as $id => $rank) {
                    if ($rank["rechtehand"] == 1) {
                        $userRank = $id;
                        break;
                    }
                }
                if (is_numeric($userRank)) {

                    Capsule::table(ALLIANCE)
                        ->where(ALLIANCE . '.alliance_id', '=', $theAlliance->alliance_id)
                        ->update([
                            "alliance_owner" => Capsule::connection()->raw("(" .
                                Capsule::table(USERS)
                                    ->select([ "user_id" ])
                                    ->toSql() . "WHERE `user_ally_rank_id` = '" . $userRank . "' AND `user_ally_id` = '" . $theAlliance->alliance_id . "' LIMIT 1" .
                                ")")
                        ]);

                } else {
                    $this->deleteAlliance($theAlliance->alliance_id);
                }
            } else {
                $this->deleteAlliance($theAlliance->alliance_id);
            }
        }

        Capsule::table(PLANETS)
            ->select([
                PLANETS . ".*",
                BUILDINGS . ".*",
                DEFENSES . ".*",
                SHIPS . ".*"
            ])
            ->where(PLANETS . '.planet_user_id', '=', $user_id)
            ->join(BUILDINGS, BUILDINGS . '.building_planet_id', '=', PLANETS . '.planet_id')
            ->join(DEFENSES, DEFENSES . '.defense_planet_id', '=', PLANETS . '.planet_id')
            ->join(SHIPS, SHIPS . '.ship_planet_id', '=', PLANETS . '.planet_id')
            ->delete();

        Capsule::table(MESSAGES)
            ->where('message_sender', '=', $user_id)
            ->orWhere('message_receiver', '=', $user_id)
            ->delete();

        Capsule::table(BUDDY)
            ->where('buddy_sender', '=', $user_id)
            ->orWhere('buddy_receiver', '=', $user_id)
            ->delete();

        Capsule::table(USERS)
            ->select([
                USERS . ".*",
                RESEARCH . ".*",
                FLEETS . ".*",
                NOTES . ".*",
                PREMIUM . ".*",
                SETTINGS . ".*",
                USERS_STATISTICS . ".*"
            ])
            ->where(USERS . ".user_id", '=', $user_id)
            ->join(RESEARCH, RESEARCH . '.research_user_id', '=', USERS . '.user_id')
            ->join(PREMIUM, PREMIUM . '.premium_user_id', '=', USERS . '.user_id')
            ->join(SETTINGS, SETTINGS . 'setting_user_id', '=', USERS . '.user_id')
            ->join(USERS_STATISTICS, USERS_STATISTICS . '.user_statistic_user_id', '=', USERS . '.user_id')
            ->leftJoin(FLEETS, FLEETS . '.fleet_owner', '=', USERS . '.user_id')
            ->leftJoin(NOTES, NOTES . '.note_owner', '=', USERS . '.user_id')
            ->delete();
    }

    /**
     * deleteAlliance
     * 
     * @param Int $alliance_id Alliance ID
     * 
     * @return void
     */
    private function deleteAlliance($alliance_id)
    {
        Capsule::table(ALLIANCE)
            ->join(ALLIANCE_STATISTICS, ALLIANCE_STATISTICS . '.alliance_statistic_alliance_id', '=', ALLIANCE . '.alliance_id')
            ->where('alliance_id', '=', $alliance_id)
            ->delete();

        Capsule::table(USERS)
            ->where('user_ally_id', '=', $alliance_id)
            ->update([
                "user_ally_id" => 0,
                "user_ally_request" => 0,
                "user_ally_request_text" => "",
                "user_ally_register_time" => "",
                "user_ally_rank_id" => 0
            ]);
    }
    
    /**
     * isOnVacations
     *
     * @param array $user User data
     *
     * @return boolean
     */
    public function isOnVacations($user)
    {
        if ($user['setting_vacations_status'] == 1) {

            return true;
        } else {

            return false;
        }
    }

    ###########################################################################
    #
    # Private Methods
    #
    ###########################################################################

    /**
     * isSessionSet
     *
     * @return boolean
     */
    private function isSessionSet()
    {
        if (!isset($_SESSION['user_id']) or !isset($_SESSION['user_name']) or !isset($_SESSION['user_password'])) {

            return false;
        } else {

            return true;
        }
    }

    /**
     * setUserData
     *
     * @return void
     */
    private function setUserData()
    {
        $user_row   = [];

        $this->user_data = Capsule::table(USERS)
            ->select([
                         USERS . ".*",
                         PREMIUM . ".*",
                         RESEARCH . ".*",
                         SETTINGS . ".*",
                         USERS_STATISTICS . ".*",
                         ALLIANCE . ".alliance_name",
                         Capsule::connection()->raw("(" .
                                                    Capsule::table(MESSAGES)
                                                        ->select(Capsule::connection()->raw("COUNT(*) as new_message"))
                                                        ->toSql()
                                                    . " WHERE `message_receiver` = `user_id` AND `message_read` = 1) AS `new_messages`"

                         )
                     ])
            ->join(SETTINGS, 'setting_user_id', '=', 'user_id')
            ->join(USERS_STATISTICS, 'user_statistic_user_id', '=', 'user_id')
            ->join(PREMIUM, 'premium_user_id', '=', 'user_id')
            ->join(RESEARCH, 'research_user_id', '=', 'user_id')
            ->leftJoin(ALLIANCE, 'alliance_id', '=', 'user_ally_id')
            ->where('user_name', '=', $_SESSION['user_name']);



        if ($this->user_data->count() != 1  && !defined('IN_LOGIN'))
            FunctionsLib::message($this->langs['ccs_multiple_users'], XGP_ROOT, 3, false, false);

        $user_row   = get_object_vars($this->user_data->first());

        if ($user_row['user_id'] != $_SESSION['user_id'] && !defined('IN_LOGIN')) {

            FunctionsLib::message($this->langs['ccs_other_user'], XGP_ROOT, 3, false, false);
        }

        if (sha1($user_row['user_password'] . "-" . SECRETWORD) != $_SESSION['user_password'] && !defined('IN_LOGIN')) {

            FunctionsLib::message($this->langs['css_different_password'], XGP_ROOT, 5, false, false);
        }

        if ($user_row['user_banned'] >= Carbon::now()) {

            $parse                  = $this->langs;
            $parse['banned_until']  = date(FunctionsLib::readConfig('date_format_extended'), $user_row['user_banned']);

            die(parent::$page->parseTemplate(parent::$page->getTemplate('home/banned_message'), $parse));
        }

        Capsule::table(USERS)
            ->where('user_id', '=', $_SESSION["user_id"])
            ->update([
                'user_onlinetime' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'user_current_page' => $_SERVER['REQUEST_URI'],
                'user_lastip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
             ]);

        // pass the data
        $this->user_data = $user_row;

        // unset the old data
        unset($user_row);
    }

    /**
     * setPlanetData
     *
     * @return void
     */
    private function setPlanetData()
    {
        $this->planet_data = Capsule::table(PLANETS)
            ->select([
                PLANETS . ".*",
                BUILDINGS . ".*",
                DEFENSES . ".*",
                SHIPS . ".*",
                Capsule::connection()->raw("xgp_m.planet_id AS moon_id"),
                Capsule::connection()->raw("xgp_m.planet_name AS moon_name"),
                Capsule::connection()->raw("xgp_m.planet_image AS moon_image"),
                Capsule::connection()->raw("xgp_m.planet_destroyed AS moon_destroyed"),
                Capsule::connection()->raw("(" .
                    Capsule::table(USERS_STATISTICS)
                        ->select(Capsule::connection()->raw('COUNT(user_statistic_user_id) AS stats_users'))
                        ->join(USERS, 'user_id', '=', 'user_statistic_user_id')->toSql()
                    ." WHERE `user_authlevel` <= " . FunctionsLib::readConfig('stat_admin_level') . ") AS stats_users"
                )
            ])
            ->join(BUILDINGS, 'building_planet_id', '=', 'planet_id')
            ->join(DEFENSES, 'defense_planet_id', '=', 'planet_id')
            ->join(SHIPS, 'ship_planet_id', '=', 'planet_id')
            ->leftJoin("" . Capsule::connection()->raw(PLANETS ." AS m"), function($join) {
                $join->on('m.planet_id', '=', Capsule::connection()->raw("(".
                    Capsule::table(PLANETS)->select('planet_id')->toSql().
                    "WHERE (planet_galaxy=planet_galaxy AND
                                planet_system=planet_system AND
                                planet_planet=planet_planet AND
                                planet_type=3))"
                ));
            })
            ->where(PLANETS.".planet_id", '=', $this->user_data['user_current_planet']);

        $this->planet_data = get_object_vars($this->planet_data->first());
    }

    /**
     * setPlanet
     *
     * @return void
     */
    private function setPlanet()
    {
        $select     = isset($_GET['cp']) ? (int)$_GET['cp'] : '';
        $restore    = isset($_GET['re']) ? (int)$_GET['re'] : '';

        if (isset($select) && is_numeric($select) && isset($restore) && $restore == 0 && $select != 0) {
            $owned = (bool)Capsule::table(PLANETS)
                ->where('planet_id', '=', $select)
                ->where('planet_user_id', '=', $this->user_data['user_id'])
                ->first();

            if ($owned) {

                $this->user_data['user_current_planet'] = $select;
                Capsule::table(USERS)
                    ->where('user_id', '=', $this->user_data['user_id'])
                    ->update([
                        "user_current_planet" => $select
                    ]);
            }
        }
    }
    
    /**
     * createUserWithOptions
     *
     * @param array   $data        The data as an array
     * @param boolean $full_insert Insert all the required tables
     *
     * @return integer
     */
    public function createUserWithOptions($data, $full_insert = true)
    {
        $data["created_at"]  =
        $data["user_register_time"] =
        $data["user_onlinetime"] =
        $data["updated_at"] = Carbon::now();
        $data["user_lastip"] = $_SERVER["REMOTE_ADDR"];
        $theUserID = Capsule::table(USERS)->insertGetId($data);

        if($full_insert) {
            self::createPremium($theUserID);
            self::createResearch($theUserID);
            self::createSettings($theUserID);
            self::createUserStatistics($theUserID);
        }

        return $theUserID;
    }
    
    /**
     * createPremium
     * 
     * @param int $user_id The user id
     * 
     * @return bool
     */
    public function createPremium($user_id)
    {
        return Capsule::table(PREMIUM)->insert(["premium_user_id" => $user_id]);
    }
    
    /**
     * createResearch
     * 
     * @param int $user_id The user id
     * 
     * @return void
     */
    public function createResearch($user_id)
    {
        Capsule::table(RESEARCH)->insert(["research_user_id" => $user_id]);
    }
    
    /**
     * createSettings
     * 
     * @param int $user_id The user id
     * 
     * @return void
     */
    public function createSettings($user_id)
    {
        Capsule::table(SETTINGS)->insert(["setting_user_id" => $user_id]);
    }
    
    /**
     * createUserStatistics
     * 
     * @param int $user_id The user id
     * 
     * @return void
     */
    public function createUserStatistics($user_id)
    {
        Capsule::table(USERS_STATISTICS)->insert(["user_statistic_user_id" => $user_id]);
    }
}

/* end of UsersLib.php */
