# Asyntai AI Chat for Zen Cart

Adds the Asyntai AI chat widget to your Zen Cart storefront. The widget answers
visitor questions about your products, your delivery and your returns. When it
cannot answer, it collects the question and the visitor email for your team.

- Zen Cart v1.5.8 and later (encapsulated plugin, installed from Plugin Manager)
- No template edit. The widget loads from the `NOTIFY_HTML_HEAD_END` notifier
- No core file is changed
- Licence: GPL v2

## Install

1. Copy the `zc_plugins` folder into your Zen Cart root. You get
   `zc_plugins/AsyntaiAiChat/v1.1.0/`.
2. In the admin, open **Modules > Plugin Manager**.
3. Click **Asyntai AI Chat**, then click **Install**.
4. Open **Tools > Asyntai AI Chat** and click **Connect to Asyntai**.
5. Sign in or create a free Asyntai account in the window that opens. The
   plugin fills in your Widget ID and switches the widget on.

## Upgrade from v1.0.0

Copy the `v1.1.0` folder next to `v1.0.0`, then click **Upgrade Available** in
**Modules > Plugin Manager**. Your settings and your Widget ID stay as they are.
To let the chatbot read your shop, open **Tools > Asyntai AI Chat** and tick
**Let the chatbot read this shop**.

## Let the chatbot read this shop (optional)

When this is on, Asyntai reads your enabled products (name, description, price,
offer price, stock, brand, category, image), your categories, your EZ-Pages and
your information pages (shipping, privacy, conditions, about us, contact) from
your shop. Only what a visitor can already see is shared: disabled products and
hidden EZ-Pages are left out.

- Asyntai reads a signed address on your shop:
  `index.php?main_page=index&asyntai_feed=products` (and `=pages`). Every request
  must carry a signature made with a secret that only your shop and Asyntai
  know. Other requests get HTTP 403.
- The secret is kept in the plugin's own table, never in a page or a setting.
- Your shop server never contacts Asyntai. When you click **Connect**, your own
  browser sends the connection request and opens the sign-in window.
- Untick the box, or click **Disconnect**, and the address answers 403 at once.

## Settings

**Configuration > Asyntai AI Chat**:

| Setting | What it does |
| --- | --- |
| Enable Asyntai AI Chat | Shows or hides the widget. |
| Widget ID | Your Asyntai Widget ID. Filled in by **Connect**, or paste it yourself. |
| Widget script URL | The URL of the widget script. Change it only if you host the script yourself. Must start with `https://` and end with `.js`. |
| Pages without the widget | Page names, separated by commas, where the widget must not load. Default: the checkout pages. |

## Uninstall

Open **Modules > Plugin Manager**, click **Asyntai AI Chat**, then click
**Un-Install**. The settings, the configuration group, both admin menu entries
and the plugin's table are removed. No store data is touched.

## Support

https://asyntai.com/documentation/installation/zencart/ or hello@asyntai.com
