<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    protected $fillable = [
        'name',
        'email',
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'active',
        'order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'port' => 'integer',
        'order' => 'integer',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Scope para obtener solo las cuentas activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para ordenar por prioridad
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Set password attribute (encrypt)
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get password attribute (decrypt)
     */
    public function getPasswordAttribute($value)
    {
        if ($value) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get mail configuration array for Laravel Mail
     */
    public function getMailConfig(): array
    {
        return [
            'transport' => $this->mailer,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'password' => $this->password,
            'timeout' => 30,
            'from' => [
                'address' => $this->from_address ?? $this->email,
                'name' => $this->from_name ?? $this->name,
            ],
        ];
    }
}
