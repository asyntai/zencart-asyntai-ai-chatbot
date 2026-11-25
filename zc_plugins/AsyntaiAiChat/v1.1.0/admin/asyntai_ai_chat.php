<?php
/**
 * Asyntai AI Chat - Tools > Asyntai AI Chat.
 *
 * Connects the shop to an Asyntai account and lets the owner choose whether
 * the chatbot may read the shop. The shop server never contacts Asyntai: the
 * admin's own browser sends the connection request when the admin clicks
 * "Connect", and opens the Asyntai sign-in window. This page only stores the
 * result.
 *
 * Every change is a POST with the admin session's security token.
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

require 'includes/application_top.php';
require_once __DIR__ . '/../catalog/includes/classes/asyntai_ai_chat_state.php';

$asyntaiOrigin = 'https://asyntai.com';
if (defined('ASYNTAI_ORIGIN')) {
    $asyntaiOrigin = ASYNTAI_ORIGIN;
} elseif (getenv('ASYNTAI_CHATBOT_ORIGIN')) {
    $asyntaiOrigin = getenv('ASYNTAI_CHATBOT_ORIGIN');
}
$asyntaiOrigin = rtrim($asyntaiOrigin, '/');
if (preg_match('~^https?://[A-Za-z0-9.-]+(:[0-9]+)?$~', $asyntaiOrigin) !== 1) {
    $asyntaiOrigin = 'https://asyntai.com';
}

$catalogBase = ((defined('ENABLE_SSL_CATALOG') && ENABLE_SSL_CATALOG === 'true') ? HTTPS_CATALOG_SERVER : HTTP_CATALOG_SERVER)
    . DIR_WS_CATALOG;

/**
 * Save one configuration value of this plugin.
 */
function asyntai_ai_chat_config($key, $value)
{
    global $db;
    $db->Execute(
        "UPDATE " . TABLE_CONFIGURATION . "
            SET configuration_value = '" . zen_db_input($value) . "', last_modified = now()
          WHERE configuration_key = '" . zen_db_input($key) . "'"
    );
}

