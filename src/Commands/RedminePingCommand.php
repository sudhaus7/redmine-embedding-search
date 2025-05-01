<?php

declare(strict_types=1);

namespace Redminesearch\Commands;

use Bluestone\Redmine\Entities\Response;

use GuzzleHttp\Exception\RequestException;
use Redminesearch\Services\RedmineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RedminePingCommand extends Command
{
    protected function configure()
    {
        $this->setName('redmine:ping')
            ->setDescription('Tool to test the Connection to Redmine')
            ->setHelp('This command allows you to test the Connection to Redmine')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $redmine = RedmineService::factory($GLOBALS['APPCONFIG']['redmine']['url'])->getConnection();

        try {
            $ping = $redmine->issue()->all([
                'offset' => 0,
                'limit' => 1,
                'status_id' => '*',
                'sort' => 'created_on:desc',
            ]);
            if ($ping instanceof Response && $ping->statusCode === 200) {
                $table = new Table($output);
                $table->addRows([
                    ['Latest Issue ID', $ping->items[0]->id],
                    ['Total Number of Issues', $ping->total],
                ]);
                $table->render();

            } else {
                $output->writeln('<error>No Issues found</error>');
            }
        } catch (RequestException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }

        return Command::SUCCESS;
    }

}
