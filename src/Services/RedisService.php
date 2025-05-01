<?php

declare(strict_types=1);

namespace Redminesearch\Services;

use Predis\Client;
use Predis\Command\Argument\Search\CreateArguments;
use Predis\Command\Argument\Search\SchemaFields\NumericField;
use Predis\Command\Argument\Search\SchemaFields\TextField;
use Predis\Command\Argument\Search\SchemaFields\VectorField;
use Predis\Response\ServerException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use function json_encode;

class RedisService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected Client $client;

    protected string $indexname;

    protected string $vectorkey;

    protected int $dimensions = 1536;

    public function __construct(?Client $client = null, ?string $vectorkey = null, ?LoggerInterface $mylogger = null)
    {
        if ($mylogger instanceof LoggerInterface) {
            $this->logger = $mylogger;
        }
        $this->vectorkey = $vectorkey ?? $GLOBALS['APPCONFIG']['redis']['vectorkeyname'];
        if (isset($GLOBALS['APPCONFIG']['openai']['dimensions'])) {
            $this->dimensions = (int)$GLOBALS['APPCONFIG']['openai']['dimensions'];
        }

        $this->indexname = 'idx:' . $this->vectorkey;

        if ($client instanceof Client) {
            $this->client = $client;
        } else {
            $connect = $GLOBALS['APPCONFIG']['redis']['connect'] ?? [
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 0,
            ];
            $this->logger->debug('Try Redis at {host}:{port}/{database}', $connect);
            $this->client = new Client($connect);
            $this->client->connect();
        }

        $this->logger->info('Redis connected {host}:{port}/{database}', [
            'host' => $this->client->getConnection()->getParameters()->host,
            'port' => $this->client->getConnection()->getParameters()->port,
            'database' => $this->client->getConnection()->getParameters()->database,
        ]);
        try {
            $info = $this->client->ftinfo($this->indexname);
            //print_r($info);
        } catch (ServerException $e) {
            $this->createIndex();
        }

    }

    public function createIndex()
    {

        try {
            $this->client->ftdropindex($this->indexname);

            $this->logger->info('Dropped Index {indexname}', ['indexname' => $this->indexname]);
        } catch (ServerException $e) {
            // it is ok
        }
        $this->logger->info('Creating Index {indexname}', ['indexname' => $this->indexname]);
        $fields = [
            new NumericField('$.issue', 'uid'),
            new TextField('$.subject', 'subject'),
            new TextField('$.text', 'text'),
            new VectorField('$.embedding', 'FLAT', ['TYPE', 'FLOAT32', 'DIM', $this->dimensions, 'DISTANCE_METRIC', 'COSINE'], 'vector'),
        ];

        $arguments = new CreateArguments();
        $arguments->on('JSON')->prefix([$this->vectorkey . ':'])->score(1.0);

        $status = $this->client->ftcreate($this->indexname, $fields, $arguments);

    }

    public function store(array $set): void
    {
        $this->logger->info('store {uid}', $set);
        $this->client->jsonset($this->vectorkey . ':' . $set['uid'], '$', json_encode($set));
    }

    public function get(int $uid): array
    {
        $result = $this->client->jsonget($this->vectorkey . ':' . $uid);
        return json_decode($result);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getIndexname(): string
    {
        return $this->indexname;
    }

    public function getVectorkey(): string
    {
        return $this->vectorkey;
    }

}
