<?php
/**
 * Options
 *
 * PHP Version 5.5+
 *
 * @category Core
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */

namespace application\core;
use Illuminate\Database\Capsule\Manager;

/**
 * Options Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class Options extends XGPCore
{
    /**
     *
     * @var Xml
     */
    private static $instance = null;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Static function used to istance this class: implements singleton pattern to avoid multiple parsing.
     *
     * @return Options
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            
            //make new istance of this class and save it to field for next usage
            $class  = __class__;
            self::$instance = new $class();
        }

        return self::$instance;
    }
    
    /**
     * Get the game options, leaving the param $option empty will return all of them
     *
     * @param string $option Option
     *
     * @return mixed
     */
    public function getOptions($option = NULL)
    {
        if(!$option) {
            $ReturnER = [];
            $query = Manager::table(OPTIONS)->get(["option_name", "option_value"]);
            foreach ($query as $item) {
                $ReturnER[$item->option_name] = $item->option_value;
            }
            return $ReturnER;
        } else {
            $query = Manager::table(OPTIONS)
                ->where('option_name', '=', $option)
                ->first(["option_value"]);
            return ($query ? $query->option_value : false);
        }
    }
    
    /**
     * Update the option in the database
     *
     * @param string $option Option
     * @param string $value  Value
     *
     * @return boolean
     */
    public function writeOptions($option, $value = NULL)
    {
        if(is_array($option))
        {
            foreach ($option as $option_name => $option_value)
            {
                $this->writeOption($option_name, $option_value);
            }
            return Manager::connection()
                ->getPdo()
                ->errorCode();
        }
        else
        {
            return $this->writeOption($option, $value);
        }
    }

    private function writeOption($option_name, $option_value)
    {
        return (!$this->getOptions ($option_name) && !empty($option_value)
            ? Manager::table (OPTIONS)
                ->insert (["option_name" => $option_name, "option_value" => $option_value])
            : Manager::table (OPTIONS)
                ->where ('option_name', '=', $option_name)
                ->update (["option_value" => $option_value]));
    }
    
    /**
     * Insert a new option into database
     * 
     * @param string $option Option
     * @param string $value  Value
     * 
     * @return boolean
     */
    public function insertOption($option, $value = '')
    {
        return $this->writeOption($option, $value);
    }
    
    
    /**
     * Delete an option permanently
     * 
     * @param string $option Option
     * 
     * @return boolean
     */
    public function deleteOption($option)
    {
        return Manager::table(OPTIONS)
            ->where('option_name', '=', $option)
            ->delete();
    }
}

/* end of Options.php */
