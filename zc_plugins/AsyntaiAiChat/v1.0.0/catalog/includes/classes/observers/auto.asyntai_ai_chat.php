<?php
/**
 * Asyntai AI Chat - storefront observer.
 *
 * Writes one <script> tag into the end of the page head. The tag loads the
 * Asyntai chat widget with the widget ID that the store owner entered in
 * Configuration > Asyntai AI Chat. Nothing is sent from this file: the store
 * owner supplies the ID, and the widget itself talks to Asyntai from the
 * visitor browser.
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
    protected string $widgetId = '';

    /**
     * The script URL, already checked.
     */
    protected string $scriptUrl = '';

    /**
     * Page names on which the widget must not load.
     *
     * @var string[]
     */
    protected array $excludedPages = [];

    public function __construct()
    {
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
