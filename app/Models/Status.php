<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table = 'status';

    protected $fillable = ['description', 'created_by', 'updated_by'];

    private static array $cache = [];

    public static function label(int $id): string
    {
        if (!array_key_exists($id, self::$cache)) {
            self::$cache[$id] = static::find($id)?->description ?? "Estado $id";
        }
        return self::$cache[$id];
    }

    public static function allOptions(): array
    {
        return static::where('id', '>', 0)->orderBy('id')->pluck('description', 'id')->toArray();
    }
}
