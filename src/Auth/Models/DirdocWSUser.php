<?php namespace UTEM\Dirdoc\Auth\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class DirdocWSUser extends Model implements AuthenticatableContract
{

    use Authenticatable;

    protected $table = 'usuarios';
    protected $primaryKey = 'rut';
    public $incrementing = false;

    protected $fillable = ['rut', 'nombres', 'apellidos', 'email'];
}