function asyntai_ai_chat_json(array $body)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_SESSION['securityToken'], $_POST['securityToken'])
        || !is_string($_POST['securityToken'])
        || !hash_equals((string)$_SESSION['securityToken'], $_POST['securityToken'])) {
        asyntai_ai_chat_json(['ok' => false, 'error' => 'security']);
    }

    $action = (string)$_POST['action'];

    if ($action === 'prepare') {
        $share = isset($_POST['share']) && $_POST['share'] === '1';
        $state = 'zencart_' . AsyntaiAiChatState::randomHex(16);
        AsyntaiAiChatState::set('pending_state', $state);
        $stage = null;
        if ($share) {
            $token = AsyntaiAiChatState::randomHex(24);
            AsyntaiAiChatState::set('pending_token', $token);
            // Asyntai reads the feed as soon as the owner signs in, before
            // "finish" runs, so the feed must already answer.
            AsyntaiAiChatState::set('share', '1');
            $stage = [
                'state' => $state,
                'platform' => 'zencart',
                'feed_protocol' => 'store_feed_v1',
                'product' => 'chat',
                'site_url' => rtrim($catalogBase, '/'),
                'feed_url' => $catalogBase . 'index.php?main_page=index&asyntai_feed=products',
                'feed_token' => $token,
            ];
        } else {
            AsyntaiAiChatState::remove('pending_token');
        }
        asyntai_ai_chat_json(['ok' => true, 'state' => $state, 'stage' => $stage]);
    }

    if ($action === 'finish') {
        $state = isset($_POST['state']) ? (string)$_POST['state'] : '';
        $siteId = isset($_POST['site_id']) ? trim((string)$_POST['site_id']) : '';
        $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $pending = AsyntaiAiChatState::get('pending_state');
        if ($pending === '' || !hash_equals($pending, $state)) {
            asyntai_ai_chat_json(['ok' => false, 'error' => 'state']);
        }
        if (preg_match('/^[A-Za-z0-9_-]{4,64}$/', $siteId) !== 1) {
            asyntai_ai_chat_json(['ok' => false, 'error' => 'site_id']);
        }
        asyntai_ai_chat_config('ASYNTAI_WIDGET_ID', $siteId);
        asyntai_ai_chat_config('ASYNTAI_STATUS', 'True');
        AsyntaiAiChatState::set('account_email', filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '');

        $token = AsyntaiAiChatState::get('pending_token');
        if ($token !== '') {
            AsyntaiAiChatState::set('feed_token', $token);
            AsyntaiAiChatState::set('share', '1');
        } else {
            AsyntaiAiChatState::remove('feed_token');
            AsyntaiAiChatState::set('share', '0');
        }
        AsyntaiAiChatState::remove('pending_token');
        AsyntaiAiChatState::remove('pending_state');
        asyntai_ai_chat_json(['ok' => true]);
    }

    if ($action === 'share') {
        $on = isset($_POST['share']) && $_POST['share'] === '1';
        if ($on) {
            if (AsyntaiAiChatState::get('feed_token') === '') {
                // Asyntai learns the feed only through a connect.
                asyntai_ai_chat_json(['ok' => false, 'needs_connect' => true]);
            }
            AsyntaiAiChatState::set('share', '1');
        } else {
            // The feed answers 403 from now on. Asyntai then stops reading it,
            // and switching on again needs a new connect.
            AsyntaiAiChatState::set('share', '0');
            AsyntaiAiChatState::remove('feed_token');
            AsyntaiAiChatState::remove('pending_token');
        }
        asyntai_ai_chat_json(['ok' => true]);
    }

    if ($action === 'disconnect') {
        foreach (['feed_token', 'pending_token', 'pending_state', 'account_email'] as $key) {
            AsyntaiAiChatState::remove($key);
        }
        AsyntaiAiChatState::set('share', '0');
        asyntai_ai_chat_config('ASYNTAI_WIDGET_ID', '');
        asyntai_ai_chat_config('ASYNTAI_STATUS', 'False');
        asyntai_ai_chat_json(['ok' => true]);
    }

    asyntai_ai_chat_json(['ok' => false, 'error' => 'action']);
}

$widgetId = defined('ASYNTAI_WIDGET_ID') ? trim(ASYNTAI_WIDGET_ID) : '';
$connected = $widgetId !== '' && defined('ASYNTAI_STATUS') && ASYNTAI_STATUS === 'True';
$sharing = $connected && AsyntaiAiChatState::sharingOn() && AsyntaiAiChatState::get('feed_token') !== '';
$accountEmail = AsyntaiAiChatState::get('account_email');

$settingsLink = '';
$group = $db->Execute(
    "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . "
      WHERE configuration_group_title = 'Asyntai AI Chat' LIMIT 1"
);
if (!$group->EOF) {
    $settingsLink = zen_href_link(FILENAME_CONFIGURATION, 'gID=' . (int)$group->fields['configuration_group_id']);
}

