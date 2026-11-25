# Changelog

## v1.1.0

- New page **Tools > Asyntai AI Chat**: connect the shop to your Asyntai
  account with one click. The Widget ID is filled in for you.
- The chatbot can now read your enabled products, categories, EZ-Pages and
  information pages directly from your shop, so its answers stay up to date
  (optional, switch it off at any time).
- Improvements to the plugin settings screen.
- Tested on Zen Cart 1.5.8a, 2.1.0 and 2.2.2.

Upgrade: copy the new `zc_plugins/AsyntaiAiChat/v1.1.0/` folder to your store,
then click **Upgrade Available** in **Modules > Plugin Manager**. Your settings
and your Widget ID stay as they are.

## v1.0.0

First release.

- Adds the Asyntai AI chat widget to the Zen Cart storefront through the
  `NOTIFY_HTML_HEAD_END` notifier. No core file change and no template edit.
- Adds four settings under **Configuration > Asyntai AI Chat**: enable, Widget ID,
  widget script URL, and the list of pages without the widget.
- The uninstall removes the settings, the configuration group and the admin menu
  entry.
- Tested on Zen Cart 1.5.8a, 2.1.0 and 2.2.2 with PHP 7.3 to 8.3 and MySQL 8.4.
