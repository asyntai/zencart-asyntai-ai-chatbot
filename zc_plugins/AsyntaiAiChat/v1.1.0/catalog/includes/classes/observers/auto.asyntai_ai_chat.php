<?php
/**
 * Asyntai AI Chat - storefront observer.
 *
 * Writes one <script> tag into the end of the page head. The tag loads the
 * Asyntai chat widget with the store's widget ID. The store never contacts
 * Asyntai from here: the widget talks to Asyntai from the visitor browser.
 *
 * It also answers the signed shop feed (see asyntai_ai_chat_feed.php) when
 * the owner has switched on sharing.
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class zcObserverAsyntaiAiChat extends base
{
    /**
     * The widget ID, already checked. Empty when the widget must not load.
     */
    protected $widgetId = '';

    /**
     * The script URL, already checked.
     */
    protected $scriptUrl = '';

    /**
     * Page names on which the widget must not load.
     *
     * @var string[]
     */
    protected $excludedPages = [];

    public function __construct()
    {
        // A signed read of the shop feed. It answers on the home page address
        // after the shop has finished starting up, and then stops the request.
        if (isset($_GET['asyntai_feed'])) {
            $this->attach($this, ['NOTIFY_HEADER_START_INDEX']);
            return;
        }

        if (!defined('ASYNTAI_STATUS') || ASYNTAI_STATUS !== 'True') {
            return;
        }

        $widget_id = defined('ASYNTAI_WIDGET_ID') ? trim(ASYNTAI_WIDGET_ID) : '';
        if ($widget_id === '' || preg_match('/^[A-Za-z0-9_-]{4,64}$/', $widget_id) !== 1) {
            return;
        }

        $script_url = defined('ASYNTAI_SCRIPT_URL') ? trim(ASYNTAI_SCRIPT_URL) : '';
        if ($script_url === '') {
            $script_url = 'https://widget.asyntai.com/static/js/chat-widget.js';
        }
        if (preg_match('~^https://[A-Za-z0-9._/-]+\.js$~', $script_url) !== 1) {
            return;
        }

        $excluded = defined('ASYNTAI_EXCLUDED_PAGES') ? ASYNTAI_EXCLUDED_PAGES : '';
        $excluded = array_filter(array_map('trim', explode(',', $excluded)));

        $this->widgetId = $widget_id;
        $this->scriptUrl = $script_url;
        $this->excludedPages = $excluded;

        $this->attach($this, ['NOTIFY_HTML_HEAD_END']);
    }

    /**
     * @param object $class
     * @param string $eventID
     * @param string $current_page_base
     */
    protected function update(&$class, $eventID, $current_page_base = '')
    {
        if ($eventID === 'NOTIFY_HEADER_START_INDEX') {
            require_once __DIR__ . '/../asyntai_ai_chat_feed.php';
            $feed = new AsyntaiAiChatFeed();
            $feed->run();
            return;
        }

        if ($current_page_base === '' && isset($_GET['main_page'])) {
            $current_page_base = (string)$_GET['main_page'];
        }
        if (in_array($current_page_base, $this->excludedPages, true)) {
            return;
        }

        echo '<script src="' . htmlspecialchars($this->scriptUrl, ENT_QUOTES) . '"'
            . ' data-asyntai-id="' . htmlspecialchars($this->widgetId, ENT_QUOTES) . '"'
            . ' async></script>' . "\n";
    }
}