$jsConfig = [
    'origin' => $asyntaiOrigin,
    'postUrl' => zen_href_link(FILENAME_ASYNTAI_AI_CHAT, '', 'SSL'),
    'token' => $_SESSION['securityToken'],
    'widgetId' => $connected ? $widgetId : '',
    'sharing' => $sharing,
    'text' => [
        'wait' => ASYNTAI_CONNECT_WAIT,
        'blocked' => ASYNTAI_CONNECT_BLOCKED,
        'failed' => ASYNTAI_CONNECT_FAILED,
        'timeout' => ASYNTAI_CONNECT_TIMEOUT,
        'confirm' => ASYNTAI_DISCONNECT_CONFIRM,
        'count' => ASYNTAI_SHARE_COUNT,
        'pending' => ASYNTAI_SHARE_PENDING,
        'error' => ASYNTAI_SHARE_ERROR,
        'saved' => ASYNTAI_SAVED,
    ],
];
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
<style>
  .asyntai-box { max-width: 760px; margin: 20px 0; padding: 20px 24px; background: #fff; border: 1px solid #ddd; border-radius: 6px; }
  .asyntai-box p { margin: 0 0 12px; line-height: 1.5; }
  .asyntai-status { font-weight: bold; }
  .asyntai-muted { color: #666; }
  .asyntai-row { margin: 16px 0; }
  .asyntai-row label { font-weight: bold; }
  .asyntai-msg { min-height: 1.5em; margin-top: 12px; }
  .asyntai-btn { display: inline-block; margin: 4px 8px 4px 0; padding: 8px 16px; border: 1px solid #111; border-radius: 4px; background: #111; color: #fff; cursor: pointer; text-decoration: none; }
  .asyntai-btn.secondary { background: #fff; color: #111; }
  .asyntai-btn:hover { opacity: .85; color: inherit; text-decoration: none; }
  .asyntai-btn[disabled] { opacity: .5; cursor: default; }
</style>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<div class="container-fluid">
  <h1 class="pageHeading"><?php echo ASYNTAI_HEADING; ?></h1>
  <div class="asyntai-box">
    <p><?php echo ASYNTAI_INTRO; ?></p>

    <p class="asyntai-status" id="asyntai-status">
      <?php if ($connected) { ?>
        <?php echo sprintf(ASYNTAI_STATUS_CONNECTED, zen_output_string_protected($widgetId)); ?>
        <?php if ($accountEmail !== '') { ?><br><span class="asyntai-muted"><?php echo sprintf(ASYNTAI_STATUS_ACCOUNT, zen_output_string_protected($accountEmail)); ?></span><?php } ?>
      <?php } else { ?>
        <?php echo ASYNTAI_STATUS_NOT_CONNECTED; ?>
      <?php } ?>
    </p>

    <div class="asyntai-row">
      <label><input type="checkbox" id="asyntai-share" <?php echo ($sharing || !$connected) ? 'checked' : ''; ?>> <?php echo ASYNTAI_SHARE_LABEL; ?></label>
      <p class="asyntai-muted"><?php echo ASYNTAI_SHARE_HELP; ?></p>
      <p class="asyntai-muted" id="asyntai-sync"></p>
    </div>

    <div class="asyntai-row">
      <button type="button" class="asyntai-btn" id="asyntai-connect"><?php echo $connected ? ASYNTAI_BUTTON_RECONNECT : ASYNTAI_BUTTON_CONNECT; ?></button>
      <?php if ($connected) { ?>
        <a class="asyntai-btn secondary" href="<?php echo zen_output_string_protected($asyntaiOrigin . '/dashboard/'); ?>" target="_blank" rel="noopener"><?php echo ASYNTAI_BUTTON_DASHBOARD; ?></a>
        <button type="button" class="asyntai-btn secondary" id="asyntai-disconnect"><?php echo ASYNTAI_BUTTON_DISCONNECT; ?></button>
      <?php } ?>
      <?php if ($settingsLink !== '') { ?>
        <a class="asyntai-btn secondary" href="<?php echo $settingsLink; ?>"><?php echo ASYNTAI_BUTTON_SETTINGS; ?></a>
      <?php } ?>
      <p class="asyntai-muted"><?php echo ASYNTAI_CONNECT_NOTE; ?></p>
      <p class="asyntai-msg" id="asyntai-msg" role="status"></p>
    </div>
  </div>
</div>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<script>
(function () {
  var cfg = <?php echo json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  var msg = document.getElementById('asyntai-msg');
  var share = document.getElementById('asyntai-share');
  var connectBtn = document.getElementById('asyntai-connect');
  var disconnectBtn = document.getElementById('asyntai-disconnect');
  var sync = document.getElementById('asyntai-sync');
  var connected = cfg.widgetId !== '';

  function say(text) { msg.textContent = text || ''; }

  function post(action, fields) {
    var body = new FormData();
    body.append('action', action);
    body.append('securityToken', cfg.token);
    Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
    return fetch(cfg.postUrl, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function poll(state, win, started) {
    if (Date.now() - started > 10 * 60 * 1000) {
      connectBtn.disabled = false;
      if (connected) { share.checked = cfg.sharing; }
      say(cfg.text.timeout);
      return;
    }
    fetch(cfg.origin + '/api/v1/wp-search/connect-status/?state=' + encodeURIComponent(state))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ready && data.data && data.data.site_id) {
          return post('finish', {
            state: state,
            site_id: data.data.site_id,
            email: data.data.account_email || ''
          }).then(function (res) {
            if (!res.ok) { throw new Error('finish'); }
            try { if (win && !win.closed) { win.close(); } } catch (e) {}
            window.location.reload();
          });
        }
        setTimeout(function () { poll(state, win, started); }, 2500);
      })
      .catch(function () { setTimeout(function () { poll(state, win, started); }, 4000); });
  }

  connectBtn.addEventListener('click', startConnect);

  function startConnect() {
    // Open the window inside the click, or the browser blocks it.
    var win = window.open('about:blank', 'asyntai_connect', 'width=520,height=760');
    if (!win) { say(cfg.text.blocked); return; }
    connectBtn.disabled = true;
    say(cfg.text.wait);
    post('prepare', { share: share.checked ? '1' : '0' })
      .then(function (res) {
        if (!res.ok) { throw new Error('prepare'); }
        var staged = Promise.resolve();
        if (res.stage) {
          // text/plain keeps this a simple request; the body is JSON.
          staged = fetch(cfg.origin + '/api/v1/wp-search/stage/', {
            method: 'POST',
            headers: { 'Content-Type': 'text/plain' },
            body: JSON.stringify(res.stage)
          }).then(function (r) { return r.json(); })
            .then(function (s) { if (!s.ok) { throw new Error('stage'); } });
        }
        return staged.then(function () {
          win.location.href = cfg.origin + '/wp-auth?platform=zencart&state=' + encodeURIComponent(res.state);
          poll(res.state, win, Date.now());
        });
      })
      .catch(function () {
        try { win.close(); } catch (e) {}
        connectBtn.disabled = false;
        if (connected) { share.checked = cfg.sharing; }
        say(cfg.text.failed);
      });
  }

  share.addEventListener('change', function () {
    if (!connected) { return; }
    // Asyntai learns the shop feed only through a connect.
    if (share.checked && !cfg.sharing) {
      startConnect();
      return;
    }
    post('share', { share: share.checked ? '1' : '0' }).then(function (res) {
      if (res.needs_connect) {
        say(cfg.text.failed);
        return;
      }
      cfg.sharing = share.checked;
      say(res.ok ? cfg.text.saved : cfg.text.failed);
      if (res.ok) { showSync(); }
    }).catch(function () { say(cfg.text.failed); });
  });

  if (disconnectBtn) {
    disconnectBtn.addEventListener('click', function () {
      if (!window.confirm(cfg.text.confirm)) { return; }
      post('disconnect', {}).then(function () { window.location.reload(); })
        .catch(function () { say(cfg.text.failed); });
    });
  }

  function showSync() {
    if (!connected || !share.checked) { sync.textContent = ''; return; }
    fetch(cfg.origin + '/api/v1/search-widget/status/?widget_id=' + encodeURIComponent(cfg.widgetId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var s = data && data.sync;
        if (!s) { sync.textContent = ''; return; }
        if (s.status === 'failed' && s.error) {
          sync.textContent = cfg.text.error.replace('%s', s.error);
        } else if (s.count > 0) {
          sync.textContent = cfg.text.count.replace('%s', s.count);
        } else {
          sync.textContent = cfg.text.pending;
        }
      })
      .catch(function () { sync.textContent = ''; });
  }
  showSync();
})();
</script>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
