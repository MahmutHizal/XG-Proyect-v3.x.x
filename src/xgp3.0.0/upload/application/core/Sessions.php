<?php
/**
 * Sessions
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
use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Sessions Class
 *
 * @category Classes
 * @package  Application
 * @author   XG Proyect Team
 * @license  http://www.xgproyect.org XG Proyect
 * @link     http://www.xgproyect.org
 * @version  3.0.0
 */
class Sessions extends XGPCore implements \SessionHandlerInterface
{
    /**
     *
     * @var boolean
     */
    private $alive  = true;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        session_start();

        // WE'RE GOING TO HANDLE A DIFFERENT DB OBJECT FOR THE SESSIONS
        // $this->dbc  = Capsule::connection();

    }

    /**
     * __destruct
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->alive) {

            session_write_close();
            $this->alive    = false;
        }
    }

    /**
     * delete
     *
     * @return void
     */
    public function delete()
    {
        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (!empty($_SESSION)) {

            unset($_SESSION);
            @session_destroy();
        }

        $this->alive    = false;
    }

    /**
     * open
     *
     * @return Database
     */
    public function open($thePath, $theID)
    {
        return true;
    }

    /**
     * close
     *
     * @return void
     */
    public function close()
    {
        return true;
    }

    /**
     * read
     *
     * @param string $sid Session Id
     *
     * @return void
     */
    public function read($sid)
    {
        $query = Capsule::table(SESSIONS)->select('session_data')->where('session_id', '=', $sid);
        if($query->count() > 0)
            return $query->first()->session_data;
        else
        {
            $query->insert(["session_id" => $sid, "created_at" => Carbon::now()]);
            return '';
        }

    }

    /**
     * write
     *
     * @param string $sid  Session Id
     * @param string $data Session Data
     *
     * @return array
     */
    public function write($sid, $data)
    {
        $query = Capsule::table(SESSIONS)
            ->where('session_id', '=', $sid);
        if($query->count() == 0)
            $query->insert(['session_id' => $sid, 'session_data' => $data]);
        else
            $query->update(['session_data' => $data]);
        //die(print_r(Capsule::connection()->getQueryLog(), false));
        return true;
    }

    /**
     * destroy
     *
     * @param string $sid Session Id
     *
     * @return array
     */
    public function destroy($sid)
    {
        $_SESSION   = [];

        return (bool)Capsule::table(SESSIONS)->where('session_id', '=', $sid)->delete();
    }

    /**
     * clean
     *
     * @param int $expire Expire
     *
     * @return array
     */
    public function gc($expire)
    {
        return (bool)(Capsule::table(SESSIONS)
            ->where(Capsule::connection()->raw("DATE_ADD(`created_at` INTERVAL " . (int)$expire . " SECOND) < NOW()"))
            ->orWhere(Capsule::connection()->raw("DATE_ADD(`updated_at` INTERVAL " . (int)$expire . " SECOND) < NOW()"))
            ->delete()
        );
    }
}

/* end of Sessions.php */