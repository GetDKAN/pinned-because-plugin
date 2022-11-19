<?php

namespace GetDkan\PinnedBecause;

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Factory;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\BasePackage;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Drupal\Composer\Plugin\Scaffold\Operations\OperationData;
use Drupal\Composer\Plugin\Scaffold\Operations\OperationFactory;
use Drupal\Composer\Plugin\Scaffold\Operations\ScaffoldFileCollection;
use GetDkan\PinnedBecause\ComposerPinCommand;
use Composer\Json\JsonFile;

/**
 * Core class of the plugin.
 *
 * Contains the primary logic which determines the files to be fetched and
 * processed.
 *
 * @internal
 */
class Handler {

  /**
   * The Composer service.
   *
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * Composer's I/O service.
   *
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * The scaffold options in the top-level composer.json's 'extra' section.
   *
   * @var \Drupal\Composer\Plugin\Scaffold\ManageOptions
   */
  protected $manageOptions;

  /**
   * The manager that keeps track of which packages are allowed to scaffold.
   *
   * @var \Drupal\Composer\Plugin\Scaffold\AllowedPackages
   */
  protected $manageAllowedPackages;

  /**
   * The list of listeners that are notified after a package event.
   *
   * @var \Drupal\Composer\Plugin\Scaffold\PostPackageEventListenerInterface[]
   */
  protected $postPackageListeners = [];

  /**
   * @var \Composer\Json\JsonFile
   */
  protected $jsonFile;

  /**
   * Handler constructor.
   *
   * @param \Composer\Composer $composer
   *   The Composer service.
   * @param \Composer\IO\IOInterface $io
   *   The Composer I/O service.
   */
  public function __construct(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
  }

  /**
   * Perform a pin.
   *
   * @param string $package
   * @param string $because
   *
   * @return integer
   */
  public function pin(string $package, string $because) {
    // Is this package already pinned?
    if ($already_pinned_because = $this->getPinReasonForPackage($package)) {
      $this->io->write($package . ' was already pinned because: ' . $already_pinned_because, TRUE);
      return 1;
    }

    // Do we require this package directly in our project?
//    $all_project_require = array_merge($package_info['require'] ?? [], $package_info['require-dev'] ?? []);
    $package_in_project = array_key_exists(
      $package,
      array_merge($package_info['require'] ?? [], $package_info['require-dev'] ?? [])
    );

    $this->jsonFile = new JsonFile(Factory::getComposerFile(), NULL, $this->io);

    $package_info = $this->jsonFile->read();
    $changed = [];

    // Get lock file version of package.
    $locked_constraint = FALSE;
    if (!$locked_constraint = $this->getLockedVersionOf($package)) {
      $this->io->write('Unable to find a locked version of ' . $package, TRUE);
      return 1;
    }

    // Add the pinned reason.
    if (($package_info['extra']['pinned-because'][$package] ?? NULL) !== $because) {
      $package_info['extra']['pinned-because'][$package] = $because;
      $changed['because'] = $because;
    }
    // Add the pinned constraint.
    if ($package_in_project) {
      foreach (['require', 'require-dev'] as $key) {
        if (($package_info[$key][$package] ?? NULL) !== $locked_constraint) {
          $package_info[$key][$package] = $locked_constraint;
          $changed['package'] = $locked_constraint;
          break;
        }
      }
    }
    else {
      // Package not in project.
      $package_info['require'][$package] = $locked_constraint;
      $changed['package'] = $locked_constraint;
    }

    if ($changed) {
      $this->jsonFile->write($package_info);
      $this->io->write('Pinned ' . $package . ':' . $changed['package'] . ' because: ' . $changed['because'], TRUE);
      $this->io->write('It\'s highly recommended that you run `composer update ' . $package . '` in order to ensure this is meaningful and good.', true);
      return 0;
    }
    $this->io->write('Unable to perform pin.', TRUE);
    return 1;
  }

  protected function addPinnedReason(string $pin_package, string $reason): bool {
    $package = $this->composer->getPackage();
    $extra = $package->getExtra();
    $pinned_because = $extra['pinned-because'] ?? [];
    $pinned_because[$pin_package] = $reason;
    $extra['pinned-because'] = $pinned_because;
    $package->setExtra($extra);

    return TRUE;
  }

  protected function addPinnedVersion($pin_package, $constraint) {
    $base_packages = $this->composer->getRepositoryManager()
      ->getLocalRepository()->getPackages();
    $stuff = [];
    foreach ($base_packages as $base_package) {
      $stuff[$base_package->getName()] = $base_package->getNames();
    }
    return TRUE;
    throw new \Exception(print_r($stuff, TRUE));

    //    return TRUE;
    $package = $this->composer->getPackage();
    // Is the pin package a dev requirement?
    // Note this is imperfect because we could be trying to pin a dependency
    // of a dev requirement which is not already in the project file.
    $dev_package_names = $this->composer->getRepositoryManager()
      ->getLocalRepository()
      ->getDevPackageNames();
    $is_dev_require = in_array($pin_package, $dev_package_names);

    // Is the pin package a dependency?
    /*    $is_dependency = in_array(
          $pin_package,
          array_merge($dev_package_names,)
        );*/


    return FALSE;
  }

  /**
   * @param array $extra
   *
   * @return false|string
   */
  protected function getPinReasonForPackage(string $package) {
    $extra = $this->composer->getPackage()->getExtra();
    if ($because = $extra['pinned-because'][$package] ?? FALSE) {
      return $because;
    }
    return FALSE;
  }

  /**
   * Find the locked version for the given package.
   *
   * @param string $pin_package_name
   *   Package name to find.
   *
   * @return false|string
   *   String containing the version constraint for the given package,
   *   or FALSE if it was not found in the lock.
   */
  protected function getLockedVersionOf(string $pin_package_name) {
    $constraint = FALSE;
    $stuff = [];
    $lock = $this->composer->getLocker()->getLockData();
    foreach ($lock['packages'] ?? [] as $package) {
      $stuff[$package['name']] = $package['name'];
      if (($package['name'] ?? '') === $pin_package_name) {
        $constraint = $package['version'] ?? FALSE;
        if ($constraint) {
          return $constraint;
        }
      }
    }

//    throw new \Exception(print_r($stuff, true));
    return FALSE;
  }

}
