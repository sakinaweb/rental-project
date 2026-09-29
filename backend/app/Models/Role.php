<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const ADMIN = 'admin';

    public const STAFF = 'staff';

    public const CUSTOMER = 'customer';

    public const PARTNER = 'partner';

    public const INVESTOR = 'investor';

    public const ALL = [self::ADMIN, self::STAFF, self::CUSTOMER, self::PARTNER, self::INVESTOR];

    protected $fillable = ['name'];

    public static function idFor(string $name): int
    {
        return static::where('name', $name)->valueOrFail('id');
    }
}
