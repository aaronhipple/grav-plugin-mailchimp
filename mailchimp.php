<?php
namespace Grav\Plugin;

use DrewM\MailChimp\MailChimp;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class MailChimpPlugin
 * @package Grav\Plugin
 */
class MailChimpPlugin extends Plugin
{
    /**
     * MailChimp's supported languages
     *
     * @var array
     * @see http://kb.mailchimp.com/lists/managing-subscribers/view-and-edit-subscriber-languages
     */
    private static $supportedLanguages = [
        'en' => 'English',
        'ar' => 'Arabic',
        'af' => 'Afrikaans',
        'be' => 'Belarusian',
        'bg' => 'Bulgarian',
        'ca' => 'Catalan',
        'zh' => 'Chinese',
        'hr' => 'Croatian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'nl' => 'Dutch',
        'et' => 'Estonian',
        'fa' => 'Farsi',
        'fi' => 'Finnish',
        'fr' => 'French (France)',
        'fr_CA' => 'French (Canada)',
        'de' => 'German',
        'el' => 'Greek',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hu' => 'Hungarian',
        'is' => 'Icelandic',
        'id' => 'Indonesian',
        'ga' => 'Irish',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'km' => 'Khmer',
        'ko' => 'Korean',
        'lv' => 'Latvian',
        'lt' => 'Lithuanian',
        'mt' => 'Maltese',
        'ms' => 'Malay',
        'mk' => 'Macedonian',
        'no' => 'Norwegian',
        'pl' => 'Polish',
        'pt' => 'Portuguese (Brazil)',
        'pt_PT' => 'Portuguese (Portugal)',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sr' => 'Serbian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'es' => 'Spanish (Mexico)',
        'es_ES' => 'Spanish (Spain)',
        'sw' => 'Swahili',
        'sv' => 'Swedish',
        'ta' => 'Tamil',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'vi' => 'Vietnamese',
    ];


    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }

        // Enable the main event we are interested in
        $this->enable([
            'onFormProcessed' => ['onFormProcessed', 0]
        ]);
    }

    /**
     * Add the MailChimp form handler
     * @param Event $event
     */
    public function onFormProcessed(Event $event)
    {
        switch ($event['action']) {
            case 'mailchimp':
                $this->handleSubscribe($event);
        }
    }

    /**
     * Retrieve supported languages
     * @return array
     */
    public static function supportedLanguages()
    {
        return self::$supportedLanguages;
    }

    /**
     * @param Event $event
     */
    protected function checkTriggers($event)
    {

        /* 
            If the triggers are not even defined then just return true
            so we can just process the MC submission
        */

        $params = $event['params'];
        $form = $event['form'];

        if ( isset($params['required_fields']) && !empty($params['required_fields']) ) {

            $fields = (is_string($params['required_fields'])) ? [$params['required_fields']] : $params['required_fields'];

            foreach ($fields as $field) {
                $trigger_value = $form->value( $field );
                if ( !$trigger_value ) { return false; }
            }

        }

        return true;

    }

    /**
     * @param Event $event
     */
    protected function handleSubscribe(Event $event)
    {

        // Ignore this processing if one of the required fields are empty
        if ( !$this->checkTriggers($event) ) { return false; }

        $action = $event['action'];
        $form = $event['form'];
        $params = $event['params'];

        $mailChimp = $this->getAPIWrapper();
        $listIDs = $params['lists'];
        $fieldMappings = (array_key_exists('field_mappings', $params)) ? $params['field_mappings'] : [];
        $language = $this->getLanguage();

        array_map(function ($listID) use ($mailChimp, $form, $fieldMappings, $language) {
            $emailAddress = $form->value('email');
            $data = [
                'email_address' => $emailAddress,
                'status' => $this->grav['config']->get('plugins.mailchimp.default_status'),
                'ip_signup' => $this->grav['uri']->ip(),
                'language' => $language,
            ];

            if (!empty($fieldMappings)) {
                $data['merge_fields'] = $this->getMergeFields($fieldMappings, $form);
            }

            if ($this->grav['config']->get('plugins.mailchimp.delete_first')) {
                $mailChimp->delete("lists/{$listID}/members/" . md5(strtolower($emailAddress)));
            }

            $mailChimp->post("lists/{$listID}/members", $data);

            if (!$mailChimp->success()) {
                $this->grav['log']->error(sprintf('MailChimp error: %s', $mailChimp->getLastError()));
            }
        }, $listIDs);
    }

    /**
     * @return MailChimp
     * @throws \Exception
     */
    protected function getAPIWrapper()
    {
        // Autoload classes
        $autoload = [
            __DIR__ . '/vendor/autoload.php',
            VENDOR_DIR . '/autoload.php',
        ];
        if (!is_file($autoload[0])) {
            if (count($autoload) === 0) {
                throw new \Exception('MailChimp plugin failed to load. Composer dependencies not met.');
            } else {
                array_shift($autoload);
            }
        }
        require_once $autoload[0];
        $apiKey = $this->grav['config']->get('plugins.mailchimp.api_key');
        $mailChimp = new MailChimp($apiKey);
        return $mailChimp;
    }

    /**
     * @return mixed
     */
    protected function getLanguage()
    {
        $detected_languages = [];
        switch ($this->grav['config']->get('plugins.mailchimp.language_detection_mode')) {
            case 'browser':
                $detected_languages = $this->grav['language']->getBrowserLanguages();
                break;
            case 'active':
                $detected_languages = [$this->grav['language']->getActive()];
                break;
        }
        $intersect_languages = array_intersect($detected_languages, array_keys(self::supportedLanguages()));
        if (!empty($intersect_languages)) {
            return end($intersect_languages);
        }
        return $this->grav['config']->get('plugins.mailchimp.default_language');
    }

    /**
     * @param array $fieldMappings
     * @param Form $form
     * @return array
     */
    protected function getMergeFields(array $fieldMappings, Form $form)
    {
        $mergeFields = [];
        foreach ($fieldMappings as $key => $value) {
            $mergeFields[$key] = $form->value($value);
        }
        return $mergeFields;
    }
}
