<?php
/**
 * Asyntai AI Chat - the shop feed.
 *
 * When the store owner switches on "Let the chatbot read this shop", Asyntai
 * reads the shop's enabled products, categories and information pages from
 * this feed, so the chatbot can answer from them. The feed sends only what
 * an anonymous visitor can already see in the shop.
 *
 * The store never contacts Asyntai from here. Asyntai asks; the feed answers
 * only requests that are signed with the secret made when the owner connected,
 * and answers 403 to everything else.
 *
 *   index.php?main_page=index&asyntai_feed=products&page=&limit=&ids=&ts=&sig=
 *   index.php?main_page=index&asyntai_feed=pages&page=&limit=&ids=&ts=&sig=
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

require_once __DIR__ . '/asyntai_ai_chat_state.php';

class AsyntaiAiChatFeed
{
    const CLOCK_SKEW = 300;
    const PAGE_SIZE = 100;
    const MAX_PAGE_SIZE = 250;
    const MAX_IDS = 50;

    /**
     * Answer the request and stop.
     */
    public function run()
    {
        // The store's request sanitizer rewrites some query values, and the
        // signature is over the values Asyntai sent, so read them raw.
        $query = [];
        parse_str(isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '', $query);
        $read = function ($name) use ($query) {
            return (isset($query[$name]) && is_string($query[$name])) ? $query[$name] : '';
        };

        $which = $read('asyntai_feed') === 'pages' ? 'pages' : 'products';
        $page = $read('page');
        $limit = $read('limit');
        $ids = $read('ids');
        $ts = $read('ts');
        $sig = strtolower($read('sig'));

        $tokens = AsyntaiAiChatState::sharingOn() ? AsyntaiAiChatState::acceptedTokens() : [];
        if (empty($tokens)) {
            $this->send(403, ['ok' => false, 'error' => 'Sharing is switched off on this site.']);
        }
        if ($ts === '' || !ctype_digit($ts)) {
            $this->send(403, ['ok' => false, 'error' => 'Missing timestamp.']);
        }
        if (abs(time() - (int)$ts) > self::CLOCK_SKEW) {
            $this->send(403, ['ok' => false, 'error' => 'The request has expired.']);
        }
        $signed = 'page=' . $page . '&limit=' . $limit . '&ids=' . $ids . '&ts=' . $ts;
        $valid = false;
        foreach ($tokens as $token) {
            if ($sig !== '' && hash_equals(hash_hmac('sha256', $signed, $token), $sig)) {
                $valid = true;
            }
        }
        if (!$valid) {
            $this->send(403, ['ok' => false, 'error' => 'Bad signature.']);
        }

        $pageNo = max(1, (int)$page);
        $size = (int)$limit;
        if ($size < 1 || $size > self::MAX_PAGE_SIZE) {
            $size = self::PAGE_SIZE;
        }

        if ($which === 'pages') {
            $all = $this->pages();
            $this->send(200, [
                'ok' => true,
                'page' => $pageNo,
                'pages' => (int)ceil(count($all) / $size),
                'total' => count($all),
                'items' => array_values(array_slice($all, ($pageNo - 1) * $size, $size)),
            ]);
        }

        $store = $this->store();

        if ($ids !== '') {
            $wanted = [];
            foreach (explode(',', $ids) as $id) {
                if (ctype_digit(trim($id))) {
                    $wanted[] = (int)trim($id);
                }
            }
            $wanted = array_slice(array_unique($wanted), 0, self::MAX_IDS);
            $found = empty($wanted) ? [] : $this->products('p.products_id IN (' . implode(',', $wanted) . ')', 0, self::MAX_IDS);
            $this->send(200, [
                'ok' => true, 'store' => $store, 'page' => 1, 'pages' => 1,
                'total' => count($found), 'products' => $found,
            ]);
        }

        $total = $this->countProducts();
        $answer = [
            'ok' => true,
            'store' => $store,
            'page' => $pageNo,
            'pages' => (int)ceil($total / $size),
            'total' => $total,
            'products' => $this->products('', ($pageNo - 1) * $size, $size),
        ];
        if ($pageNo === 1) {
            $answer['categories'] = $this->categories();
        }
        $this->send(200, $answer);
    }

    protected function send($status, array $body)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit();
    }

    // ------------------------------------------------------------------
    // Shop data. Only what a logged-out visitor sees.
    // ------------------------------------------------------------------

    protected function languageId()
    {
        return isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
    }

    protected function baseUrl()
    {
        return rtrim(HTTP_SERVER, '/') . DIR_WS_CATALOG;
    }

    protected function link($page, $params)
    {
        // zen_href_link() escapes "&" for HTML; the feed is JSON.
        return str_replace('&amp;', '&', zen_href_link($page, $params, 'NONSSL', false));
    }

    protected function store()
    {
        $base = $this->baseUrl();
        $info = [
            'name' => defined('STORE_NAME') ? $this->text(STORE_NAME) : '',
            'url' => $base,
            'currency' => defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '',
            'version' => defined('PROJECT_VERSION_MAJOR') ? PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR : '',
            'pages_url' => $base . 'index.php?main_page=index&asyntai_feed=pages',
        ];
        return $info;
    }

    protected function countProducts()
    {
        global $db;
        $result = $db->Execute(
            "SELECT COUNT(*) AS total FROM " . TABLE_PRODUCTS . " p
              WHERE p.products_status = 1"
        );
        return $result->EOF ? 0 : (int)$result->fields['total'];
    }

    /**
     * @param string $where extra condition, already safe
     */
    protected function products($where, $offset, $count)
    {
        global $db, $currencies;

        $sql = "SELECT p.products_id, p.products_model, p.products_image, p.products_quantity,
                       p.products_tax_class_id, p.master_categories_id, p.product_is_call,
                       pd.products_name, pd.products_description, m.manufacturers_name
                  FROM " . TABLE_PRODUCTS . " p
                  JOIN " . TABLE_PRODUCTS_DESCRIPTION . " pd
                    ON pd.products_id = p.products_id AND pd.language_id = " . $this->languageId() . "
             LEFT JOIN " . TABLE_MANUFACTURERS . " m
                    ON m.manufacturers_id = p.manufacturers_id
                 WHERE p.products_status = 1" . ($where !== '' ? " AND " . $where : '') . "
              ORDER BY p.products_id
                 LIMIT " . (int)$offset . ", " . (int)$count;
        $rows = $db->Execute($sql);

        $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '';
        $decimals = 2;
        if (isset($currencies->currencies[$currency]['decimal_places'])) {
            $decimals = (int)$currencies->currencies[$currency]['decimal_places'];
        }
        $noImage = defined('PRODUCTS_IMAGE_NO_IMAGE') ? PRODUCTS_IMAGE_NO_IMAGE : '';
        $stockCheck = defined('STOCK_CHECK') && STOCK_CHECK === 'true';
        $categoryNames = [];

        $products = [];
        foreach ($rows as $row) {
            $id = (int)$row['products_id'];
            $product = [
                'id' => $id,
                'name' => $this->text($row['products_name']),
                'url' => $this->link(zen_get_info_page($id), 'products_id=' . $id),
            ];

            $description = $this->text($row['products_description']);
            if ($description !== '') {
                $product['description'] = $description;
            }

            if ((int)$row['product_is_call'] !== 1) {
                $rate = zen_get_tax_rate($row['products_tax_class_id']);
                $base = (float)zen_get_products_base_price($id);
                $actual = (float)zen_get_products_actual_price($id);
                if ($base > 0) {
                    $product['price'] = round(zen_add_tax($base, $rate), $decimals);
                    if ($actual > 0 && $actual < $base) {
                        $product['special'] = round(zen_add_tax($actual, $rate), $decimals);
                    }
                    $product['currency'] = $currency;
                }
            }

            $image = (string)$row['products_image'];
            if ($image !== '' && $image !== $noImage) {
                $product['image_url'] = $this->baseUrl() . DIR_WS_IMAGES . $image;
            }
            if ($stockCheck) {
                $product['quantity'] = (int)$row['products_quantity'];
            }
            if ((string)$row['manufacturers_name'] !== '') {
                $product['manufacturer'] = $this->text($row['manufacturers_name']);
            }
            if ((string)$row['products_model'] !== '') {
                $product['model'] = $this->text($row['products_model']);
            }

            $categoryId = (int)$row['master_categories_id'];
            if ($categoryId > 0) {
                if (!isset($categoryNames[$categoryId])) {
                    $categoryNames[$categoryId] = $this->text(zen_get_category_name($categoryId, $this->languageId()));
                }
                if ($categoryNames[$categoryId] !== '') {
                    $product['categories'] = [$categoryNames[$categoryId]];
                }
            }

            $products[] = $product;
        }
        return $products;
    }

    protected function categories()
    {
        global $db;
        $counts = [];
        $rows = $db->Execute(
            "SELECT p2c.categories_id, COUNT(*) AS total
               FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c
               JOIN " . TABLE_PRODUCTS . " p ON p.products_id = p2c.products_id
              WHERE p.products_status = 1
           GROUP BY p2c.categories_id"
        );
        foreach ($rows as $row) {
            $counts[(int)$row['categories_id']] = (int)$row['total'];
        }

        $categories = [];
        $rows = $db->Execute(
            "SELECT c.categories_id, cd.categories_name, cd.categories_description
               FROM " . TABLE_CATEGORIES . " c
               JOIN " . TABLE_CATEGORIES_DESCRIPTION . " cd
                 ON cd.categories_id = c.categories_id AND cd.language_id = " . $this->languageId() . "
              WHERE c.categories_status = 1
           ORDER BY c.sort_order, cd.categories_name"
        );
        foreach ($rows as $row) {
            $id = (int)$row['categories_id'];
            $category = [
                'name' => $this->text($row['categories_name']),
                'url' => $this->link(FILENAME_DEFAULT, 'cPath=' . zen_get_generated_category_path_rev($id)),
                'products' => isset($counts[$id]) ? $counts[$id] : 0,
            ];
            $description = $this->text($row['categories_description']);
            if ($description !== '') {
                $category['description'] = $description;
            }
            $categories[] = $category;
        }
        return $categories;
    }

    /**
     * EZ-Pages that are shown somewhere in the shop, plus the shop's own
     * information pages (shipping, privacy, conditions...) that are switched on.
     */
    protected function pages()
    {
        global $db;
        $items = [];

        $rows = $db->Execute(
            "SELECT e.pages_id, ec.pages_title, ec.pages_html_text
               FROM " . TABLE_EZPAGES . " e
               JOIN " . TABLE_EZPAGES_CONTENT . " ec
                 ON ec.pages_id = e.pages_id AND ec.languages_id = " . $this->languageId() . "
              WHERE e.alt_url = '' AND e.alt_url_external = ''
                AND (e.status_toc > 0 OR e.status_header > 0 OR e.status_sidebox > 0
                     OR e.status_footer > 0 OR e.status_visible > 0)
           ORDER BY e.pages_id"
        );
        foreach ($rows as $row) {
            $title = $this->text($row['pages_title']);
            $content = $this->text($row['pages_html_text']);
            if ($title === '' || $content === '') {
                continue;
            }
            $items[] = [
                'type' => 'page',
                'title' => $title,
                'url' => $this->link(FILENAME_EZPAGES, 'id=' . (int)$row['pages_id']),
                'content' => $content,
            ];
        }

        // main_page => [define file, on/off setting, title]
        $defined = [
            'index' => ['FILENAME_DEFINE_MAIN_PAGE', 'DEFINE_MAIN_PAGE_STATUS', 'STORE_NAME'],
            'about_us' => ['FILENAME_DEFINE_ABOUT_US', 'DEFINE_ABOUT_US_STATUS', 'BOX_INFORMATION_ABOUT_US'],
            'shippinginfo' => ['FILENAME_DEFINE_SHIPPINGINFO', 'DEFINE_SHIPPINGINFO_STATUS', 'BOX_INFORMATION_SHIPPING'],
            'privacy' => ['FILENAME_DEFINE_PRIVACY', 'DEFINE_PRIVACY_STATUS', 'BOX_INFORMATION_PRIVACY'],
            'conditions' => ['FILENAME_DEFINE_CONDITIONS', 'DEFINE_CONDITIONS_STATUS', 'BOX_INFORMATION_CONDITIONS'],
            'accessibility' => ['FILENAME_DEFINE_ACCESSIBILITY', 'DEFINE_ACCESSIBILITY_STATUS', 'BOX_INFORMATION_ACCESSIBILITY'],
            'contact_us' => ['FILENAME_DEFINE_CONTACT_US', 'DEFINE_CONTACT_US_STATUS', 'BOX_INFORMATION_CONTACT'],
            'page_2' => ['FILENAME_DEFINE_PAGE_2', 'DEFINE_PAGE_2_STATUS', 'BOX_INFORMATION_PAGE_2'],
            'page_3' => ['FILENAME_DEFINE_PAGE_3', 'DEFINE_PAGE_3_STATUS', 'BOX_INFORMATION_PAGE_3'],
            'page_4' => ['FILENAME_DEFINE_PAGE_4', 'DEFINE_PAGE_4_STATUS', 'BOX_INFORMATION_PAGE_4'],
        ];
        foreach ($defined as $mainPage => $info) {
            list($fileConst, $statusConst, $titleConst) = $info;
            // 1 = page and text on, 2 = text on without a menu link.
            if (!defined($fileConst) || !defined($statusConst)
                || !in_array((string)constant($statusConst), ['1', '2'], true)) {
                continue;
            }
            if (!is_dir(DIR_WS_MODULES . 'pages/' . $mainPage)) {
                continue;
            }
            $content = $this->text($this->defineText(constant($fileConst)));
            if ($content === '') {
                continue;
            }
            $title = defined($titleConst) ? $this->text(constant($titleConst)) : $mainPage;
            $items[] = [
                'type' => 'page',
                'title' => $title,
                'url' => $mainPage === 'index' ? $this->baseUrl() : $this->link($mainPage, ''),
                'content' => $content,
            ];
        }

        return $items;
    }

    /**
     * The text of a "define page", read the same way the shop shows it.
     */
    protected function defineText($defineFile)
    {
        $language = isset($_SESSION['language']) ? $_SESSION['language'] : 'english';
        $path = zen_get_file_directory(DIR_WS_LANGUAGES . $language . '/html_includes/', $defineFile, 'false');
        if (!is_string($path) || $path === '' || !is_file($path)) {
            return '';
        }
        ob_start();
        include $path;
        return (string)ob_get_clean();
    }

    /**
     * HTML to plain text.
     */
    protected function text($html)
    {
        $text = (string)$html;
        $text = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $text);
        $text = preg_replace('~<br\s*/?>|</(p|div|li|h[1-6]|tr|table|ul|ol)>~i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, defined('CHARSET') ? CHARSET : 'UTF-8');
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        return trim($text);
    }
}
