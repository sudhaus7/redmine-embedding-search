<?php

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

    public function __construct()
    {
        $this->logger = new NullLogger();
        $this->cache = new FilesystemAdapter(__CLASS__, 0, APP_PATH . '/.cache');
    }

    /**
     * @param int $id
     * @param $title
     * @param $content
     *
     * @return array{title:float[],content:float[],titlecontent:float[]}
     * @throws InvalidArgumentException
     */
    public function getEmbeddings(int $id, string $title, string $content): array
    {
        $model = $GLOBALS['APPCONFIG']['openai']['embeddingmodel'] ?? self::EMBEDDINGMODEL;

        $key = sha1($model . '-' . (string)$id);
        return $this->cache->get($key, function (ItemInterface $item) use ($model, $title, $content): array {

            $result = [];

            $client = OpenAI::client($GLOBALS['APPCONFIG']['openai']['key']);
            $embedding = $client->embeddings()->create([
                'encoding_format' => 'float',
                'model' => $model,
                'input' => $title,
            ]);
            $result['title'] = $embedding->toArray()['data'][0]['embedding'];
            $embedding = $client->embeddings()->create([
                'encoding_format' => 'float',
                'model' => $model,
                'input' => $content,
            ]);
            $result['content'] = $embedding->toArray()['data'][0]['embedding'];
            $embedding = $client->embeddings()->create([
                'encoding_format' => 'float',
                'model' => $model,
                'input' => $title . "\n" . $content,
            ]);
            $result['titlecontent'] = $embedding->toArray()['data'][0]['embedding'];
            return $result;
        });

    }

}
