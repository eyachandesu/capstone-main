<?php
require_once __DIR__ . "/../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class JwtHelper
{
    private static $secret_key = null;
    private static $algorithm = "HS256";

    /**
     * Load environment variables once
     */
    private static function init()
    {
        // Only load if the key hasn't been set yet
        if (self::$secret_key !== null) {
            return;
        }

        try {
            // Load .env from the root directory
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); 
        $dotenv->safeLoad();

        self::$secret_key = $_ENV["SECRET_KEY"] ?? getenv("SECRET_KEY");

        if (empty(self::$secret_key)) {
            // If .env is missing, the JWT will always fail. 
            // This is a common reason for the "loop".
            throw new Exception("SECRET_KEY missing.");
            }
        } catch (Exception $e) {
            // Log the error and stop execution or handle it gracefully
            error_log("JWT Init Error: " . $e->getMessage());
            throw new Exception("Internal Server Error: Security configuration missing.");
        }
    }

    /**
     * @param array $payload Data to encode
     * @return string
     */
  public static function generateToken(array $userData)
{
    self::init();
    $payload = [
        'iat' => time(),
        'exp' => time() + (60 * 60),
        'data' => $userData // Nest the data here so middleware works
    ];

    return JWT::encode($payload, self::$secret_key, self::$algorithm);
}

    /**
     * @param string $token
     * @return object|null
     */
    public static function verifyToken(string $token)
    {
        try {
            self::init();
            return JWT::decode($token, new Key(self::$secret_key, self::$algorithm));
        } catch (Exception $e) {
            // This will catch expired tokens, signature mismatches, etc.
            error_log("JWT Verification Failed: " . $e->getMessage());
            return null;
        }
    }
}