<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\RedisInfoCommand.
 */

namespace Docker\Drupal\Command\Redis;

use Docker\Drupal\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Docker\Drupal\Style\DruDockStyle;
use Docker\Drupal\Extension\ApplicationContainerExtension;

/**
 * Class RedisInfoCommand
 * @package Docker\Drupal\Command\redis
 */
class RedisInfoCommand extends Command {
  protected function configure() {
    $this
      ->setName('redis:info')
      ->setDescription('Get Redis running config information')
      ->setHelp("This command will output current running REDIS instance information/config.");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $application = new Application();
    $container_application = new ApplicationContainerExtension();
    $io = new DruDockStyle($input, $output);

    $io->section("REDIS ::: Info");

    if ($config = $application->getAppConfig($io)) {
      $appname = $config['appname'];
    }

    if ($container_application->checkForAppContainers($appname, $io)) {
      $command = $container_application->getComposePath($appname, $io) . 'exec -T redis redis-cli info';
      $application->runcommand($command, $io);
    }
  }
}