<?php

declare(strict_types=1);

namespace Redminesearch\Services;

use OpenAI;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class EmbeddingService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const EMBEDDINGMODEL = 'text-embedding-3-small';

    protected AbstractAdapter $cache;

    protected int $dimensions = 1536;

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->cache = new FilesystemAdapter(str_replace('\\', '-', __CLASS__), 0, APP_PATH . '/.cache');
        if (isset($GLOBALS['APPCONFIG']['openai']['dimensions'])) {
            $this->dimensions = (int)$GLOBALS['APPCONFIG']['openai']['dimensions'];
        }

    }

    /**
     * @param int $id
     * @param $title
     * @param $content
     *
     * @return array{title:float[],content:float[],titlecontent:float[]}
     * @throws InvalidArgumentException
     */
    public function getEmbeddings(int|string $id, string $content): array
    {
        $model = $GLOBALS['APPCONFIG']['openai']['embeddingmodel'] ?? self::EMBEDDINGMODEL;

        $key = sha1($model . '-' . (string)$id);
        return $this->cache->get($key, function (ItemInterface $item) use ($model, $content): array {

            $result = [];

            $clientFactory = OpenAI::factory()
                ->withHttpHeader('OpenAI-Beta', 'assistants=v2');

            if (isset($GLOBALS['APPCONFIG']['openai']['key'])) {
                $clientFactory->withApiKey($GLOBALS['APPCONFIG']['openai']['key']);
            }
            if (isset($GLOBALS['APPCONFIG']['openai']['baseuri'])) {
                $clientFactory->withBaseUri($GLOBALS['APPCONFIG']['openai']['baseuri']);
            }
            $client = $clientFactory->make();

            $embedding = $client->embeddings()->create([
                'encoding_format' => 'float',
                'dimensions' => $this->dimensions,
                'model' => $model,
                'input' => $content,
            ]);

            return $embedding->toArray()['data'][0]['embedding'];
        });

    }

}
