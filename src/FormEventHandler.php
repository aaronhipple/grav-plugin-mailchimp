<?php declare(strict_types=1);
namespace AaronHipple\Grav\Plugin\MailChimp;

use DrewM\MailChimp\MailChimp;
use Grav\Plugin\Form\Form;
use RocketTheme\Toolbox\Event\Event;

/**
 * FormEventHandler sends MailChimp subscribers based on form submissions.
 *
 * @author Aaron Hipple
 */
final class FormEventHandler
{
    public function setMailChimp(MailChimp $mailchimp)
    {
        $this->mailchimp = $mailchimp;
        return $this;
    }

    public function setLanguage(string $language)
    {
        $this->language = $language;
        return $this;
    }
    public function setDefaultListIds(array $defaultListIds)
    {
        $this->defaultListIds = $defaultListIds;
        return $this;
    }
    public function setDefaultStatus(string $defaultStatus)
    {
        $this->defaultStatus = $defaultStatus;
        return $this;
    }
    public function setIp(string $ip)
    {
        $this->ip = $ip;
        return $this;
    }
    public function setDeleteFirst(bool $deleteFirst)
    {
        $this->deleteFirst = $deleteFirst;
        return $this;
    }
    public function onEvent(Event $event)
    {
        $this->build()->onEvent($event);
    }


    private function build()
    {
        return new class(
            $this->mailchimp,
            $this->language,
            $this->defaultListIds ?? [],
            $this->defaultStatus,
            $this->ip,
            $this->deleteFirst ?? false
        ) {
            public function __construct(
                MailChimp $mailchimp,
                string $language,
                array $defaultListIds,
                string $defaultStatus,
                string $ip,
                bool $deleteFirst
            ) {
                $this->mailchimp = $mailchimp;
                $this->language = $language;
                $this->defaultListIds = $defaultListIds;
                $this->defaultStatus = $defaultStatus;
                $this->ip = $ip;
                $this->deleteFirst = $deleteFirst;
            }

            /**
             * @param Event $event
             */
            public function onEvent(Event $event)
            {
                if (!$this->shouldSubscribe($event)) {
                    return false;
                }

                $listIds = array_key_exists('lists', $event['params'])
                         ? $event['params']['lists']
                         : $this->defaultListIds;

                $fieldMappings = array_key_exists('field_mappings', $event['params'])
                               ? $event['params']['field_mappings']
                               : [];
                $email = $event['form']->value('email');
                $subscriberId = MailChimp::subscriberHash($email);
                $mergeFields = empty($fieldMappings)
                             ? null
                             : $this->getMergeFields($fieldMappings, $event['form']);

                foreach ($listIds as $listId) {
                    $data = [
                        'email_address' => $email,
                        'status' => $this->defaultStatus,
                        'ip_signup' => $this->ip,
                        'language' => $this->language,
                    ];

                    if (!is_null($mergeFields)) {
                        $data['merge_fields'] = $mergeFields;
                    }


                    $route = "lists/{$listId}/members/{$subscriberId}";

                    if ($this->deleteFirst) {
                        $this->mailchimp->delete($route);
                    }

                    $this->mailchimp->put($route, $data);
                }
            }

            /**
             * @param Event $event
             */
            protected function shouldSubscribe(Event $event)
            {

                /*
                  If the triggers are not even defined then just return true
                  so we can just process the MC submission
                */

                $params = $event['params'];
                $form = $event['form'];

                if (isset($params['required_fields']) && !empty($params['required_fields'])) {
                    $isStr  = is_string($params['required_fields']);
                    $fields = $isStr ? [$params['required_fields']] : $params['required_fields'];
                    foreach ($fields as $field) {
                        $trigger_value = $form->value($field);
                        if (!$trigger_value) {
                            return false;
                        }
                    }
                }

                return true;
            }

            /**
             * @param  array         $fieldMappings
             * @param  Form $form
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
        };
    }
}
