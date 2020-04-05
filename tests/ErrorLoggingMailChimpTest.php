<?php declare(strict_types=1);
namespace AaronHipple\Grav\Plugin\MailChimp\Test;

use AaronHipple\Grav\Plugin\MailChimp\ErrorLoggingMailChimp;
use DrewM\MailChimp\MailChimp;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

/**
 * @covers \AaronHipple\Grav\Plugin\MailChimp\ErrorLoggingMailChimp
 */
final class ErrorLoggingMailChimpTest extends TestCase
{
    protected function setUp(): void
    {
        $this->mailchimp = $this->prophesize(MailChimp::class);
        $this->mailchimp->put(
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('int')
        )->willReturn(null);
        $this->mailchimp->delete(
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('int')
        )->willReturn(null);
        $this->mailchimp->post(
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('int')
        )->willReturn(null);
        $this->mailchimp->get(
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('int')
        )->willReturn(null);
        $this->mailchimp->patch(
            Argument::type('string'),
            Argument::type('array'),
            Argument::type('int')
        )->willReturn(null);
        $this->mailchimp->success()->willReturn(true);
        $this->mailchimp->getLastError()->willReturn(null);

        $this->logger = $this->prophesize(Logger::class);
        $this->logger->error(Argument::type('string'));
    }

    protected function lmc()
    {
        return $this->loggingMailchimp = new ErrorLoggingMailChimp(
            $this->mailchimp->reveal(),
            $this->logger->reveal()
        );
    }

    public function methods()
    {
        return [['get'], ['post'], ['put'], ['patch'], ['delete']];
    }

    /**
     * @dataProvider methods
     */
    public function testDoesNotLogWhenAnErrorDoesNotOccur(string $method)
    {
        $this->mailchimp->success()->willReturn(true);

        $this->lmc()->$method("", []);

        $this->logger->error(Argument::type('string'))->shouldNotHaveBeenCalled();
    }

    /**
     * @dataProvider methods
     */
    public function testLogsWhenAnErrorOccurs(string $method)
    {
        $this->mailchimp->success()->willReturn(false);
        $this->mailchimp->getLastError()->willReturn("Oh no!");
        $this->mailchimp->getLastResponse()->willReturn(
            [
                'headers' => [],
                'body' => '',
            ]
        );

        $this->lmc()->$method("", []);

        $this->logger->error(Argument::type('string'))->shouldHaveBeenCalled();
    }
}
