<?php
/**
 * Planet Library
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
use Illuminate\Database\Capsule\Manager as Capsule;
use Carbon\Carbon;

/**
 * PlanetLib Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class PlanetLib extends XGPCore
{
    /**
     *
     * @var FormulaLib
     */
    private $formula;
    
    /**
     *
     * @var array
     */
    private $langs;
    
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->langs    = parent::$lang;
        $this->formula  = FunctionsLib::loadLibrary('FormulaLib');
    }
    
    /**
     * createPlanetWithOptions
     *
     * @param array   $data        The data as an array
     * @param boolean $full_insert Insert all the required tables
     *
     * @return int
     */
    public static function createPlanetWithOptions($data, $full_insert = true)
    {
        $data["created_at"] = $data["updated_at"] = $data["planet_last_update"] = Carbon::now();
        $thePlanetID = Capsule::table(PLANETS)->insertGetId($data);
        if ($full_insert) {
            self::createBuildings($thePlanetID);
            self::createDefenses($thePlanetID);
            self::createShips($thePlanetID);
        }
        return $thePlanetID;
    }
    
    /**
     * setNewPlanet
     *
     * @param int     $galaxy   Galaxy
     * @param int     $system   System
     * @param int     $position Position
     * @param int     $owner    Planet owner Id
     * @param string  $name     Planet name
     * @param boolean $main     Main planet
     *
     * @return boolean
     */
    public function setNewPlanet($galaxy, $system, $position, $owner, $name = '', $main = false)
    {
        $planet_exist = Capsule::table(PLANETS)
            ->select('planet_id')
            ->where([
                ['planet_galaxy', '=', $galaxy],
                ['planet_system', '=', $system],
                ['planet_planet', '=', $position]
            ]);
        if (!$planet_exist->exists()) {

            $planet = $this->formula->getPlanetSize($position, $main);
            $temp   = $this->formula->setPlanetTemp($position);
            $name   = ($name == '') ? $this->langs['ge_colony'] : $name;

            if ($main == true) {
                $name   = $this->langs['ge_home_planet'];
            }

            return $this->createPlanetWithOptions(
                [
                    'planet_name' => $name,
                    'planet_user_id' => $owner,
                    'planet_galaxy' => $galaxy,
                    'planet_system' => $system,
                    'planet_planet' => $position,
                    'planet_type' => '1',
                    'planet_last_update' => Carbon::now(),
                    'planet_image' => $this->formula->setPlanetImage($system, $position),
                    'planet_diameter' => $planet['planet_diameter'],
                    'planet_field_max' => $planet['planet_field_max'],
                    'planet_temp_min' => $temp['min'],
                    'planet_temp_max' => $temp['max'],
                    'planet_metal' => BUILD_METAL,
                    'planet_metal_perhour' => FunctionsLib::readConfig('metal_basic_income'),
                    'planet_crystal' => BUILD_CRISTAL,
                    'planet_crystal_perhour' => FunctionsLib::readConfig('crystal_basic_income'),
                    'planet_deuterium' => BUILD_DEUTERIUM,
                    'planet_deuterium_perhour' => FunctionsLib::readConfig('deuterium_basic_income')
                ]
            );
        }

        return false;
    }
    
    
    /**
     * setNewMoon
     *
     * @param int    $galaxy     Galaxy
     * @param int    $system     System
     * @param int    $position   Position
     * @param int    $owner      Owner
     * @param string $name       Moon name
     * @param int    $chance     Chance
     * @param int    $size       Size
     * @param int    $max_fields Max Fields
     * @param int    $min_temp   Min Temp
     * @param int    $max_temp   Max Temp
     *
     * @return string
     */
    public function setNewMoon($galaxy, $system, $position, $owner, $name = '', $chance = 0, $size = 0, $max_fields = 1, $min_temp = 0, $max_temp = 0)
    {
        $MoonPlanet = parent::$db->queryFetch(
            "SELECT pm2.`planet_id`,
            pm2.`planet_name`,
            pm2.`planet_temp_max`,
            pm2.`planet_temp_min`,
            (SELECT pm.`planet_id` AS `id_moon`
                    FROM " . PLANETS . " AS pm
                    WHERE pm.`planet_galaxy` = '". $galaxy ."' AND
                                    pm.`planet_system` = '". $system ."' AND
                                    pm.`planet_planet` = '". $position ."' AND
                                    pm.`planet_type` = 3) AS `id_moon`
            FROM " . PLANETS . " AS pm2
            WHERE pm2.`planet_galaxy` = '". $galaxy ."' AND
                    pm2.`planet_system` = '". $system ."' AND
                    pm2.`planet_planet` = '". $position ."';"
        );

        if ($MoonPlanet['id_moon'] == '' && $MoonPlanet['planet_id'] != 0) {

            $SizeMin    = 2000 + ($chance * 100);
            $SizeMax    = 6000 + ($chance * 200);
            $temp       = $this->formula->setPlanetTemp($position);
            $size       = $chance == 0 ? $size : mt_rand($SizeMin, $SizeMax);
            $size       = $size == 0 ? mt_rand(2000, 6000) : $size;
            $max_fields = $max_fields == 0 ? 1 : $max_fields;
            
            $this->createPlanetWithOptions(
                [
                    'planet_name' => $name == '' ? $this->langs['fcm_moon'] : $name,
                    'planet_user_id' => $owner,
                    'planet_galaxy' => $galaxy,
                    'planet_system' => $system,
                    'planet_planet' => $position,
                    'planet_last_update' => Carbon::now(),
                    'planet_type' => '3',
                    'planet_image' => 'mond',
                    'planet_diameter' => $size,
                    'planet_field_max' => $max_fields,
                    'planet_temp_min' => $min_temp == 0 ? $temp['min'] : $min_temp,
                    'planet_temp_max' => $max_temp == 0 ? $temp['max'] : $max_temp
                ]
            );
        
            return true;
        }
        
        return false;
    }
    
    /**
     * createBuildings
     *
     * @param int $planet_id The planet id
     *
     * @return void
     */
    public static function createBuildings($planet_id)
    {
        Capsule::table(BUILDINGS)->insert(["building_planet_id" => $planet_id]);
    }
    
    /**
     * createDefenses
     *
     * @param int $planet_id The planet id
     *
     * @return void
     */
    public static function createDefenses($planet_id)
    {
        Capsule::table(DEFENSES)->insert(["defense_planet_id" => $planet_id]);
    }
    
    /**
     * createShips
     *
     * @param int $planet_id The planet id
     *
     * @return void
     */
    public static function createShips($planet_id)
    {
        Capsule::table(SHIPS)->insert(["ship_planet_id" => $planet_id]);
    }
    
    /**
     * deletePlanetById
     *
     * @param int $planet_id The planed ID
     *
     * @return void
     */
    public static function deletePlanetById($planet_id)
    {
    }
    
    /**
     * deletePlanetByCoords
     *
     * @param int $galaxy The galaxy
     * @param int $system The system
     * @param int $planet The planet
     * @param int $type   The planet type (planet|moon)
     *
     * @return void
     */
    public static function deletePlanetByCoords($galaxy, $system, $planet, $type)
    {
    }
}

/* end of PlanetLib.php */
