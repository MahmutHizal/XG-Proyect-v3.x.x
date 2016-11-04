<?php namespace application\models\game;


use Illuminate\Database\Eloquent\Model;

class Planet extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = PLANETS;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'planet_id';


}