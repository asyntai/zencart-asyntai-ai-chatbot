<?php
/**
 * Asyntai AI Chat - private plugin state.
 *
 * Values that must never be printed into a page (the feed signing token, the
 * handshake state) live in the plugin's own table, not in the configuration
 * table, so the Configuration screen never shows them and a settings save
 * cannot change them.
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class AsyntaiAiChatState
{
    const TABLE = 'asyntai_ai_chat_state';

    /**
     * Full table name with the store's table prefix.
     */
    public static function table()
    {
        return (defined('DB_PREFIX') ? DB_PREFIX : '') . self::TABLE;
    }

    /**
     * The SQL that creates the table. Used by the installer.
     */
    public static function createSql()
    {
        return "CREATE TABLE IF NOT EXISTS " . self::table() . " (
                    state_key VARCHAR(64) NOT NULL,
                    state_value TEXT NOT NULL,
                    PRIMARY KEY (state_key)
                )";
    }

    public static function get($key, $default = '')
    {
        global $db;
        $result = $db->Execute(
            "SELECT state_value FROM " . self::table() .
            " WHERE state_key = '" . zen_db_input($key) . "' LIMIT 1"
        );
        if ($result === false || $result->EOF) {
            return $default;
        }
        return (string)$result->fields['state_value'];
    }

    public static function set($key, $value)
    {
        global $db;
        $db->Execute(
            "REPLACE INTO " . self::table() . " (state_key, state_value)
             VALUES ('" . zen_db_input($key) . "', '" . zen_db_input((string)$value) . "')"
        );
    }

    public static function remove($key)
    {
        global $db;
        $db->Execute(
            "DELETE FROM " . self::table() . " WHERE state_key = '" . zen_db_input($key) . "'"
        );
    }

    /**
     * Whether the owner allows the chatbot to read the shop.
     */
    public static function sharingOn()
    {
        return self::get('share', '0') === '1';
    }

    /**
     * Tokens the feed accepts: the live one, and the one of a sign-in that
     * is still running (Asyntai reads the feed the moment the owner signs in).
     *
     * @return string[]
     */
    public static function acceptedTokens()
    {
        $tokens = [];
        foreach (['feed_token', 'pending_token'] as $key) {
            $value = self::get($key);
            if (preg_match('/^[a-f0-9]{48}$/', $value) === 1) {
                $tokens[] = $value;
            }
        }
        return $tokens;
    }

    /**
     * A new random hex string.
     */
    public static function randomHex($bytes)
    {
        return bin2hex(random_bytes($bytes));
    }
}
