<?php
/**
 * Update Resources Library
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
use Illuminate\Database\Capsule\Manager;

/**
 * UpdateResourcesLib Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class UpdateResourcesLib extends XGPCore
{
    /**
     * updateResources
     *
     * @param array   $current_user   Current user
     * @param array   $current_planet Current planet
     * @param int     $UpdateTime     Update time
     * @param boolean $Simul          Simulation
     *
     * @return void
     */
    public static function updateResources(&$current_user, &$current_planet, $UpdateTime, $Simul = false)
    {
        $resource   = parent::$objects->getObjects();
        $ProdGrid   = parent::$objects->getProduction();
        $reslist    = parent::$objects->getObjectsList();

        $game_resource_multiplier       = FunctionsLib::readConfig('resource_multiplier');
        $game_metal_basic_income        = FunctionsLib::readConfig('metal_basic_income');
        $game_crystal_basic_income      = FunctionsLib::readConfig('crystal_basic_income');
        $game_deuterium_basic_income    = FunctionsLib::readConfig('deuterium_basic_income');

        $current_planet['planet_metal_max']     = ProductionLib::maxStorable($current_planet[$resource[22]]);
        $current_planet['planet_crystal_max']   = ProductionLib::maxStorable($current_planet[$resource[23]]);
        $current_planet['planet_deuterium_max'] = ProductionLib::maxStorable($current_planet[$resource[24]]);

        $MaxMetalStorage        = $current_planet['planet_metal_max'];
        $MaxCristalStorage      = $current_planet['planet_crystal_max'];
        $MaxDeuteriumStorage    = $current_planet['planet_deuterium_max'];

        $Caps                               = array();
        $BuildTemp                          = $current_planet['planet_temp_max'];
        $sub_query                          = [];
        $tech_query                         = [];
        $parse['production_level']          = 100;

        $post_percent                       = ProductionLib::maxProduction(
            $current_planet['planet_energy_max'],
            $current_planet['planet_energy_used']
        );

        $Caps['planet_metal_perhour']       = 0;
        $Caps['planet_crystal_perhour']     = 0;
        $Caps['planet_deuterium_perhour']   = 0;
        $Caps['planet_energy_max']          = 0;
        $Caps['planet_energy_used']         = 0;

        foreach ($ProdGrid as $ProdID => $formula) {

            $BuildLevelFactor   = $current_planet['planet_' . $resource[$ProdID] . '_percent'];
            $BuildLevel         = $current_planet[$resource[$ProdID]];
            $BuildEnergy        = $current_user['research_energy_technology'];

            // BOOST
            $geologe_boost      = 1 + ( 1 * ( OfficiersLib::isOfficierActive(
                $current_user['premium_officier_geologist']
            ) ? GEOLOGUE : 0));
            $engineer_boost     = 1 + ( 1 * ( OfficiersLib::isOfficierActive(
                $current_user['premium_officier_engineer']
            ) ? ENGINEER_ENERGY : 0));

            // PRODUCTION FORMULAS
            $metal_prod         = eval($ProdGrid[$ProdID]['formule']['metal']);
            $crystal_prod       = eval($ProdGrid[$ProdID]['formule']['crystal']);
            $deuterium_prod     = eval($ProdGrid[$ProdID]['formule']['deuterium']);
            $energy_prod        = eval($ProdGrid[$ProdID]['formule']['energy']);

            // PRODUCTION
            $Caps['planet_metal_perhour']       += ProductionLib::currentProduction(
                ProductionLib::productionAmount($metal_prod, $geologe_boost, $game_resource_multiplier),
                $post_percent
            );

            $Caps['planet_crystal_perhour']     += ProductionLib::currentProduction(
                ProductionLib::productionAmount($crystal_prod, $geologe_boost, $game_resource_multiplier),
                $post_percent
            );

            $Caps['planet_deuterium_perhour']   += ProductionLib::currentProduction(
                ProductionLib::productionAmount($deuterium_prod, $geologe_boost, $game_resource_multiplier),
                $post_percent
            );

            if ($ProdID >= 4) {
                
                if ($ProdID == 12 && $current_planet['planet_deuterium'] == 0) {
                    continue;
                }

                $Caps['planet_energy_max']  += ProductionLib::productionAmount(
                    $energy_prod,
                    $engineer_boost,
                    0,
                    true
                );
            } else {

                $Caps['planet_energy_used'] += ProductionLib::productionAmount(
                    $energy_prod,
                    1,
                    0,
                    true
                );
            }
        }

        if ($current_planet['planet_type'] == 3) {

            $game_metal_basic_income                    = 0;
            $game_crystal_basic_income                  = 0;
            $game_deuterium_basic_income                = 0;
            $current_planet['planet_metal_perhour']     = 0;
            $current_planet['planet_crystal_perhour']   = 0;
            $current_planet['planet_deuterium_perhour'] = 0;
            $current_planet['planet_energy_used']       = 0;
            $current_planet['planet_energy_max']        = 0;
        } else {

            $current_planet['planet_metal_perhour']     = $Caps['planet_metal_perhour'];
            $current_planet['planet_crystal_perhour']   = $Caps['planet_crystal_perhour'];
            $current_planet['planet_deuterium_perhour'] = $Caps['planet_deuterium_perhour'];
            $current_planet['planet_energy_used']       = $Caps['planet_energy_used'];
            $current_planet['planet_energy_max']        = $Caps['planet_energy_max'];
        }
        if(is_int($UpdateTime))
            $UpdateTime = Carbon::createFromTimestamp($UpdateTime);

        if(is_null($current_planet['planet_last_update']))
            $current_planet['planet_last_update'] = Carbon::now()->subHour(1);
        else
            $current_planet["planet_last_update"] = Carbon::parse($current_planet["planet_last_update"]);

        $ProductionTime                         = ($UpdateTime->timestamp- $current_planet['planet_last_update']->timestamp);

        $current_planet['planet_last_update']   = $UpdateTime;

        if ($current_planet['planet_energy_max'] == 0) {

            $current_planet['planet_metal_perhour']     = $game_metal_basic_income;
            $current_planet['planet_crystal_perhour']   = $game_crystal_basic_income;
            $current_planet['planet_deuterium_perhour'] = $game_deuterium_basic_income;

            $production_level   = 100;
        } elseif ($current_planet['planet_energy_max'] >= $current_planet['planet_energy_used']) {

            $production_level = 100;
        } else {

            $production_level = floor(
                ($current_planet['planet_energy_max'] / $current_planet['planet_energy_used']) * 100
            );
        }
        
        if ($production_level > 100) {

            $production_level = 100;
        } elseif ($production_level < 0) {

            $production_level = 0;
        }

        if ($current_planet['planet_metal'] <= $MaxMetalStorage) {

            $MetalProduction    = (
                ($ProductionTime * ($current_planet['planet_metal_perhour'] / 3600))
            ) * (0.01 * $production_level);

            $MetalBaseProduc    = (($ProductionTime * ($game_metal_basic_income / 3600 )));
            $MetalTheorical     = $current_planet['planet_metal'] + $MetalProduction  +  $MetalBaseProduc;
            
            if ($MetalTheorical <= $MaxMetalStorage) {

                $current_planet['planet_metal']  = $MetalTheorical;
            } else {

                $current_planet['planet_metal']  = $MaxMetalStorage;
            }
        }

        if ($current_planet['planet_crystal'] <= $MaxCristalStorage) {

            $CristalProduction  = (
                ($ProductionTime * ($current_planet['planet_crystal_perhour'] / 3600))
            ) * (0.01 * $production_level);

            $CristalBaseProduc  = (($ProductionTime * ($game_crystal_basic_income / 3600 )));
            $CristalTheorical   = $current_planet['planet_crystal'] + $CristalProduction  +  $CristalBaseProduc;

            if ($CristalTheorical <= $MaxCristalStorage) {

                $current_planet['planet_crystal']   = $CristalTheorical;
            } else {

                $current_planet['planet_crystal']   = $MaxCristalStorage;
            }
        }

        if ($current_planet['planet_deuterium'] <= $MaxDeuteriumStorage) {

            $DeuteriumProduction    = (
                ($ProductionTime * ($current_planet['planet_deuterium_perhour'] / 3600))
            ) * (0.01 * $production_level);

            $DeuteriumBaseProduc    = (($ProductionTime * ($game_deuterium_basic_income / 3600 )));
            $DeuteriumTheorical     = $current_planet['planet_deuterium'] +
                $DeuteriumProduction  +  $DeuteriumBaseProduc;
            
            if ($DeuteriumTheorical <= $MaxDeuteriumStorage) {

                $current_planet['planet_deuterium']  = $DeuteriumTheorical;
            } else {

                $current_planet['planet_deuterium']  = $MaxDeuteriumStorage;
            }
        }

        if ($current_planet['planet_metal'] < 0) {

            $current_planet['planet_metal']  = 0;
        }

        if ($current_planet['planet_crystal'] < 0) {

            $current_planet['planet_crystal']  = 0;
        }

        if ($current_planet['planet_deuterium'] < 0) {

            $current_planet['planet_deuterium']  = 0;
        }

        if ($Simul == false) {

            // SHIPS AND DEFENSES UPDATE
            $builded        = self::buildingQueue($current_user, $current_planet, $ProductionTime);
            $ship_points    = 0;
            $defense_points = 0;

            if ($builded != '') {

                foreach ($builded as $element => $count) {

                    if ($element <> '') {

                        // POINTS
                        switch ($element) {

                            case (($element >= 202) && ($element <= 215)):
                                $ship_points    += StatisticsLib::calculatePoints($element, $count) * $count;
                                break;

                            case (($element >= 401) && ($element <= 503)):
                                $defense_points += StatisticsLib::calculatePoints($element, $count) * $count;
                                break;

                            default:
                                break;
                        }
                        $sub_query[$resource[$element]] = $current_planet[$resource[$element]];
                    }
                }
            }

            // RESEARCH UPDATE
            if(!is_null($current_planet['planet_b_tech_id']))
            {
                if(Carbon::parse($current_planet['planet_b_tech_id']) <= Carbon::now())
                {
                    $current_user['research_points']    = StatisticsLib::calculatePoints(
                        $current_planet['planet_b_tech_id'],
                        $current_user[$resource[$current_planet['planet_b_tech_id']]],
                        'tech'
                    );
                    $current_user[$resource[$current_planet['planet_b_tech_id']]]++;
                    $tech_query[PLANETS . ".planet_b_tech"] = NULL;
                    $tech_query[PLANETS . ".planet_b_tech_id"] = NULL;
                    $tech_query[RESEARCH . "." . $resource[$current_planet['planet_b_tech_id']]] = $current_user[$resource[$current_planet['planet_b_tech_id']]];
                    $tech_query[RESEARCH . ".research_current_research"] = "0";
                }
            }

            $theQuery = Manager::table(PLANETS)
                ->where(PLANETS . '.planet_id', '=', $current_planet['planet_id'])
                ->join(USERS_STATISTICS, USERS_STATISTICS . '.user_statistic_user_id', '=', PLANETS . '.planet_id')
                ->join(DEFENSES, DEFENSES . '.defense_planet_id', '=', PLANETS . '.planet_id')
                ->join(SHIPS, SHIPS . '.ship_planet_id', '=', PLANETS . '.planet_id')
                ->join(RESEARCH, RESEARCH . '.research_user_id', '=', PLANETS . '.planet_user_id');

            $theQuery->update(array_merge([
                "planet_metal" => $current_planet['planet_metal'],
                "planet_crystal" => $current_planet['planet_crystal'],
                "planet_deuterium" => $current_planet['planet_deuterium'],
                "planet_energy_used" => $current_planet['planet_energy_used'],
                "planet_energy_max" => $current_planet['planet_energy_max'],

                "planet_b_hangar_id" =>$current_planet['planet_b_hangar_id'],

                "planet_metal_perhour" => $current_planet['planet_metal_perhour'],
                "planet_crystal_perhour" => $current_planet['planet_crystal_perhour'],
                "planet_deuterium_perhour" => $current_planet['planet_deuterium_perhour'],

                "planet_b_hangar" => $current_planet['planet_b_hangar'],
                "planet_last_update" => $current_planet['planet_last_update']
            ], $sub_query, $tech_query));

            $theQuery->increment("user_statistic_ships_points", $ship_points);
            $theQuery->increment("user_statistic_defenses_points", $defense_points);
        }
    }

    /**
     * buildingQueue
     *
     * @param array $current_user   Current user
     * @param array $current_planet Current planet
     * @param int   $ProductionTime Production time
     *
     * @return int
     */
    private static function buildingQueue($current_user, &$current_planet, $ProductionTime)
    {
        $resource   = parent::$objects->getObjects();

        if ($current_planet['planet_b_hangar_id'] != 0) {

            $Builded    = array();
            $BuildArray = array();
            $BuildQueue = explode(';', $current_planet['planet_b_hangar_id']);

            $current_planet['planet_b_hangar'] = Carbon::parse($current_planet['planet_b_hangar']);

            $current_planet['planet_b_hangar']->add($ProductionTime);

            foreach ($BuildQueue as $Node => $Array) {
                if ($Array != '') {
                    $Item               = explode(',', $Array);
                    $AcumTime           = DevelopmentsLib::developmentTime(
                        $current_user,
                        $current_planet,
                        $Item[0]
                    );
                    $BuildArray[$Node]  = array($Item[0], $Item[1], $AcumTime);
                }
            }

            $current_planet['planet_b_hangar_id']   = '';
            $UnFinished = false;

            foreach ($BuildArray as $Node => $Item) {

                $Element            = $Item[0];
                $Count              = $Item[1];
                $BuildTime          = $Item[2];
                $Builded[$Element]  = 0;

                if (!$UnFinished and $BuildTime > 0) {

                    $AllTime = $BuildTime * $Count;

                    if ($current_planet['planet_b_hangar']->timestamp >= $BuildTime) {

                        $Done   = min($Count, floor($current_planet['planet_b_hangar']->timestamp / $BuildTime));

                        if ($Count > $Done) {

                            $current_planet['planet_b_hangar']->sub($BuildTime * $Done);
                            
                            $UnFinished = true;
                            $Count      -= $Done;

                        } else {
                            $current_planet['planet_b_hangar']->sub($AllTime);
                            $Count = 0;
                        }

                        $Builded[$Element]                      += $Done;
                        $current_planet[$resource[$Element]]    += $Done;

                    } else {

                        $UnFinished = true;
                    }
                } elseif (!$UnFinished) {

                    $Builded[$Element]  += $Count;
                    $current_planet[$resource[$Element]]    += $Count;
                    $Count  = 0;
                }

                if ($Count != 0) {

                    $current_planet['planet_b_hangar_id'] .= $Element . "," . $Count . ";";
                }
            }
        } else {
            $Builded                            = '';
            $current_planet['planet_b_hangar']  = NULL;
        }

        return $Builded;
    }
}

/* end of UpdateResourcesLib.php */
