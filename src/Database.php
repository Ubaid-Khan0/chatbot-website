<?php

declare(strict_types=1);

namespace Nexum;

use MongoDB\Client;
use MongoDB\Database as MongoDatabase;

final class Database
{
    private static ?MongoDatabase $database = null;

    public static function connection(): MongoDatabase
    {
        if (self::$database === null) {
            $client = new Client(Config::require('MONGODB_URI'), [], [
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ]);
            self::$database = $client->selectDatabase(Config::get('MONGODB_DATABASE', 'nexum_chatbot'));
        }
        return self::$database;
    }
}
