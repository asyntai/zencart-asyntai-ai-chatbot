<?php
/**
 * Asyntai AI Chat - install and uninstall.
 *
 * The install adds one configuration group with four settings, and one entry
 * in the admin Configuration menu. The uninstall removes both. No store data
 * is touched, and no request leaves the store.
 *
 * @copyright Copyright 2026 Advanced AI s.r.o.
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected string $configGroupTitle = 'Asyntai AI Chat';

    protected function executeInstall()
    {
        $cgi = $this->getOrCreateConfigGroupId(
            $this->configGroupTitle,
            'Settings for the Asyntai AI chat widget on your storefront.'
        );

        $this->addConfigurationKey('ASYNTAI_STATUS', [
            'configuration_title' => 'Enable Asyntai AI Chat',
            'configuration_value' => 'False',
            'configuration_description' => 'Set to <strong>True</strong> to show the chat widget on your storefront. The widget only loads when a valid Widget ID is set below.',
            'configuration_group_id' => $cgi,
            'sort_order' => 10,
            'set_function' => 'zen_cfg_select_option([\'True\', \'False\'], ',
        ]);

        $this->addConfigurationKey('ASYNTAI_WIDGET_ID', [
            'configuration_title' => 'Widget ID',
            'configuration_value' => '',
            'configuration_description' => 'Your own Asyntai Widget ID, for example <code>asyntai_1a2b3c4d5e6f</code>. Find it in your Asyntai dashboard under <em>Install</em>. Without this ID nothing loads.',
            'configuration_group_id' => $cgi,
            'sort_order' => 20,
        ]);

        $this->addConfigurationKey('ASYNTAI_SCRIPT_URL', [
            'configuration_title' => 'Widget script URL',
            'configuration_value' => 'https://widget.asyntai.com/static/js/chat-widget.js',
            'configuration_description' => 'The URL of the widget script. Change this only if Asyntai tells you to, or if you host the script yourself. The URL must start with <code>https://</code> and end with <code>.js</code>.',
            'configuration_group_id' => $cgi,
            'sort_order' => 30,
        ]);

        $this->addConfigurationKey('ASYNTAI_EXCLUDED_PAGES', [
            'configuration_title' => 'Pages without the widget',
            'configuration_value' => 'checkout_payment,checkout_confirmation,checkout_process,checkout_success',
            'configuration_description' => 'A list of page names, separated by commas, on which the widget must not load. The page name is the <code>main_page</code> value in the address, for example <code>checkout_payment</code>. Leave this empty to load the widget on every page.',
            'configuration_group_id' => $cgi,
            'sort_order' => 40,
        ]);

        zen_deregister_admin_pages(['configAsyntaiAiChat']);
        zen_register_admin_page(
            'configAsyntaiAiChat',
            'BOX_CONFIGURATION_ASYNTAI_AI_CHAT',
            'FILENAME_CONFIGURATION',
            "gID=$cgi",
            'configuration',
            'Y'
        );

        return true;
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['configAsyntaiAiChat']);

        $this->deleteConfigurationKeys([
            'ASYNTAI_STATUS',
            'ASYNTAI_WIDGET_ID',
            'ASYNTAI_SCRIPT_URL',
            'ASYNTAI_EXCLUDED_PAGES',
        ]);
        $this->deleteConfigurationGroup($this->configGroupTitle, true);

        return true;
    }
}
