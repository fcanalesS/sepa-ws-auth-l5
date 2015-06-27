<?php namespace UTEM\Dirdoc\Auth\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class DirdocWSUser extends Model implements AuthenticatableContract
{

    use Authenticatable;

    protected $fillable = ['rut', 'nombres', 'apellidos', 'email'];
}
