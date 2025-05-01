<?php

declare(strict_types=1);

namespace Redminesearch\Commands;

use Bluestone\Redmine\Entities\Issue;
use Redminesearch\Services\RedmineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RedmineIssuesCommand extends Command
{
    protected function configure()
    {
        $this->setName('redmine:issues')
             ->setDescription('List Issues from redmine')
             ->setHelp('')
            ->addArgument('date', InputArgument::OPTIONAL, 'Display only this date')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $redmine = RedmineService::factory($GLOBALS['APPCONFIG']['redmine']['url'])->getConnection();

        $filter = [];
        if ($input->getArgument('date')) {
            $filter['created_on'] = $input->getArgument('date');
        }
        $result = $redmine->issue()->all($filter);

        $table = new Table($output);
        $table->setHeaders(['issue', 'date', 'project', 'subject', 'author']);
        /** @var Issue $item */
        foreach ($result->items as $item) {
            $table->addRow([
                $item->id,
                $item->createdOn?->format('Y-m-d'),
                $item->project->name,
                $item->subject, $item->author?->name,
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
