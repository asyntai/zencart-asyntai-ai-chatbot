# Asyntai AI Chat for Zen Cart

Adds the Asyntai AI chat widget to your Zen Cart storefront. The widget answers
visitor questions about your products, your delivery and your returns. When it
cannot answer, it collects the question and the visitor email for your team.

- Zen Cart v2.1.0 and later (encapsulated plugin, installed from Plugin Manager)
- No template edit. The widget loads from the `NOTIFY_HTML_HEAD_END` notifier
- No core file is changed
- Licence: GPL v2

## What you need

An Asyntai account and a Widget ID. Create one at https://asyntai.com/. The
free plan is enough to test the plugin.

## Install

1. Copy the `zc_plugins` folder into your Zen Cart root. You get
   `zc_plugins/AsyntaiAiChat/v1.0.0/`.
2. In the admin, open **Modules > Plugin Manager**.
3. Click **Asyntai AI Chat**, then click **Install**.
4. Open **Configuration > Asyntai AI Chat**.
5. Paste your Widget ID.
6. Set **Enable Asyntai AI Chat** to **True**, then click **Save All Changes**.
7. Open your storefront. The chat button is in the bottom corner.

## Settings

| Setting | What it does |
| --- | --- |
| Enable Asyntai AI Chat | Shows or hides the widget. Default `False`. |
| Widget ID | Your own Asyntai Widget ID, for example `asyntai_1a2b3c4d5e6f`. Nothing loads without it. |
| Widget script URL | The URL of the widget script. Change it only if you host the script yourself. Must start with `https://` and end with `.js`. |
| Pages without the widget | Page names, separated by commas, where the widget must not load. Default: the checkout pages. |

## Uninstall

Open **Modules > Plugin Manager**, click **Asyntai AI Chat**, then click
**Uninstall**. The four settings, the configuration group and the admin menu
entry are removed. No store data is touched.

## Privacy

The plugin sends nothing on its own. It writes one `<script>` tag into the page
head with the Widget ID that you enter. The widget then runs in the visitor
browser and talks to Asyntai from there. There is no affiliate parameter and no
tracking of your store.

## Support

https://asyntai.com/zencart-plugin-documentation or hello@asyntai.com
