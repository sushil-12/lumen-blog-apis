<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Support\Facades\Hash;

class User extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'password', 'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Ensure password is only hashed once before saving to the database.
     */
    public function setPasswordAttribute($value)
    {
        if (Hash::needsRehash($value)) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}