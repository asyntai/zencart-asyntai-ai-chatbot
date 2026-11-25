# Asyntai AI Chat for Zen Cart

Adds the [Asyntai](https://asyntai.com/) AI chat widget to your Zen Cart storefront.
The widget answers visitor questions about your products, your delivery and your
returns. When it cannot answer, it collects the question and the visitor email for
your team.

- Zen Cart 1.5.8 and later
- Encapsulated plugin. You install it from the Plugin Manager
- No core file change and no template edit. It uses the `NOTIFY_HTML_HEAD_END` notifier
- Licence: GPL v2

## What you need

An Asyntai account. You can create a free one during the connect step.

## Install

1. Download the latest ZIP from [Releases](../../releases), or download this
   repository as a ZIP.
2. Copy the `zc_plugins` folder into your Zen Cart root, next to `index.php`. You
   get `zc_plugins/AsyntaiAiChat/v1.1.0/`.
3. In the admin, open **Modules > Plugin Manager**.
4. Click **Asyntai AI Chat**, then click **Install**.
5. Open **Tools > Asyntai AI Chat** and click **Connect to Asyntai**.
6. Sign in or create a free Asyntai account in the window that opens. The plugin
   fills in your Widget ID and switches the widget on.
7. Open your storefront. The chat button is in the bottom corner.

### Upgrade from v1.0.0

Copy the `v1.1.0` folder next to `v1.0.0`, then click **Upgrade Available** in
**Modules > Plugin Manager**. Your settings and your Widget ID stay as they are.

## Let the chatbot read this shop (optional)

In **Tools > Asyntai AI Chat**, tick **Let the chatbot read this shop**. Asyntai
then reads your enabled products (price, offer price, stock, brand, category,
image), categories, EZ-Pages and information pages (shipping, privacy,
conditions, about us, contact) directly from your shop, so the chatbot's answers
stay up to date. Only what a visitor can already see is shared.

- Asyntai reads `index.php?main_page=index&asyntai_feed=products` (and `=pages`).
  Each request must carry an HMAC-SHA256 signature made with a secret that only
  your shop and Asyntai know, and a timestamp no older than 5 minutes. Anything
  else gets HTTP 403.
- The secret lives in the plugin's own table (`asyntai_ai_chat_state`), never in
  a page or a configuration value.
- Your shop server never contacts Asyntai. When you click **Connect**, your own
  browser sends the connection request and opens the sign-in window.
- Untick the box, or click **Disconnect**, and the address answers 403 at once.

## Settings

| Setting | What it does |
| --- | --- |
| Enable Asyntai AI Chat | Shows or hides the widget. Default `False`. |
| Widget ID | Your Asyntai Widget ID, for example `asyntai_1a2b3c4d5e6f`. **Connect** fills it in, or paste it yourself. Nothing loads without it. |
| Widget script URL | The address of the widget script. Change it only if you host the script yourself. It must start with `https://` and end with `.js`. |
| Pages without the widget | Page names, separated by commas, where the widget must not load. Default: the checkout pages. |

## How it works

The plugin listens for the Zen Cart `NOTIFY_HTML_HEAD_END` notifier. On every
storefront page it writes one line into the page head:

```html
<script
  src="https://widget.asyntai.com/static/js/chat-widget.js"
  data-asyntai-id="YOUR_WIDGET_ID"
  async></script>
```

The browser then loads the widget. The widget runs in the visitor browser and talks
to Asyntai from there.

Before it writes that line, the plugin checks four things:

1. **Enable** is `True`
2. The Widget ID matches `[A-Za-z0-9_-]`, 4 to 64 characters
3. The script URL starts with `https://` and ends with `.js`
4. The current page is not in the exclude list

If any check fails, nothing is written.

## Uninstall

Open **Modules > Plugin Manager**, click **Asyntai AI Chat**, then click
**Un-Install**. The settings, the configuration group, both admin menu entries and
the plugin's table are removed. No store data is touched.

To hide the widget without an uninstall, set **Enable Asyntai AI Chat** to **False**.

## Compatibility

| Item | Value |
| --- | --- |
| Zen Cart | 1.5.8, 2.0.0, 2.1.0, 2.2.0, 2.2.1, 2.2.2 |
| PHP | 7.3 or higher |
| Plugin type | Encapsulated plugin |
| Licence | GPL v2 |

## Documentation and support

- Documentation: <https://asyntai.com/documentation/installation/zencart/>
- Email: hello@asyntai.com
- Issues: use the [Issues](../../issues) tab of this repository
