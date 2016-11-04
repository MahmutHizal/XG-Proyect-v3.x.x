<?php namespace application\models\game;


use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = USERS;

    public $timestamps = true;

    protected $primaryKey = 'user_id';

    public function scopeForLogin($query)
    {
        return $query->addSelect([
            "user_id",
            "user_name",
            "user_password",
            "user_banned",
            "user_home_planet_id"
        ]);
    }
}