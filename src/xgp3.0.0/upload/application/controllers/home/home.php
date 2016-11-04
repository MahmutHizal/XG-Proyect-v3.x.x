<?php
/**
 * Home Controller
 *
 * PHP Version 5.5+
 *
 * @category Controller
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */

namespace application\controllers\home;

use application\core\XGPCore;
use application\libraries\FunctionsLib;
use application\models\game\auth;
use application\models\game\User;
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager;

/**
 * Home Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class Home extends XGPCore
{
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

        $this->buildPage();
    }
    /**
     * buildPages
     *
     * @return void
     */
    private function buildPage()
    {
        $parse  = $this->langs;

        if ($_POST) {
            $login = User::select([
                "user_id",
                "user_name",
                "user_password",
                "user_banned",
                "user_home_planet_id"
            ])
                ->where(function($query) {
                    $query->where('user_name', '=', $_POST["login"])
                        ->orWhere('user_email', '=', $_POST["login"]);
                })
                ->where("user_password", '=', sha1($_POST["pass"]))
                ->first();
            if($login)
            {
                if($login->user_banned && $login->user_banned <= Carbon::now())
                {
                    $login->user_banned = NULL;
                    Manager::table(BANNED)
                        ->where('banned_who', '=', $login->user_name);
                }
                if(parent::$users->userLogin($login->user_id, $login->user_name, $login->user_password)) {
                    $login->user_current_planet = $login->user_home_planet_id;

                    $login->save();

                    FunctionsLib::redirect('game.php?page=overview');
                }
            }
            else
            {
                // If login fails
                FunctionsLib::redirect('index.php');
            }

        } else {
            $parse['year']          = Carbon::now()->year;
            $parse['version']       = SYSTEM_VERSION;
            $parse['servername']    = strtr($this->langs['hm_title'], ['%s' => FunctionsLib::readConfig('game_name')]);
            $parse['game_logo']     = FunctionsLib::readConfig('game_logo');
            $parse['forum_url']     = FunctionsLib::readConfig('forum_url');
            $parse['js_path']       = JS_PATH . 'home/';
            $parse['css_path']      = CSS_PATH . 'home/';
            $parse['img_path']      = IMG_PATH . 'home/';
            $parse['base_path']     = BASE_PATH;

            parent::$page->display(
                parent::$page->parseTemplate(parent::$page->getTemplate('home/index_body'), $parse),
                false,
                '',
                false
            );
        }
    }
}

/* end of home.php */
