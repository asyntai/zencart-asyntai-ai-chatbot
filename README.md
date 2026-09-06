# Asyntai AI Chat for Zen Cart

Adds the [Asyntai](https://asyntai.com/) AI chat widget to your Zen Cart storefront.
The widget answers visitor questions about your products, your delivery and your
returns. When it cannot answer, it collects the question and the visitor email for
your team.

- Zen Cart 2.1.0 and later
- Encapsulated plugin. You install it from the Plugin Manager
- No core file change and no template edit. It uses the `NOTIFY_HTML_HEAD_END` notifier
- Licence: GPL v2

## What you need

An Asyntai account and a Widget ID. Create one at <https://asyntai.com/>. The free
plan is enough to run the plugin.

## Install

1. Download the latest ZIP from [Releases](../../releases), or download this
   repository as a ZIP.
2. Copy the `zc_plugins` folder into your Zen Cart root, next to `index.php`. You
   get `zc_plugins/AsyntaiAiChat/v1.0.0/`.
3. In the admin, open **Modules > Plugin Manager**.
4. Click **Asyntai AI Chat**, then click **Install**.
5. Open **Configuration > Asyntai AI Chat**.
6. Paste your Widget ID.
7. Set **Enable Asyntai AI Chat** to **True**, then click **Save All Changes**.
8. Open your storefront. The chat button is in the bottom corner.

## Settings

| Setting | What it does |
| --- | --- |
| Enable Asyntai AI Chat | Shows or hides the widget. Default `False`. |
| Widget ID | Your own Asyntai Widget ID, for example `asyntai_1a2b3c4d5e6f`. Nothing loads without it. |
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
**Un-Install**. The four settings, the configuration group and the admin menu entry
are removed. No store data is touched.

To hide the widget without an uninstall, set **Enable Asyntai AI Chat** to **False**.

## Compatibility

| Item | Value |
| --- | --- |
| Zen Cart | 2.1.0, 2.2.0, 2.2.1, 2.2.2 |
| PHP | 8.2 or higher |
| Plugin type | Encapsulated plugin |
| Licence | GPL v2 |

## Documentation and support

- Documentation: <https://asyntai.com/documentation/installation/zencart/>
- Email: hello@asyntai.com
- Issues: use the [Issues](../../issues) tab of this repository
