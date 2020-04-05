<?php declare(strict_types=1);
namespace AaronHipple\Grav\Plugin\MailChimp;

use DrewM\MailChimp\MailChimp;
use Monolog\Logger;

class ErrorLoggingMailChimp extends MailChimp
{
    public function __construct(MailChimp $delegate, Logger $log)
    {
        $this->delegate = $delegate;
        $this->log = $log;
    }

    public function delete($method, $args = array(), $timeout = self::TIMEOUT)
    {
        $result = $this->delegate->delete($method, $args, $timeout);
        $this->logIfError();
        return $result;
    }

    public function get($method, $args = array(), $timeout = self::TIMEOUT)
    {
        $result = $this->delegate->get($method, $args, $timeout);
        $this->logIfError();
        return $result;
    }

    public function patch($method, $args = array(), $timeout = self::TIMEOUT)
    {
        $result = $this->delegate->patch($method, $args, $timeout);
        $this->logIfError();
        return $result;
    }

    public function post($method, $args = array(), $timeout = self::TIMEOUT)
    {
        $result = $this->delegate->post($method, $args, $timeout);
        $this->logIfError();
        return $result;
    }

    public function put($method, $args = array(), $timeout = self::TIMEOUT)
    {
        $result = $this->delegate->put($method, $args, $timeout);
        $this->logIfError();
        return $result;
    }

    private function logIfError()
    {
        if (!$this->delegate->success()) {
            $this->log->error(
                sprintf(
                    "MailChimp error: %s\n%s",
                    $this->delegate->getLastError(),
                    $this->delegate->getLastResponse()['body']
                )
            );
        }
    }
}
