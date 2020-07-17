<?php declare(strict_types=1);
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

namespace Grav\Plugin;

use RocketTheme\Toolbox\Event\Event;

$vendoredAutoloader = __DIR__ . '/vendor/autoload.php';
if (is_file($vendoredAutoloader)) {
    include_once $vendoredAutoloader;
}

use AaronHipple\Grav\Plugin\MailChimp\ErrorLoggingMailChimp;
use AaronHipple\Grav\Plugin\MailChimp\FormEventHandler;
use DrewM\MailChimp\MailChimp;
use Grav\Common\Plugin;

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
        $this->enable(
            [
                'onFormProcessed' => ['onFormProcessed', 0]
            ]
        );
    }

    /**
     * Add the MailChimp form handler
     * @param RocketTheme\Toolbox\Event\Event $event
     */
    public function onFormProcessed(Event $event)
    {
        switch ($event['action']) {
            case 'mailchimp':
                $mailchimp = new ErrorLoggingMailChimp(
                    new MailChimp($this->grav['config']->get('plugins.mailchimp.api_key')),
                    $this->grav['log']
                );

                $handler = (new FormEventHandler())
                    ->setMailChimp($mailchimp)
                    ->setDefaultStatus($this->grav['config']->get('plugins.mailchimp.default_status'))
                    ->setDeleteFirst($this->grav['config']->get('plugins.mailchimp.delete_first'))
                    ->setIp($this->grav['uri']->ip())
                    ->setLanguage($this->getLanguage());

                $defaultListId = $this->grav['config']->get('plugins.mailchimp.default_list_id');

                if (!is_null($defaultListId)) {
                    $handler->setDefaultListIds([$defaultListId]);
                }

                $handler->onEvent($event);
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
     * @return mixed
     */
    protected function getLanguage()
    {
        $detectedLanguages = [];
        switch ($this->grav['config']->get('plugins.mailchimp.language_detection_mode')) {
            case 'browser':
                $detectedLanguages = $this->grav['language']->getBrowserLanguages();
                break;
            case 'active':
                $detectedLanguages = [$this->grav['language']->getActive()];
                break;
        }
        $intersectLanguages = array_intersect($detectedLanguages, array_keys(self::supportedLanguages()));
        if (!empty($intersectLanguages)) {
            return end($intersectLanguages);
        }
        return $this->grav['config']->get('plugins.mailchimp.default_language');
    }
}
