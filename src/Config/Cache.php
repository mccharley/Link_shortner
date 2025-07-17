<?php

declare(strict_types=1);

namespace LinkShortener\Config;

use Predis\Client;
use RuntimeException;

class Cache
{
    private static ?Client $redis = null;
    
    public static function getRedis(): Client
    {
        if (self::$redis === null) {
            self::$redis = self::createRedisConnection();
        }
        
        return self::$redis;
    }
    
    private static function createRedisConnection(): Client
    {
        $config = [
            'scheme' => 'tcp',
            'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
            'database' => (int)($_ENV['REDIS_DB'] ?? 0),
        ];
        
        if (!empty($_ENV['REDIS_PASSWORD'])) {
            $config['password'] = $_ENV['REDIS_PASSWORD'];
        }
        
        try {
            $redis = new Client($config);
            $redis->ping();
            return $redis;
        } catch (\Exception $e) {
            throw new RuntimeException("Redis connection failed: " . $e->getMessage());
        }
    }
    
    public static function get(string $key): ?string
    {
        try {
            $value = self::getRedis()->get($key);
            return $value !== null ? (string)$value : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    public static function set(string $key, string $value, int $ttl = 3600): bool
    {
        try {
            self::getRedis()->setex($key, $ttl, $value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public static function delete(string $key): bool
    {
        try {
            self::getRedis()->del($key);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public static function increment(string $key, int $by = 1): int
    {
        try {
            return (int)self::getRedis()->incrby($key, $by);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    public static function expire(string $key, int $ttl): bool
    {
        try {
            return (bool)self::getRedis()->expire($key, $ttl);
        } catch (\Exception $e) {
            return false;
        }
    }
}