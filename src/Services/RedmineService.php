<?php

declare(strict_types=1);

namespace Redminesearch\Services;

use Bluestone\Redmine\Client;
use Bluestone\Redmine\HttpHandler;

class RedmineService
{
    protected string $host;
    protected ?string $key;
    public function __construct(string $host, string $key = null)
    {
        $this->host = $host;
        if ($key === null && isset($GLOBALS['APPCONFIG']['redmine']['key'])) {
            $this->key = $GLOBALS['APPCONFIG']['redmine']['key'];
        }
    }

    public function getConnection()
    {
        if ($this->key) {
            $httpHandler = new HttpHandler($this->host, $this->key);
        } else {
            $httpHandler = new HttpHandler($this->host);
        }
        return new Client($httpHandler);
    }

    public static function factory(string $host, string $key = null)
    {
        return new self($host, $key);
    }

}
