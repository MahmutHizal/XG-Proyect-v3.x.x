<?php namespace application\models\game;


class auth
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static $instance;

    protected static $booted = false;

    protected $user;

    public static function initAuth()
    {
        if (static::$instance == null) {

            //make new istance of this class and save it to field for next usage
            $class  = __CLASS__;
            static::$instance = new $class();
        }
        static::$booted = true;
        return static::$instance;
    }

    protected static function is_booted()
    {
        return (bool)static::$booted;
    }

    public function __construct()
    {
        $this->user = new User();
    }

    public static function user()
    {
        if (self::is_booted())
        {
            return (self::$instance->CheckUserIsLogged() ? self::$instance->user : false);
        }
    }

    public function CheckUserIsLogged()
    {
        return $this->CheckSessionStarted();
    }

    private function CheckSessionStarted()
    {
        return (bool)$_SESSION;
    }

    public static function login($NameOrMail, $Password)
    {
        if(self::is_booted() && !self::$instance->CheckUserIsLogged())
        {
            return User::forLogin()
                ->where(function($query) use($NameOrMail) {
                    $query->where('user_name', '=', $NameOrMail)
                        ->orWhere('user_email', '=', $NameOrMail);
                })
                ->where("user_password", '=', sha1($Password))
                ->first();
        }
        return false;
    }
}