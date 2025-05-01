<?php

declare(strict_types=1);

namespace Redminesearch\Commands;

use Bluestone\Redmine\Entities\Issue;
use Redminesearch\Services\EmbeddingService;
use Redminesearch\Services\RedisService;
use Redminesearch\Services\RedmineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class RedmineSyncCommand extends Command
{
    protected function configure()
    {
        $this->setName('redmine:sync')
            ->setDescription('Get Issues from redmine, create embeddings and write them into Redis')
            ->addArgument('from', InputArgument::OPTIONAL, 'The timestamp from which issues should be synced, in the Format [YYYY-MM-DD|lastsync|all]', 'lastsync')
            ->addOption('recreate', null, InputOption::VALUE_NONE, 'force recreation of the index')
            ->addOption('clearcache', null, InputOption::VALUE_NONE, 'clear the cache')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $logger = new ConsoleLogger($output);

        $redis = new RedisService(mylogger: $logger);

        if ($input->getOption('recreate')) {
            $redis->createIndex();
        }

        $embedding = new EmbeddingService();
        $embedding->setLogger($logger);
        if ($input->getOption('clearcache')) {
            $embedding->clearCache();
        }

        $redmine = RedmineService::factory($GLOBALS['APPCONFIG']['redmine']['url'])->getConnection();

        $filter = [
            'status_id' => '*',
            'offset' => 0,
        ];
        switch ($input->getArgument('from')) {
            case 'lastsync':
                $lastsync = $redis->getClient()->get($redis->getIndexname() . ':lastsync');
                if ($lastsync) {
                    $filter['created_on'] = '>=' . $lastsync;
                }
                break;
            case 'all':
                break;
            default:
                $filter['created_on'] = '>=' . $input->getArgument('from');
                break;
        }
        $result = $redmine->issue()->all($filter);

        $count = 0;
        while ($result->total > $count) {
            $logger->info('Running issues {offset}', $filter);
            /** @var Issue $issue */
            foreach ($result->items as $issue) {
                try {
                    $set = [
                        'uid'     => (int)$issue->id,
                        'subject'   => $issue->subject,
                        'content'   => $issue->subject . "\n" . $issue->description,
                        'embedding' => $embedding->getEmbeddings(
                            $redis->getVectorkey() . ':' . $issue->id,
                            $issue->subject . "\n" . $issue->description
                        ),
                    ];
                    $redis->store($set);
                } catch (\Exception $e) {
                    $logger->error($e->getMessage());
                }
            }
            $count = $count + $result->limit;
            $filter['offset'] = $count;
            $result = $redmine->issue()->all($filter);

        }
        if ($input->getArgument('from') === 'lastsync' || $input->getArgument('from') === 'all') {
            $redis->getClient()->set($redis->getIndexname() . ':lastsync', date('Y-m-d'));
        }
        return Command::SUCCESS;
    }
}
