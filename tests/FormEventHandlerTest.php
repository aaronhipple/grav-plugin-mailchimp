<?php declare(strict_types=1);
namespace AaronHipple\Grav\Plugin\MailChimp\Test;

use AaronHipple\Grav\Plugin\MailChimp\FormEventHandler;
use DrewM\MailChimp\MailChimp;
use Grav\Plugin\Form\Form;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use RocketTheme\Toolbox\Event\Event;

/**
 * @covers \AaronHipple\Grav\Plugin\MailChimp\FormEventHandler
 */
final class FormEventHandlerTest extends TestCase
{
    protected $listId = 'I BE DEFAULT';
    protected $email = 'test@test.test';
    protected $subscriberId = 'dd46a756faad4727fb679320751f6dea'; // MailChimp::subscriberHash($email)

    protected function setUp(): void
    {
        $this->mailchimp = $this->prophesize(MailChimp::class);
        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array')
        );
        $this->mailchimp->delete(
            Argument::type('string'),
            Argument::type('array')
        );

        $this->form = $this->prophesize(Form::class);
        $this->form->willExtend(Form::class);
        $this->form->value('email')->willReturn($this->email);
        $this->form->value(Argument::type('string'))->willReturn(null);

        $this->event = $this->prophesize(Event::class);
        $this->event->willImplement('ArrayAccess');
        $this->event->offsetExists('params')->willReturn(true);
        $this->event->offsetExists('form')->willReturn(true);
        $this->event->offsetGet('form')->willReturn($this->form->reveal());
        $this->event->offsetGet('params')->willReturn([]);

        $this->handler = new FormEventHandler();
        $this->handler
            ->setMailChimp($this->mailchimp->reveal())
            ->setLanguage('en')
            ->setDefaultListIds([$this->listId])
            ->setDefaultStatus('pending')
            ->setIp('127.0.0.1');
    }

    public function testPutsAMemberInSimpleConfiguration()
    {
        $this->handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            "lists/{$this->listId}/members/{$this->subscriberId}",
            Argument::any()
        )->shouldHaveBeenCalled();
        $this->mailchimp->delete()->shouldNotHaveBeenCalled();
    }

    public function testDeletesAMemberWhenInstructed()
    {
        $this->handler
            ->setDeleteFirst(true)
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            "lists/{$this->listId}/members/{$this->subscriberId}",
            Argument::any()
        )->shouldHaveBeenCalled();
        $this->mailchimp->delete(
            "lists/{$this->listId}/members/{$this->subscriberId}"
        )->shouldHaveBeenCalled();
    }

    public function testSendsConfiguredFields()
    {
        $language = 'fr';
        $ipAddress = '8.8.8.8';
        $status = 'unsubscribed';

        $this->handler
            ->setLanguage($language)
            ->setIp($ipAddress)
            ->setDefaultStatus($status)
            ->onEvent($this->event->reveal());

        $expectedData = [
            'email_address' => $this->email,
            'status' => $status,
            'ip_signup' => $ipAddress,
            'language' => $language,
        ];

        $this->mailchimp->put(
            "lists/{$this->listId}/members/{$this->subscriberId}",
            $expectedData
        )->shouldHaveBeenCalled();
    }

    public function testSendsMappedFields()
    {
        $this->form->value('field_1')->willReturn('value_1');

        $this->event->offsetGet('params')->willReturn(
            [
                'field_mappings' => [
                    'a_field' => 'field_1',
                ]
            ]
        );

        $this->handler
            ->onEvent($this->event->reveal());

        $expectedData = [
            'email_address' => $this->email,
            'status' => 'pending',
            'ip_signup' => '127.0.0.1',
            'language' => 'en',
            'merge_fields' => [
                'a_field' => 'value_1',
            ],
        ];

        $this->mailchimp->put(
            "lists/{$this->listId}/members/{$this->subscriberId}",
            $expectedData
        )->shouldHaveBeenCalled();
    }

    public function testSendsToManyConfiguredLists()
    {
        $this->event->offsetGet('params')->willReturn(
            [
                'lists' => [
                    'list_one',
                    'list_two',
                ],
            ]
        );
        $this->handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            "lists/{$this->listId}/members/{$this->subscriberId}",
            Argument::any()
        )->shouldNotHaveBeenCalled();
        $this->mailchimp->put(
            "lists/list_one/members/{$this->subscriberId}",
            Argument::any()
        )->shouldHaveBeenCalled();
        $this->mailchimp->put(
            "lists/list_two/members/{$this->subscriberId}",
            Argument::any()
        )->shouldHaveBeenCalled();
    }

    public function testDoesNotSendIfAllRequiredFieldsAreAbsent()
    {
        $this->event->offsetGet('params')->willReturn(
            [
                'required_fields' => ['foo', 'bar'],
            ]
        );
        $this->handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array')
        )->shouldNotHaveBeenCalled();
    }

    public function testDoesNotSendIfSomeRequiredFieldsAreAbsent()
    {
        $this->form->value('foo')->willReturn('foo');

        $this->event->offsetGet('params')->willReturn(
            [
                'required_fields' => ['foo', 'bar'],
            ]
        );
        $this->handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array')
        )->shouldNotHaveBeenCalled();
    }

    public function testDoesSendIfAllRequiredFieldsArePresent()
    {
        $this->form->value('foo')->willReturn('foo');
        $this->form->value('bar')->willReturn('bar');

        $this->event->offsetGet('params')->willReturn(
            [
                'required_fields' => ['foo', 'bar'],
            ]
        );
        $this->handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array')
        )->shouldHaveBeenCalled();
    }

    public function testSupportsConfigurationsWithoutADefaultListID()
    {
        $handler = new FormEventHandler();
        $handler
            ->setMailChimp($this->mailchimp->reveal())
            ->setLanguage('en')
            ->setDefaultStatus('pending')
            ->setIp('127.0.0.1');

        $this->event->offsetGet('params')->willReturn(
            [
                'lists' => [
                    'list_one',
                ],
            ]
        );

        $handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            "lists/list_one/members/{$this->subscriberId}",
            Argument::any()
        )->shouldHaveBeenCalled();
    }

    public function testRequiresEitherDefaultOrFormBasedListIds()
    {
        $handler = new FormEventHandler();
        $handler
            ->setMailChimp($this->mailchimp->reveal())
            ->setLanguage('en')
            ->setDefaultStatus('pending')
            ->setIp('127.0.0.1');

        $handler
            ->onEvent($this->event->reveal());

        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array')
        )->shouldNotHaveBeenCalled();
    }
}
