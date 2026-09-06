# Changelog

## v1.0.0

First release.

- Adds the Asyntai AI chat widget to the Zen Cart storefront through the
  `NOTIFY_HTML_HEAD_END` notifier. No core file change and no template edit.
- Adds four settings under **Configuration > Asyntai AI Chat**: enable, Widget ID,
  widget script URL, and the list of pages without the widget.
- The uninstall removes the settings, the configuration group and the admin menu
  entry.
- Tested on Zen Cart 1.5.8a, 2.1.0 and 2.2.2 with PHP 7.3 to 8.3 and MySQL 8.4.
