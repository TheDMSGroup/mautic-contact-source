<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use MauticPlugin\MauticContactSourceBundle\Model\Cache;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI Command : Performs maintenance tasks required by the source plugin.
 *
 * php app/console mautic:contactsource:maintenance
 */
class MaintenanceCommand extends ModeratedCommand
{
    /**
     * Maintenance command line task.
     */
    protected function configure()
    {
        $this->setName('mautic:contactsource:maintenance')
            ->setDescription('Performs maintenance tasks required by the source plugin.');

        parent::configure();
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container  = $this->getContainer();
        $translator = $container->get('translator');
        if (!$this->checkRunStatus($input, $output)) {
            return 0;
        }

        /** @var Cache $cacheModel */
        $cacheModel = $container->get('mautic.contactsource.model.cache');
        $output->writeln(
            '<info>'.$translator->trans(
                'mautic.contactsource.maintenance.running'
            ).'</info>'
        );
        $deleted = $cacheModel->getRepository()->deleteExpired();
        $output->writeln('Deleted '.$deleted.' expired cache entries.');
        $output->writeln(
            '<info>'.$translator->trans(
                'mautic.contactsource.maintenance.complete'
            ).'</info>'
        );

        $this->completeRun();

        return 0;
    }
}
