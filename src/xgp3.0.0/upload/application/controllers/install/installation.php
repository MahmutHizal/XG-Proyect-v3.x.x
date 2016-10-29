<?php
/**
 * Installation Controller
 *
 * PHP Version 5.5+
 *
 * @category Controllers
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */

namespace application\controllers\install;

use application\core\XGPCore;
use application\libraries\FunctionsLib;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;

/**
 * Installation Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class Installation extends XGPCore
{
    private $host;
    private $name;
    private $user;
    private $password;
    private $prefix;
    private $langs;

    /**
     * __construct()
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->langs    = parent::$lang;
        $this->_planet  = FunctionsLib::loadLibrary('PlanetLib');

        if ($this->serverRequirementes()) {
            
            $this->buildPage();
        } else {

            die(FunctionsLib::message($this->langs['ins_no_server_requirements'], '', '', false, false));
        }
    }

    /**
     * __destruct
     *
     * @return void
     */
    public function __destruct()
    {
        if(Manager::connection())
            Manager::connection()->disconnect();
    }

    /**
     * buildPage
     *
     * @return void
     */
    private function buildPage()
    {
        $parse      = $this->langs;
        $continue   = true;

        // VERIFICATION - WE NEED THE config DIR WRITABLE
        if (!$this->isWritable()) {
            die(FunctionsLib::message($this->langs['ins_not_writable'], '', '', false, false));
        }
        
        // VERIFICATION - WE DON'T WANT ANOTHER INSTALLATION
        if ($this->isInstalled()) {
            die(FunctionsLib::message($this->langs['ins_already_installed'], '', '', false, false));
        }

        // ACTION FOR THE CURRENT PAGE
        switch ((isset($_POST['page']) ? $_POST['page'] : '')) {
            case 'step1':
                
                $this->host     = isset($_POST['host']) ? $_POST['host'] : null;
                $this->user     = isset($_POST['user']) ? $_POST['user'] : null;
                $this->password = isset($_POST['password']) ? $_POST['password'] : null;
                $this->name     = isset($_POST['db']) ? $_POST['db'] : null;
                $this->prefix   = isset($_POST['prefix']) ? $_POST['prefix'] : null;

                if (!$this->validateDbData()) {
                    $alerts     = $this->langs['ins_empty_fields_error'];
                    $continue   = false;
                }

                if ($continue && !$this->tryConnection()) {
                    $alerts     = $this->langs['ins_not_connected_error'];
                    $continue   = false;
                }

                if ($continue && !$this->tryDatabase()) {
                    $alerts     = $this->langs['ins_db_not_exists'];
                    $continue   = false;
                }

                if ($continue && !$this->writeConfigFile()) {
                    $alerts     = $this->langs['ins_write_config_error'];
                    $continue   = false;
                }

                if ($continue) {
                    FunctionsLib::redirect('?page=installation&mode=step2');
                }

                $parse['alert']     = $this->saveMessage($alerts, 'warning');
                $parse['v_host']    = $this->host;
                $parse['v_db']      = $this->name;
                $parse['v_user']    = $this->user;
                $parse['v_prefix']  = $this->prefix;
                
                $current_page   = parent::$page->parseTemplate(
                    parent::$page->getTemplate('install/in_database_view'),
                    $parse
                );
                
                break;

            case 'step2':
                if ($continue) {
                    FunctionsLib::redirect('?page=installation&mode=step3');
                }

                $parse['alert'] = $this->saveMessage($alerts, 'warning');
                $current_page   = parent::$page->parseTemplate(
                    parent::$page->getTemplate('install/in_database_view'),
                    $parse
                );

                break;

            case 'step3':
                if (!$this->insertDbData()) {
                    $alerts     = $this->langs['ins_insert_tables_error'];
                    $continue   = false;
                }

                if ($continue) {
                    
                    FunctionsLib::redirect('?page=installation&mode=step4');
                }

                $parse['alert'] = $this->saveMessage($alerts, 'warning');
                $current_page   = parent::$page->parseTemplate(
                    parent::$page->getTemplate('install/in_database_view'),
                    $parse
                );
                break;

            case 'step4':
                FunctionsLib::redirect('?page=installation&mode=step5');
                break;

            case 'step5':
                $create_account_status = $this->createAccount();

                if ($create_account_status < 0) {

                    // Failure
                    if ($create_account_status == -1) {

                        $error_message  = $this->langs['ins_adm_empty_fields_error'];
                    } else {

                        $error_message  = $this->langs['ins_adm_invalid_email_address'];
                    }
                    
                    $parse['alert'] = $this->saveMessage($error_message, 'warning');

                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_create_admin_view'),
                        $parse
                    );

                    $continue       = false;
                }

                if ($continue) {

                    // set last stat update
                    FunctionsLib::updateConfig('stat_last_update', time());
                    
                    // set the installation language to the game language
                    FunctionsLib::updateConfig('lang', FunctionsLib::getCurrentLanguage());
                    
                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_create_admin_done_view'),
                        $this->langs
                    );
                    
                    // This will continue on false meaning "This is the end of the installation, no else where to go"
                    $continue       = false;
                }
                break;

            case '':
            default:
                break;
        }

        if ($continue) {

            switch ((isset($_GET['mode']) ? $_GET['mode'] : '')) {

                case 'step1':
                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_database_view'),
                        $this->langs
                    );

                    break;

                case 'step2':
                    $parse['step']              = 'step2';
                    $parse['done_config']       = '';
                    $parse['done_connected']    = $this->langs['ins_done_connected'];
                    $parse['done_insert']       = '';
                    $current_page               = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_done_actions_view'),
                        $parse
                    );

                    break;

                case 'step3':
                    $parse['step']              = 'step3';
                    $parse['done_config']       = $this->langs['ins_done_config'];
                    $parse['done_connected']    = '';
                    $parse['done_insert']       = '';
                    $current_page               = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_done_actions_view'),
                        $parse
                    );

                    break;

                case 'step4':
                    $parse['step']              = 'step4';
                    $parse['done_config']       = '';
                    $parse['done_connected']    = '';
                    $parse['done_insert']       = $this->langs['ins_done_insert'];
                    $current_page               = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_done_actions_view'),
                        $parse
                    );

                    break;

                case 'step5':
                    $parse['step']  = 'step5';
                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_create_admin_view'),
                        $parse
                    );

                    break;

                case 'license':
                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_license_view'),
                        $this->langs
                    );

                    break;

                case '':
                case 'overview':
                default:
                    $current_page   = parent::$page->parseTemplate(
                        parent::$page->getTemplate('install/in_welcome_view'),
                        $this->langs
                    );

                    break;
            }
        }

        parent::$page->display($current_page);
    }

    /**
     * method server_requirementes
     * param
     * return true if the required server requirements are met
     */
    private function serverRequirementes()
    {
        if (version_compare(PHP_VERSION, '5.5.0', '<')) {
            
            return false;
        } else {
            
            return true;
        }
    }

    /**
     * isWritable
     *
     * @return boolean
     */
    private function isWritable()
    {
        $config_dir    = XGP_ROOT . 'application/config/';
        
        return is_writable($config_dir);
    }
    
    /**
     * isInstalled
     *
     * @return boolean
     */
    private function isInstalled()
    {
        // if file not exists
        $config_file    = XGP_ROOT . 'application/config/config.php';

        if (!file_exists($config_file) or filesize($config_file) == 0) {
            
            return false;
        }
        
        // if no db object
        if (parent::$db == null) {

            return false;
        }
        
        // check if tables exist
        if (!$this->tablesExists()) {
            
            return false;
        }
        
        // check for admin account
        if (!$this->adminExists()) {
            
            return false;
        }
        
        return true;
    }

    /**
     * tablesExists
     *
     * @return boolean
     */
    private function tablesExists()
    {
        $result = Manager::connection()
            ->getPdo()
            ->query('SHOW TABLES;');
        //$result = parent::$db->query("SHOW TABLES FROM " . DB_NAME);
        $arr = [];
        foreach($result as $row) {
            if(strpos($row[0], DB_PREFIX) !== false) {
                $arr[] = $row[0];
            }
        }
        if(count($arr) > 0) {

            return true;
        }
        return false;
    }
    
    /**
     * adminExists
     *
     * @return boolean
     */
    private function adminExists()
    {
        return (bool)Manager::table(USERS)
            ->select('user_id')
            ->where('user_authlevel', '=', '3')
            ->first();
    }

    /**
     * tryConnection
     *
     * @return boolean
     */
    private function tryConnection()
    {

        return true;//(bool)Manager::connection()->getDatabaseName();
    }

    /**
     * tryDatabase
     *
     * @return boolean
     */
    private function tryDatabase()
    {
        return true;//(bool)Manager::connection()->getDatabaseName();
    }

    /**
     * writeConfigFile
     *
     * @return boolean
     */
    private function writeConfigFile()
    {
        $config_file    = @fopen(XGP_ROOT . CONFIGS_PATH . 'config.php', "w");

        if (!$config_file) {
            
            return false;
        }

        $data   = "<?php\n";
        $data   .= "defined('DB_HOST') ? NULL : define('DB_HOST', '".$this->host."');\n";
        $data   .= "defined('DB_USER') ? NULL : define('DB_USER', '".$this->user."');\n";
        $data   .= "defined('DB_PASS') ? NULL : define('DB_PASS', '".$this->password."');\n";
        $data   .= "defined('DB_NAME') ? NULL : define('DB_NAME', '".$this->name."');\n";
        $data   .= "defined('DB_PREFIX') ? NULL : define('DB_PREFIX', '".$this->prefix."');\n";
        $data   .= "defined('SECRETWORD') ? NULL : define('SECRETWORD', 'xgp-".$this->generateToken()."');\n";
        $data   .= "?>";

        // create the new file
        if (fwrite($config_file, $data)) {

            fclose($config_file);
            
            return true;
        }
        
        // check if something was created and delete it
        if (file_exists($config_file)) {

            unlink($config_file);
        }
        
        return false;
    }

    /**
     * insertDbData
     *
     * @return boolean
     */
    private function insertDbData()
    {

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Store the current sql_mode
            Manager::connection()->getPdo()->query('SET @orig_mode = @@global.sql_mode');

            // Set sql_mode to one that won't trigger errors...
            Manager::connection()->getPdo()->query('SET @@global.sql_mode = "MYSQL40"');
        }

        Manager::schema()->create(USERS, function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('user_name', 32)->unique();
            $table->string('user_password', 40);
            $table->string('user_email', 64)->unique();
            $table->string('user_email_permanent', 64);
            $table->tinyInteger('user_authlevel')->default(0);
            $table->integer('user_home_planet_id')->default(0)->unsigned();

            $table->integer('user_galaxy')->default(0);
            $table->integer('user_planet')->default(0);
            $table->integer('user_system')->default(0);
            $table->integer('user_current_planet')->default(0);

            $table->ipAddress('user_lastip');
            $table->ipAddress('user_ip_at_reg');
            $table->text('user_agent');
            $table->string('user_current_page', 32);
            $table->timestamp('user_register_time')->nullable();
            $table->timestamp('user_onlinetime')->nullable();

            $table->text('user_fleet_shortcuts');

            $table->integer('user_ally_id')->default(0);
            $table->integer('user_ally_request')->default(0);
            $table->text('user_ally_request_text');
            $table->timestamp('user_ally_register_time')->nullable();
            $table->integer('user_ally_rank_id')->default(0);

            $table->timestamp('user_banned')->nullable();

            $table->timestamps();
        });

        Manager::schema()->create(ACS_FLEETS, function(Blueprint $table){
            $table->increments('acs_fleet_id');
            $table->string('acs_fleet_name', 50);
            $table->text('acs_fleet_members');
            $table->text('acs_fleet_fleets');
            $table->integer('acs_fleet_galaxy');
            $table->integer('acs_fleet_system');
            $table->integer('acs_fleet_planet');
            $table->integer('acs_fleet_planet_type');
            $table->text('acs_fleet_invited');
            $table->timestamps();
        });

        Manager::schema()->create(ALLIANCE, function(Blueprint $table){
            $table->increments('alliance_id');
            $table->string('alliance_name', 32);
            $table->string('alliance_tag', 8);
            $table->integer('alliance_owner')
                ->default(0);
            $table->string('alliance_description', 2000);
            $table->string('alliance_text', 2000);
            $table->string('alliance_request', 2000);
            $table->string('alliance_web', 255);
            $table->string('alliance_image', 255);
            $table->string('alliance_owner_range', 32);
            $table->integer('alliance_request_notallow')
                ->default(0);
            $table->text('alliance_ranks');

            $table->timestamps();
            $table->softDeletes();
        });

        Manager::schema()->create(ALLIANCE_STATISTICS, function(Blueprint $table) {
            $table->increments('alliance_statistic_alliance_id');
            $table->double('alliance_statistic_buildings_points', 132, 8)->default(0);
            $table->integer('alliance_statistic_buildings_old_rank')->default(0);
            $table->integer('alliance_statistic_buildings_rank')->default(0);

            $table->double('alliance_statistic_defenses_points', 132, 8)->default(0);
            $table->integer('alliance_statistic_defenses_old_rank')->default(0);
            $table->integer('alliance_statistic_defenses_rank')->default(0);

            $table->double('alliance_statistic_ships_points', 132, 8)->default(0);
            $table->integer('alliance_statistic_ships_old_rank')->default(0);
            $table->integer('alliance_statistic_ships_rank')->default(0);

            $table->double('alliance_statistic_technology_points', 132, 8)->default(0);
            $table->integer('alliance_statistic_technology_old_rank')->default(0);
            $table->integer('alliance_statistic_technology_rank')->default(0);

            $table->double('alliance_statistic_total_points', 132, 8)->default(0);
            $table->integer('alliance_statistic_total_old_rank')->default(0);
            $table->integer('alliance_statistic_total_rank')->default(0);

            $table->timestamps();
        });

        Manager::schema()->create(BANNED, function(Blueprint $table){
            $table->increments('banned_id');
            $table->string('banned_who', 64)->nullable();
            $table->string('banned_who2', 64)->nullable();
            $table->string('banned_theme', 2000)->default('NONE');
            $table->timestamp('banned_time')->nullable();
            $table->timestamp('banned_longer')->nullable();
            $table->string('banned_author')->default('System');
            $table->string('banned_email')->nullable();
        });

        Manager::schema()->create(BUDDY, function(Blueprint $table){
            $table->increments('buddy_id');
            $table->integer('buddy_sender')->unsigned();
            $table->integer('buddy_receiver')->unsigned();
            $table->integer('buddy_status')->default(0);
            $table->string('buddy_request_text', 500)->nullable();
            $table->timestamps();
        });

        Manager::schema()->create(BUILDINGS, function(Blueprint $table){
            $table->increments('building_id');
            $table->integer('building_planet_id')->unsigned();
            $table->integer('building_metal_mine')->default(0);
            $table->integer('building_crystal_mine')->default(0);
            $table->integer('building_deuterium_sintetizer')->default(0);
            $table->integer('building_solar_plant')->default(0);
            $table->integer('building_fusion_reactor')->default(0);
            $table->integer('building_robot_factory')->default(0);
            $table->integer('building_nano_factory')->default(0);
            $table->integer('building_hangar')->defaut(0);
            $table->integer('building_metal_store')->default(0);
            $table->integer('building_crystal_store')->default(0);
            $table->integer('building_deuterium_tank')->default(0);
            $table->integer('building_laboratory')->default(0);
            $table->integer('building_terraformer')->default(0);
            $table->integer('building_ally_deposit')->default(0);
            $table->integer('building_missile_silo')->default(0);
            $table->integer('building_mondbasis')->default(0);
            $table->integer('building_phalanx')->default(0);
            $table->integer('building_jump_gate')->default(0);
        });

        Manager::schema()->create(DEFENSES, function(Blueprint $table){
            $table->increments('defense_id');
            $table->integer('defense_planet_id')->unsigned();
            $table->integer('defense_rocket_launcher')->default(0);
            $table->integer('defense_light_laser')->default(0);
            $table->integer('defense_heavy_laser')->default(0);
            $table->integer('defense_ion_cannon')->default(0);
            $table->integer('defense_gauss_cannon')->default(0);
            $table->integer('defense_plasma_turret')->default(0);
            $table->integer('defense_small_shield_dome')->default(0);
            $table->integer('defense_large_shield_dome')->default(0);
            $table->integer('defense_anti-ballistic_missile')->default(0);
            $table->integer('defense_interplanetary_missile')->default(0);
        });

        Manager::schema()->create(FLEETS, function(Blueprint $table){
            $table->increments('fleet_id');
            $table->integer('fleet_owner')->default(0);
            $table->integer('fleet_mission')->default(0);
            $table->integer('fleet_amount')->default(0);
            $table->text('fleet_array')->nullable();

            $table->timestamp('fleet_start_time')->nullable();
            $table->integer('fleet_start_galaxy')->default(0);
            $table->integer('fleet_start_system')->default(0);
            $table->integer('fleet_start_planet')->default(0);
            $table->integer('fleet_start_type')->default(0);

            $table->timestamp('fleet_end_time')->nullable();
            $table->timestamp('fleet_end_stay')->nullable();
            $table->integer('fleet_end_galaxy')->default(0);
            $table->integer('fleet_end_system')->default(0);
            $table->integer('fleet_end_planet')->default(0);
            $table->integer('fleet_end_type')->default(0);

            $table->integer('fleet_target_obj')->default(0);

            $table->double('fleet_resource_metal', 132, 8)->default(0);
            $table->double('fleet_resource_crystal', 132, 8)->default(0);
            $table->double('fleet_resource_deuterium', 132, 8)->default(0);
            $table->integer('fleet_target_owner')->default(0);
            $table->string('fleet_group', 15)->default(0);
            $table->integer('fleet_mess')->default(0);
            $table->integer('fleet_creation')->nullable();
        });

        Manager::schema()->create(MESSAGES, function(Blueprint $table){
            $table->increments('message_id');
            $table->integer('message_sender')->default(0);
            $table->integer('message_receiver')->default(0);
            $table->integer('message_type')->default(0);
            $table->string('message_from',48)->nullable();
            $table->text('message_subject');
            $table->text('message_text');
            $table->integer('message_read')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });

        Manager::schema()->create(NOTES, function(Blueprint $table){
            $table->increments('note_id');
            $table->integer('note_owner')->unsigned();
            $table->timestamps();
            $table->integer('note_priority')->default(0);
            $table->string('note_title', 32);
            $table->text('note_text')->nullable();

        });

        Manager::schema()->create(OPTIONS, function(Blueprint $table){
            $table->increments('option_id');
            $table->string('option_name', 191)->nullable();
            $table->text('option_value')->nullable(0);
        });

        Manager::schema()->create(PLANETS, function (Blueprint $table){
            $table->increments('planet_id');
            $table->string('planet_name', '50')->nullable();
            $table->integer('planet_user_id')->unsigned();

            $table->integer('planet_galaxy')->default(0);
            $table->integer('planet_system')->default(0);
            $table->integer('planet_planet')->default(0);

            $table->integer('planet_type')->default(1);
            $table->integer('planet_destroyed')->default(0);

            $table->integer('planet_b_building')->default(0);
            $table->text('planet_b_building_id')->nullable();

            $table->integer('planet_b_tech')->default(0);
            $table->text('planet_b_tech_id')->nullable();

            $table->integer('planet_b_hangar')->default(0);
            $table->text('planet_b_hangar_id')->nullable();

            $table->string('planet_image', 32)->default('normaltempplanet01');
            $table->integer('planet_diameter')->default(12800);
            $table->integer('planet_field_current')->default(0);
            $table->integer('planet_field_max')->default(163);
            $table->integer('planet_temp_min')->default(-17);
            $table->integer('planet_temp_max')->default(23);

            $table->double('planet_metal', 132, 8)->default(0);
            $table->integer('planet_metal_perhour')->default(0);
            $table->bigInteger('planet_metal_max')->default(10000);

            $table->double('planet_crystal', 132, 8)->default(0);
            $table->integer('planet_crystal_perhour')->default(0);
            $table->bigInteger('planet_crystal_max')->default(10000);

            $table->double('planet_deuterium', 132, 8)->default(0);
            $table->integer('planet_deuterium_perhour')->default(0);
            $table->bigInteger('planet_deuterium_max')->default(10000);

            $table->integer('planet_energy_used')->default(0);
            $table->bigInteger('planet_energy_max')->default(0);

            $table->integer('planet_building_metal_mine_porcent')->default(10);
            $table->integer('planet_building_crystal_mine_porcent')->default(10);
            $table->integer('planet_building_deuterium_sintetizer_porcent')->default(10);
            $table->integer('planet_building_solar_plant_porcent')->default(10);
            $table->integer('planet_building_fusion_reactor_porcent')->default(10);
            $table->integer('planet_ship_solar_satellite_porcent')->default(10);

            $table->integer('planet_last_jump_time')->default(0);
            $table->bigInteger('planet_debris_metal')->default(0);
            $table->bigInteger('planet_debris_crystal')->default(0);
            $table->integer('planet_invisible_start_time')->default(0);


            $table->timestamps();

            $table->foreign('planet_user_id')
                ->references('user_id')
                ->on(USERS)
                ->onDelete('cascade');
        });

        Manager::schema()->create(PREMIUM, function(Blueprint $table){
            $table->increments('premium_id');
            $table->integer('premium_user_id')->unsigned();
            $table->integer('premium_dark_matter')->default(0);
            $table->integer('premium_officier_commander')->default(0);
            $table->integer('premium_officier_admiral')->default(0);
            $table->integer('premium_officier_engineer')->default(0);
            $table->integer('premium_officier_geologist')->default(0);
            $table->integer('premium_officier_technocrat')->default(0);

            $table->foreign('premium_user_id')
                ->references('user_id')
                ->on(USERS)
                ->onDelete('cascade');
        });

        Manager::schema()->create(REPORTS, function (Blueprint $table) {
            $table->increments('report_id');
            $table->string('report_owners');
            $table->string('report_rid');
            $table->text('report_content');
            $table->tinyInteger('report_destroyed')->default(0);
            $table->timestamps();
        });

        Manager::schema()->create(RESEARCH, function (Blueprint $table){
            $table->increments('research_id');
            $table->integer('research_user_id')->unsigned();
            $table->integer('research_current_research')->default(0);
            $table->integer('research_espionage_technology')->default(0);
            $table->integer('research_computer_technology')->default(0);
            $table->integer('research_weapons_technology')->default(0);
            $table->integer('research_shielding_technology')->default(0);
            $table->integer('research_armour_technology')->default(0);
            $table->integer('research_energy_technology')->default(0);
            $table->integer('research_hyperspace_technology')->default(0);
            $table->integer('research_combustion_drive')->default(0);
            $table->integer('research_impulse_drive')->default(0);
            $table->integer('research_hyperspace_drive')->default(0);
            $table->integer('research_laser_technology')->default(0);
            $table->integer('research_ionic_technology')->default(0);
            $table->integer('research_plasma_technology')->default(0);
            $table->integer('research_intergalactic_research_network')->default(0);
            $table->integer('research_astrophysics')->default(0);
            $table->integer('research_graviton_technology')->default(0);

            $table->foreign('research_user_id')
                ->references('user_id')
                ->on(USERS)
                ->onDelete('cascade');
        });

        Manager::schema()->create(SESSIONS, function (Blueprint $table){
            $table->char('session_id', 32);
            $table->longText('session_data');
            $table->timestamps();
        });

        Manager::schema()->create(SETTINGS, function (Blueprint $table) {
            $table->increments('setting_id');
            $table->integer('setting_user_id')->unsigned();
            $table->tinyInteger('setting_no_ip_check')->default(1);
            $table->tinyInteger('setting_planet_sort')->default(0);
            $table->tinyInteger('setting_planet_order')->default(0);
            $table->tinyInteger('setting_probes_amount')->default(1);
            $table->tinyInteger('setting_fleet_actions')->default(0);
            $table->tinyInteger('setting_galaxy_espionage')->default(1);
            $table->tinyInteger('setting_galaxy_write')->default(1);
            $table->tinyInteger('setting_galaxy_buddy')->default(1);
            $table->tinyInteger('setting_galaxy_missile')->default(1);
            $table->tinyInteger('setting_vacations_status')->default(0);
            $table->tinyInteger('setting_vacations_until')->default(0);
            $table->tinyInteger('setting_delete_account')->default(0);


            $table->foreign('setting_user_id')
                ->references('user_id')
                ->on(USERS)
                ->onDelete('cascade');
        });

        Manager::schema()->create(SHIPS, function(Blueprint $table) {
            $table->increments('ship_id');
            $table->integer('ship_planet_id')->unsigned();

            $table->integer('ship_small_cargo_ship')->defaut(0);
            $table->integer('ship_big_cargo_ship')->defaut(0);
            $table->integer('ship_light_fighter')->defaut(0);
            $table->integer('ship_heavy_fighter')->defaut(0);
            $table->integer('ship_cruiser')->defaut(0);
            $table->integer('ship_battleship')->defaut(0);
            $table->integer('ship_colony_ship')->defaut(0);
            $table->integer('ship_recycler')->defaut(0);
            $table->integer('ship_espionage_probe')->defaut(0);
            $table->integer('ship_bomber')->defaut(0);
            $table->integer('ship_solar_satellite')->defaut(0);
            $table->integer('ship_destroyer')->defaut(0);
            $table->integer('ship_deathstar')->defaut(0);
            $table->integer('ship_battlecruiser')->defaut(0);

            $table->foreign('ship_planet_id')
                ->references('planet_id')
                ->on(PLANETS)
                ->onDelete('cascade');
        });

        Manager::schema()->create(USERS_STATISTICS, function (Blueprint $table) {
            $table->increments('user_statistic_id');
            $table->integer('user_statistic_user_id')->unsigned();

            $table->double('user_statistic_buildings_points', 132, 8)->default(0);
            $table->integer('user_statistic_buildings_old_rank')->default(0);
            $table->integer('user_statistic_buildings_rank')->default(0);

            $table->double('user_statistic_defenses_points', 132, 8)->default(0);
            $table->integer('user_statistic_defenses_old_rank')->default(0);
            $table->integer('user_statistic_defenses_rank')->default(0);

            $table->double('user_statistic_ships_points', 132, 8)->default(0);
            $table->integer('user_statistic_ships_old_rank')->default(0);
            $table->integer('user_statistic_ships_rank')->default(0);

            $table->double('user_statistic_technology_points', 132, 8)->default(0);
            $table->integer('user_statistic_technology_old_rank')->default(0);
            $table->integer('user_statistic_technology_rank')->default(0);

            $table->double('user_statistic_total_points', 132, 8)->default(0);
            $table->integer('user_statistic_total_old_rank')->default(0);
            $table->integer('user_statistic_total_rank')->default(0);

            $table->timestamps();

            $table->foreign('user_statistic_user_id')
                ->references('user_id')
                ->on(USERS)
                ->onDelete('cascade');

        });

        Manager::table(OPTIONS)->insert([
            ['option_name' => 'game_name', 'option_value' => 'XGP'],
            ['option_name' => 'game_logo', 'option_value' => 'http://www.xgproyect.org/images/misc/xg-logo.png'],
            ['option_name' => 'lang', 'option_value' => 'spanish'],
            ['option_name' => 'game_speed', 'option_value' => '2500'],
            ['option_name' => 'fleet_speed', 'option_value' => '2500'],
            ['option_name' => 'resource_multiplier', 'option_value' => '1'],
            ['option_name' => 'admin_email', 'option_value' => '0'],
            ['option_name' => 'forum_url', 'option_value' => 'http://www.xgproyect.org/'],
            ['option_name' => 'game_enable', 'option_value' => '1'],
            ['option_name' => 'close_reason', 'option_value' => 'Sorry, the server is currently offline.'],
            ['option_name' => 'ssl_enabled', 'option_value' => '0'],
            ['option_name' => 'date_time_zone', 'option_value' => 'Europe/Istanbul'],
            ['option_name' => 'date_format', 'option_value' => 'd.m.Y'],
            ['option_name' => 'date_format_extended', 'option_value' => 'd.m.Y H:i:s'],
            ['option_name' => 'adm_attack', 'option_value' => '1'],
            ['option_name' => 'game_logo', 'option_value' => 'http://www.xgproyect.org/images/misc/xg-logo.png'],
            ['option_name' => 'game_logo', 'option_value' => 'http://www.xgproyect.org/images/misc/xg-logo.png'],
            ['option_name' => 'fleet_cdr', 'option_value' => '30'],
            ['option_name' => 'defs_cdr', 'option_value' => '30'],
            ['option_name' => 'noobprotection', 'option_value' => '1'],
            ['option_name' => 'noobprotectiontime', 'option_value' => '50000'],
            ['option_name' => 'noobprotectionmulti', 'option_value' => '5'],
            ['option_name' => 'modules', 'option_value' => '1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;1;0;1;1;'],
            ['option_name' => 'moderation', 'option_value' => '1,1,0,0,1,0;1,1,0,1,1,1;1;'],
            ['option_name' => 'initial_fields', 'option_value' => '163'],
            ['option_name' => 'metal_basic_income', 'option_value' => '90'],
            ['option_name' => 'crystal_basic_income', 'option_value' => '45'],
            ['option_name' => 'deuterium_basic_income', 'option_value' => '0'],
            ['option_name' => 'energy_basic_income', 'option_value' => '0'],
            ['option_name' => 'reg_enable', 'option_value' => '1'],
            ['option_name' => 'reg_welcome_message', 'option_value' => '1'],
            ['option_name' => 'reg_welcome_email', 'option_value' => '1'],
            ['option_name' => 'stat_points', 'option_value' => '1000'],
            ['option_name' => 'stat_update_time', 'option_value' => '1'],
            ['option_name' => 'stat_admin_level', 'option_value' => '0'],
            ['option_name' => 'stat_last_update', 'option_value' => '0'],
            ['option_name' => 'premium_url', 'option_value' => 'http://www.xgproyect.org/game.php?page=officier'],
            ['option_name' => 'trader_darkmatter', 'option_value' => '3500'],
            ['option_name' => 'auto_backup', 'option_value' => '0'],
            ['option_name' => 'last_backup', 'option_value' => '0'],
            ['option_name' => 'last_cleanup', 'option_value' => '0'],
            ['option_name' => 'version', 'option_value' => '3.0.0'],
            ['option_name' => 'lastsettedgalaxypos', 'option_value' => '1'],
            ['option_name' => 'lastsettedsystempos', 'option_value' => '1'],
            ['option_name' => 'lastsettedplanetpos', 'option_value' => '1'],
        ]);



        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Change it back to original sql_mode
            Manager::connection()->getPdo()->query('SET @@global.sql_mode = @orig_mode');
        }
        return true;
    }

    /**
     * createAccount
     *
     * @return negative value if an error ocurred, or 0 if admin account was successfully created
     *          -1: Some field is empty
     *          -2: Admin email is invalid
     */
    private function createAccount()
    {
        // validations
        if (empty($_POST['adm_user']) || empty($_POST['adm_pass']) || empty($_POST['adm_email'])) {
            return -1;
        }
            
        if (!FunctionsLib::validEmail($_POST['adm_email'])) {
            return -2;
        }

        // some default values
        $adm_name   = parent::$db->escapeValue($_POST['adm_user']);
        $adm_email  = parent::$db->escapeValue($_POST['adm_email']);
        $adm_pass   = sha1($_POST['adm_pass']);

        // create user and its planet
        parent::$users->createUserWithOptions(
            [
                'user_name' => $adm_name,
                'user_password' => $adm_pass,
                'user_email' => $adm_email,
                'user_email_permanent' => $adm_email,
                'user_authlevel' => '3',
                'user_home_planet_id' => '1',
                'user_galaxy' => 1,
                'user_system' => 1,
                'user_planet' => 1,
                'user_current_planet' => 1,
                'user_ip_at_reg' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => '0',
                'user_current_page' => '0'
            ]
        );
        
        $this->_planet->setNewPlanet(1, 1, 1, 1, $adm_name);

        // write the new admin email for support
        FunctionsLib::updateConfig('admin_email', $adm_email);

        return true;
    }

    /**
     * validateDbData
     *
     * @return boolean
     */
    private function validateDbData()
    {
        return !empty($this->host) && !empty($this->name) &&
                !empty($this->user) && !empty($this->prefix);
    }

    /**
     * generateToken
     *
     * return string
     */
    private function generateToken()
    {
        $characters = 'aazertyuiopqsdfghjklmwxcvbnAZERTYUIOPQSDFGHJKLMWXCVBN1234567890';
        $count      = strlen($characters);
        $new_token  = '';
        $lenght     = 16;
        srand((double)microtime() * 1000000);

        for ($i = 0; $i < $lenght; $i++) {
            $character_boucle   = mt_rand(0, $count - 1);
            $new_token          = $new_token . substr($characters, $character_boucle, 1);
        }

        return $new_token;
    }

    /**
     * saveMessage
     *
     * @param string $message Message
     * @param string $result  Result
     *
     * @return array
     */
    private function saveMessage($message, $result = 'ok')
    {
        switch ($result) {
            case 'ok':
                $parse['color']     = 'alert-success';
                $parse['status']    = $this->langs['ins_ok_title'];
                break;

            case 'error':
                $parse['color']     = 'alert-error';
                $parse['status']    = $this->langs['ins_error_title'];
                break;

            case 'warning':
                $parse['color']     = 'alert-block';
                $parse['status']    = $this->langs['ins_warning_title'];
                break;
        }

        $parse['message']   = $message;

        return parent::$page->parseTemplate(
            parent::$page->getTemplate('adm/save_message_view'),
            $parse
        );
    }
}

/* end of installation.php */
