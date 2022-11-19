<?php

namespace GetDkan\PinnedBecause;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "pin" command class.
 */
class ComposerPinCommand extends BaseCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('pin:pin')
      ->setAliases(['pin'])
      ->setDescription('Pin a specific version of a package based on the lock file.')
      ->addArgument('package', NULL, 'The package to pin.')
      ->addOption('because', NULL, InputOption::VALUE_REQUIRED, 'Short explanation of why the package is pinned.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $handler = new Handler($this->getComposer(), $this->getIO());
    return $handler->pin(
      $input->getArgument('package'),
      $input->getOption('because')
    );
  }

}
