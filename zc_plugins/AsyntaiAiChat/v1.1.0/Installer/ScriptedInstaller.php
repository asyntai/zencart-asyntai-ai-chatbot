<?php
/**
 * Asyntai AI Chat - install and uninstall.
 *
 * The install adds one configuration group with four settings, an entry in
 * the admin Configuration menu, the "Asyntai AI Chat" page under Tools, and
 * one small table for the plugin's private state. The uninstall removes all
 * of them. No store data is touched, and no request leaves the store.
 *
 * Only plain SQL and the two admin-page functions are used here, so the same
 * installer runs on Zen Cart 1.5.8 and on 2.x. The configuration helper
 * methods of the base class arrived in v2.0.1 and are not used.
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected $configGroupTitle = 'Asyntai AI Chat';

    protected $configKeys = [
        'ASYNTAI_STATUS',
        'ASYNTAI_WIDGET_ID',
        'ASYNTAI_SCRIPT_URL',
        'ASYNTAI_EXCLUDED_PAGES',
    ];

    protected function executeInstall()
    {
        $cgi = $this->getOrCreateGroupId();
        if ($cgi === 0) {
            return false;
        }

        $keys = "'" . implode("', '", $this->configKeys) . "'";
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ($keys)"
        );

        $sql =
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value,
                 configuration_description, configuration_group_id, sort_order,
                 date_added, use_function, set_function)
             VALUES
                ('Enable Asyntai AI Chat', 'ASYNTAI_STATUS', 'False',
                 'Set to <strong>True</strong> to show the chat widget on your storefront. The widget only loads when a valid Widget ID is set below.',
                 $cgi, 10, now(), NULL, 'zen_cfg_select_option([''True'', ''False''], '),

                ('Widget ID', 'ASYNTAI_WIDGET_ID', '',
                 'Your own Asyntai Widget ID, for example <code>asyntai_1a2b3c4d5e6f</code>. Find it in your Asyntai dashboard under <em>Install</em>. Without this ID nothing loads.',
                 $cgi, 20, now(), NULL, NULL),

                ('Widget script URL', 'ASYNTAI_SCRIPT_URL', 'https://widget.asyntai.com/static/js/chat-widget.js',
                 'The URL of the widget script. Change this only if Asyntai tells you to, or if you host the script yourself. The URL must start with <code>https://</code> and end with <code>.js</code>.',
                 $cgi, 30, now(), NULL, NULL),

                ('Pages without the widget', 'ASYNTAI_EXCLUDED_PAGES', 'checkout_payment,checkout_confirmation,checkout_process,checkout_success',
                 'A list of page names, separated by commas, on which the widget must not load. The page name is the <code>main_page</code> value in the address, for example <code>checkout_payment</code>. Leave this empty to load the widget on every page.',
                 $cgi, 40, now(), NULL, NULL)";
        $this->executeInstallerSql($sql);

        zen_deregister_admin_pages(['configAsyntaiAiChat']);
        zen_register_admin_page(
            'configAsyntaiAiChat',
            'BOX_CONFIGURATION_ASYNTAI_AI_CHAT',
            'FILENAME_CONFIGURATION',
            "gID=$cgi",
            'configuration',
            'Y'
        );

        return $this->addConnectPage();
    }

    /**
     * From 1.0.0: keeps the four settings and their values, and adds the
     * private state table and the "Connect" page under Tools.
     *
     * Zen Cart 1.5.8 calls this without an argument, 2.x with the old version.
     */
    protected function executeUpgrade($oldVersion = null)
    {
        if ($this->getOrCreateGroupId() === 0) {
            return false;
        }
        return $this->addConnectPage();
    }

    protected function addConnectPage()
    {
        $this->executeInstallerSql(
            "CREATE TABLE IF NOT EXISTS " . DB_PREFIX . "asyntai_ai_chat_state (
                state_key VARCHAR(64) NOT NULL,
                state_value TEXT NOT NULL,
                PRIMARY KEY (state_key)
            )"
        );

        zen_deregister_admin_pages(['toolsAsyntaiAiChat']);
        zen_register_admin_page(
            'toolsAsyntaiAiChat',
            'BOX_TOOLS_ASYNTAI_AI_CHAT',
            'FILENAME_ASYNTAI_AI_CHAT',
            '',
            'tools',
            'Y'
        );

        return true;
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['configAsyntaiAiChat', 'toolsAsyntaiAiChat']);
        $this->executeInstallerSql("DROP TABLE IF EXISTS " . DB_PREFIX . "asyntai_ai_chat_state");

        $keys = "'" . implode("', '", $this->configKeys) . "'";
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ($keys)"
        );
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION_GROUP . "
              WHERE configuration_group_title = '" . $this->configGroupTitle . "'"
        );

        return true;
    }

    /**
     * The id of the plugin's configuration group. It is created when missing.
     * Returns 0 when the group could not be created.
     */
    protected function getOrCreateGroupId()
    {
        $select =
            "SELECT configuration_group_id
               FROM " . TABLE_CONFIGURATION_GROUP . "
              WHERE configuration_group_title = '" . $this->configGroupTitle . "'
              LIMIT 1";

        $check = $this->dbConn->Execute($select);
        if ($check->EOF) {
            $this->executeInstallerSql(
                "INSERT INTO " . TABLE_CONFIGURATION_GROUP . "
                    (configuration_group_title, configuration_group_description, sort_order, visible)
                 VALUES
                    ('" . $this->configGroupTitle . "', 'Settings for the Asyntai AI chat widget on your storefront.', 1, 1)"
            );
            $check = $this->dbConn->Execute($select);
            if ($check->EOF) {
                return 0;
            }
        }

        $cgi = (int)$check->fields['configuration_group_id'];

        // Zen Cart sorts the Configuration menu by sort_order; the group id is
        // the value the core installer uses for a new group.
        $this->executeInstallerSql(
            "UPDATE " . TABLE_CONFIGURATION_GROUP . "
                SET sort_order = $cgi
              WHERE configuration_group_id = $cgi
              LIMIT 1"
        );

        return $cgi;
    }
}
