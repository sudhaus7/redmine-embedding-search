<?php

declare(strict_types=1);

namespace Redminesearch\Commands;

use Bluestone\Redmine\Entities\Issue;
use Redminesearch\Services\EmbeddingService;
use Redminesearch\Services\RedisService;
use Redminesearch\Services\RedmineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CheckIssuesCommand extends Command
{
    protected function configure()
    {
        $this->setName('check')
             ->setDescription('Check issues against history')
             ->setHelp('')
            ->addOption('onlysubject', null, InputOption::VALUE_NONE, 'Only compare the subject instead of the whole Ticket body')
             ->addArgument('date', InputArgument::OPTIONAL, 'Display only this date', date('Y-m-d'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $output->writeln('');
        $logger = new ConsoleLogger($output);

        $redmine = RedmineService::factory($GLOBALS['APPCONFIG']['redmine']['url'])->getConnection();
        $redis = new RedisService(mylogger: $logger);
        $redis->setLogger($logger);

        $embeddingService = new EmbeddingService();
        $embeddingService->setLogger($logger);

        $filter = [
            'status_id' => '*',
            'offset' => 0,
            'created_on' => $input->getArgument('date'),
        ];

        $result = $redmine->issue()->all($filter);
        $count = 0;
        while ($result->total > $count) {
            $logger->info('Running issues {offset}', $filter);
            /** @var Issue $issue */
            foreach ($result->items as $issue) {

                $text = $issue->subject;
                if (!$input->getOption('onlysubject')) {
                    $text .= "\n" . $issue->description;
                }

                $embedding = $embeddingService->getEmbeddings(
                    $redis->getVectorkey() . ':' . $issue->id,
                    $text
                );

                $searchArguments = new \Predis\Command\Argument\Search\SearchArguments();
                $searchArguments
                    ->dialect('2')
                    ->sortBy('__vector_score')
                    ->limit(1, 5)
                    ->addReturn(3, '__vector_score', 'uid', 'subject')
                    ->params([ 'query_vector', pack('f' . $embeddingService->getDimensions(), ... $embedding) ]);

                $output->writeln(sprintf('<info>%d: %s [%s/issues/%1$d]</info>', $issue->id, $issue->subject, trim($GLOBALS['APPCONFIG']['redmine']['url'], '/')));

                $results = $redis->normalizeRedisResult(
                    $redis->getClient()->ftsearch(
                        $redis->getIndexname(),
                        '(*)=>[KNN 6 @vector $query_vector]',
                        $searchArguments
                    )
                );
                $table = new Table($output);
                foreach ($results as $key => $searchresult) {
                    $uid = explode(':', $key)[1];
                    $table->addRow([
                        round((float)$searchresult['__vector_score'], 5),
                        $uid,
                        $searchresult['subject'],
                        sprintf('[%s/issues/%d]', trim($GLOBALS['APPCONFIG']['redmine']['url'], '/'), $uid),
                    ]);
                }
                $table->render();
                $output->writeln('');
            }
            $count = $count + $result->limit;
            $filter['offset'] = $count;
            $result = $redmine->issue()->all($filter);

        }
        return Command::SUCCESS;
    }
}
