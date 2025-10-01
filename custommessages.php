<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class CustomMessages extends Module
{
    public function __construct()
    {
        $this->name = 'custommessages';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Jordi Rosell';
        $this->need_instance = 0;

        parent::__construct();

        $this->displayName = $this->l('Custom Messages');
        $this->description = $this->l('Display custom messages in hooks like displayNav1.');

        // Prestashop 1.7.8.0 to 8.x compliance
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        // Must clear cache on install to ensure Configuration::get works correctly immediately after install
        Tools::clearSmartyCache();
        
        return parent::install()
            && $this->registerHook('displayNav1')
            && $this->installDefaultMessages();
    }

    public function uninstall()
    {
        // Clean up the configuration value upon uninstall
        return Configuration::deleteByName('CUSTOMMESSAGE_TEXT') && parent::uninstall();
    }

    /**
     * Install default messages for all languages and all shops
     */
    private function installDefaultMessages()
    {
        $languages = Language::getLanguages(false);
        $shops = Shop::getShops(false, null, true);

        $default_messages_map = [
            'en' => 'The best...',
            'fr' => 'Le meilleur...',
            'de' => 'Die besten....',
            'it' => 'Il miglior...',
            'es' => 'El mejor...',
        ];
        
        // 1. Build the multilingual array
        $values = [];
        foreach ($languages as $lang) {
            $iso = $lang['iso_code'];
            $values[$lang['id_lang']] = isset($default_messages_map[$iso])
                ? $default_messages_map[$iso]
                : 'Custom Message - Set in BO';
        }

        // 2. Save the multilingual array for each shop explicitly
        foreach ($shops as $shop_id) {
            // Configuration::updateValue($key, $values, $html = false, $id_shop_group = null, $id_shop = null)
            // When $values is a language array, $html should be false and Prestashop handles the language saving.
            if (!Configuration::updateValue('CUSTOMMESSAGE_TEXT', $values, false, null, $shop_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display message in front office
     */
    public function hookDisplayNav1($params)
    {
        // Get context shop ID and language ID
        $shop_id = (int)$this->context->shop->id;
        $lang_id = (int)$this->context->language->id;

        // Retrieve the message for the current language and shop
        // Configuration::get($key, $id_lang, $id_shop_group, $id_shop)
        $message = Configuration::get('CUSTOMMESSAGE_TEXT', $lang_id, null, $shop_id);

        if (empty($message)) {
            return '';
        }

        $this->context->smarty->assign([
            'custom_message' => $message,
        ]);

        // Use a template for cleaner rendering
        return $this->display(__FILE__, 'custommessages.tpl');
    }

    /**
     * Back Office configuration
     */
    public function getContent()
    {
        $output = '';
        $shop_id = (int)$this->context->shop->id;
        $languages = Language::getLanguages(false);

        if (Tools::isSubmit('submit_custommessages')) {
            $values = [];
            $all_languages_set = true;
            
            // FIX: You must iterate through languages to get the values from HelperForm
            foreach ($languages as $lang) {
                // The input name is posted as [name]_[id_lang] when lang => true is set
                $value = Tools::getValue('CUSTOMMESSAGE_TEXT_' . (int)$lang['id_lang']);
                
                if (empty($value)) {
                    $all_languages_set = false;
                }
                
                $values[(int)$lang['id_lang']] = $value;
            }

            if (!empty($values) && $all_languages_set) {
                // Save the multilingual array for the current shop
                // Configuration::updateValue($key, $values, $html = false, $id_shop_group = null, $id_shop = null)
                if (Configuration::updateValue('CUSTOMMESSAGE_TEXT', $values, false, null, $shop_id)) {
                    $output .= $this->displayConfirmation($this->l('Settings updated.'));
                } else {
                    $output .= $this->displayError($this->l('Failed to update settings.'));
                }
            } else {
                // FIX: This now correctly displays if any required field is missing
                $output .= $this->displayError($this->l('Message required for all enabled languages.'));
            }
        }
        
        return $output . $this->renderForm();
    }

    /**
     * Render the BO form
     */
    private function renderForm()
    {
        // ... (rest of the renderForm is correct)
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Custom Message Settings'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Message'),
                        'name' => 'CUSTOMMESSAGE_TEXT',
                        'lang' => true, // Essential for multilingual field
                        'size' => 255,
                        'required' => true,
                    ]
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->submit_action = 'submit_custommessages';

        // Multilingual configuration
        $helper->default_form_language = (int)$this->context->language->id;
        $helper->allow_employee_form_lang = true;
        $languages = Language::getLanguages(false);
        foreach ($languages as &$lang) {
            if (!isset($lang['is_default'])) {
                $lang['is_default'] = ($lang['id_lang'] == (int)$this->context->language->id) ? 1 : 0;
            }
        }
        $helper->languages = $languages;
        $helper->id_language = (int)$this->context->language->id;

        // Load current values for current shop
        $helper->fields_value = $this->getFormValues();

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Load multilingual values for the current shop
     */
    protected function getFormValues()
    {
        $fields_value = [];
        $languages = Language::getLanguages(false);
        $shop_id = (int)$this->context->shop->id;

        foreach ($languages as $lang) {
            // Configuration::get($key, $id_lang, $id_shop_group, $id_shop)
            $fields_value['CUSTOMMESSAGE_TEXT'][$lang['id_lang']] = Configuration::get('CUSTOMMESSAGE_TEXT', $lang['id_lang'], null, $shop_id);
        }

        return $fields_value;
    }
}