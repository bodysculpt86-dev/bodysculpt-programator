<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * Telegram Bot API alert sender.
 * ---------------------------------------------------------------------------- */

/**
 * Class Telegram
 *
 * Sends short operational alerts to a Telegram chat via the Bot API.
 *
 * Every other sender in this application reports its outcome as a result array,
 * and this one is no different: send() never throws and never returns a bare
 * boolean, because the caller has to be able to tell "the alert went out" from
 * "there is no chat configured" — the second is the state that would otherwise
 * make a silent alert channel look like a working one.
 */
class Telegram
{
    /**
     * @var string Telegram Bot API base URL.
     */
    private const API_BASE = 'https://api.telegram.org';

    /**
     * @var CI_Controller|object CodeIgniter instance.
     */
    protected $CI;

    /**
     * @var string|null Bot token, from the environment.
     */
    protected ?string $botToken = null;

    /**
     * @var string|null Target chat ID, from the environment.
     */
    protected ?string $chatId = null;

    /**
     * Telegram constructor.
     */
    public function __construct()
    {
        $this->CI = &get_instance();

        $this->botToken = $this->readEnvOrConfig('TELEGRAM_BOT_TOKEN');
        $this->chatId = $this->readEnvOrConfig('TELEGRAM_CHAT_ID');
    }

    /**
     * Whether this instance can send at all.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Send one message to the configured chat.
     *
     * @param string $message Message text (plain text, no Markdown parsing).
     *
     * @return array ['sent' => bool, 'reason' => string|null, 'detail' => string|null]
     *               reason is 'not_configured', 'rejected' or 'network'.
     */
    public function send(string $message): array
    {
        if (!$this->is_configured()) {
            return [
                'sent' => false,
                'reason' => 'not_configured',
                'detail' => 'TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID must both be visible to this process.',
            ];
        }

        $ch = curl_init(self::API_BASE . '/bot' . $this->botToken . '/sendMessage');

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'chat_id' => $this->chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['sent' => false, 'reason' => 'network', 'detail' => 'cURL error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        // Telegram answers 200 with {"ok":true} on success. A token that is wrong
        // or a chat the bot was never added to both answer non-2xx with a
        // `description`, which is the only part worth showing to the operator.
        if ($httpCode < 200 || $httpCode >= 300 || empty($decoded['ok'])) {
            $detail = is_array($decoded) ? ($decoded['description'] ?? $response) : $response;

            return ['sent' => false, 'reason' => 'rejected', 'detail' => (string) $detail];
        }

        return ['sent' => true, 'reason' => null, 'detail' => null];
    }

    /**
     * Read a value from an environment variable or from the Config class.
     *
     * Mirrors the other sender libraries, so a value can come from either place.
     *
     * @param string $name The environment variable / Config constant name.
     *
     * @return string|null
     */
    private function readEnvOrConfig(string $name): ?string
    {
        $value = getenv($name);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (defined("Config::$name")) {
            $value = constant("Config::$name");

            return $value !== '' ? (string) $value : null;
        }

        return null;
    }
}
