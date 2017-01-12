<?php

namespace Terminus\Commands;

use Terminus\Collections\Sites;
use Terminus\Commands\TerminusCommand;
use Terminus\Exceptions\TerminusException;
use Terminus\Utils;

// Get environment variables, if available
$bitkinex = getenv('TERMINUS_FILER_BITKINEX_LOC');
$cyberduck = getenv('TERMINUS_FILER_CYBERDUCK_LOC');
$filezilla = getenv('TERMINUS_FILER_FILEZILLA_LOC');
$winscp = getenv('TERMINUS_FILER_WINSCP_LOC');
// Operating system specific checks
define('OS', strtoupper(substr(PHP_OS, 0, 3)));
switch (OS) {
  case 'DAR':
  case 'LIN':
    if (!$filezilla) {
      $filezilla = 'filezilla';
    }
    define('FILEZILLA', $filezilla);
    define(
      'SUPPORTED_APPS', serialize(
        array('', FILEZILLA)
      )
    );
      break;
  case 'WIN':
    $program_files = 'Program Files';
    $arch = getenv('PROCESSOR_ARCHITECTURE');
    if ($arch == 'x86') {
      $program_files = 'Program Files (x86)';
    }
    if (!$bitkinex) {
      $bitkinex = "\\{$program_files}\\BitKinex\\bitkinex.exe";
    }
    if (!$cyberduck) {
      $cyberduck = "\\{$program_files}\\Cyberduck\\Cyberduck.exe";
    }
    if (!$filezilla) {
      $filezilla = "\\{$program_files}\\FileZilla FTP Client\\filezilla.exe";
    }
    if (!$winscp) {
      $winscp = "\\{$program_files}\\WinSCP\\WinSCP.exe";
    }
    define('BITKINEX', "\"$bitkinex\"");
    define('CYBERDUCK', "\"$cyberduck\"");
    define('FILEZILLA', "\"$filezilla\"");
    define('WINSCP', "\"$winscp\"");
    define(
      'SUPPORTED_APPS', serialize(
        array(
          '',
          BITKINEX,
          CYBERDUCK,
          FILEZILLA,
          WINSCP,
        )
      )
    );
      break;
  default:
    $this->failure('Operating system not supported.');
}

/**
 * Opens the Site using an SFTP Client
 *
 * @command site
 */
class FilerCommand extends TerminusCommand {
  /**
   * Object constructor
   *
   * @param array $options
   * @return FilerCommand
   */

  public function __construct(array $options = []) {
    $options['require_login'] = true;
    parent::__construct($options);
    $this->sites = new Sites();
  }

  /**
   * Opens the Site using an SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * [--a=<app>]
   * : Application to Open (optional)
   *
   * [--b=<bundle>]
   * : Bundle Identifier (optional)
   *
   * [--p=<true|false>]
   * : Whether to persist the connection
   *
   * ## EXAMPLES
   *  terminus site filer --site=test
   *
   * @subcommand filer
   * @alias file
   */
  public function filer($args, $assoc_args) {
    $site = $this->sites->get(
      $this->input()->siteName(array('args' => $assoc_args))
    );

    $supported_apps = unserialize(SUPPORTED_APPS);
    $app = '';
    if (isset($assoc_args['a'])) {
      $app = $assoc_args['a'];
    }
    if (!in_array($app, $supported_apps)) {
      $this->failure('App not supported.');
    }

    $supported_bundles = array(
      '',
      'com.panic.transmit',
      'ch.sudo.cyberduck',
    );
    $bundle = '';
    if (isset($assoc_args['b'])) {
      $bundle = $assoc_args['b'];
    }
    if (!in_array($bundle, $supported_bundles)) {
      $this->failure('Bundle not supported.');
    }

    $persist = false;
    if (isset($assoc_args['p'])) {
      $persist = $assoc_args['p'];
    }

    $app_args = '';
    if (isset($assoc_args['app_args'])) {
      $app_args = $assoc_args['app_args'];
    }

    $type = 'b';
    if ($app) {
      $type = 'a';
    } else {
      $app = $bundle;
    }

    $env = $this->input()->env(array('args' => $assoc_args, 'site' => $site));
    $environment = $site->environments->get($env);
    $connection_info = $environment->connectionInfo();
    $domain = $env . '-' . $site->get('name') . '.pantheon.io';

    if ($persist) {
      // Additional connection information
      $id = substr(md5($domain), 0, 8) . '-' . $site->get('id');
      $connection_info['id'] = $id;
      $connection_info['domain'] = $domain;
      $connection_info['timestamp'] = time();
      // Persistent Cyberduck bookmark instance
      if (stripos($app, 'cyberduck')) {
        switch (OS) {
          case 'WIN':
            $bookmark_file = getenv('HOMEPATH')
              . '\\AppData\\Roaming\\Cyberduck\\Bookmarks\\' . $id . '.duck';
              break;
          default:
            $this->failure('Operating system not supported.');
        }
        $bookmark_xml = $this->getBookmarkXml($connection_info);
        if ($this->writeXml($bookmark_file, $bookmark_xml)) {
          $connection = $bookmark_file;
        }
      }
    } else {
      $connection = $connection_info['sftp_url'];
    }

    // Operating system specific checks
    switch (OS) {
      case 'DAR':
        if ($app_args) {
          $app_args = "--args $app_args";
        }
        $connect = 'open -%s %s %s %s';
        $command = sprintf($connect, $type, $app, $app_args, $connection);
          break;
      case 'LIN':
        $connect = '%s %s %s %s';
        $redirect = '> /dev/null 2> /dev/null &';
        $command = sprintf($connect, $app, $app_args, $connection, $redirect);
          break;
      case 'WIN':
        $connect = 'start "" /b %s %s %s';
        $command = sprintf($connect, $app, $app_args, $connection);
          break;
    }

    $this->log()->info(
      'Opening {domain} in {app}', array('domain' => $domain, 'app' => $app)
    );

    // Wake the Site
    $environment->wake();

    // Open the Site in app/bundle
    $this->log()->info($command);
    if ($this->validCommand($app)) {
      exec($command);
    }
  }

  /**
   * Opens the Site using Transmit SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * ## EXAMPLES
   *  terminus site transmit --site=test
   *
   * @subcommand transmit
   * @alias panic
   */
  public function transmit($args, $assoc_args) {
    if (OS != 'DAR') {
      $this->failure('Operating system not supported.');
    }
    $assoc_args['b'] = 'com.panic.transmit';
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using Cyberduck SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * ## EXAMPLES
   *  terminus site cyberduck --site=test
   *
   * @subcommand cyberduck
   * @alias duck
   */
  public function cyberduck($args, $assoc_args) {
    switch (OS) {
      case 'DAR':
        $assoc_args['b'] = 'ch.sudo.cyberduck';
          break;
      case 'WIN':
        $assoc_args['a'] = CYBERDUCK;
        $assoc_args['p'] = true;
          break;
      case 'LIN':
      default:
        $this->failure('Operating system not supported.');
    }
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using FileZilla SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * ## EXAMPLES
   *  terminus site filezilla --site=test
   *
   * @subcommand filezilla
   * @alias zilla
   */
  public function filezilla($args, $assoc_args) {
    $assoc_args['a'] = FILEZILLA;
    $assoc_args['app_args'] = '-l ask';
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using BitKinex SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * ## EXAMPLES
   *  terminus site bitkinex --site=test
   *
   * @subcommand bitkinex
   * @alias bit
   */
  public function bitkinex($args, $assoc_args) {
    if (!Utils\isWindows()) {
      $this->failure('Operating system not supported.');
    }
    $assoc_args['a'] = BITKINEX;
    $assoc_args['app_args'] = 'browse';
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using WinSCP SFTP Client
   *
   * ## OPTIONS
   *
   * [--site=<site>]
   * : Site to Use
   *
   * [--env=<env>]
   * : Environment
   *
   * ## EXAMPLES
   *  terminus site winscp --site=test
   *
   * @subcommand winscp
   * @alias scp
   */
  public function winscp($args, $assoc_args) {
    if (!Utils\isWindows()) {
      $this->failure('Operating system not supported.');
    }
    $assoc_args['a'] = WINSCP;
    $this->filer($args, $assoc_args);
  }

  /**
   * XML for Cyberduck bookmark file
   *
   * @param array Connection information
   * @return string XML bookmark file content
   */
  private function getBookmarkXml($ci) {
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>Protocol</key>
  <string>sftp</string>
  <key>Nickname</key>
  <string>{$ci['domain']}</string>
  <key>UUID</key>
  <string>{$ci['id']}</string>
  <key>Hostname</key>
  <string>{$ci['sftp_host']}</string>
  <key>Port</key>
  <string>{$ci['git_port']}</string>
  <key>Username</key>
  <string>{$ci['sftp_username']}</string>
  <key>Path</key>
  <string></string>
  <key>Access Timestamp</key>
  <string>{$ci['timestamp']}</string>
</dict>
</plist>
XML;
  }

  /**
   * Write the XML to the configuration file
   *
   * @param string $file XML configuration file
   * @param string $data XML configuration data
   * @return bool True if writing to the file was successful
   */
  private function writeXml($file, $data) {
    try {
      $handle = fopen($file, "w");
      fwrite($handle, $data);
      fclose($handle);
    } catch (Exception $e) {
      $this->failure($e->getMessage());
      return false;
    }
    return true;
  }

  /**
   * Executable file validation
   *
   * @param string $file Full path to the executable file
   * @return bool True or false based on the file execution status
   */
  private function validCommand($file = '') {
    if (!$file) {
      return false;
    }
    switch (OS) {
      case 'DAR':
        switch ($file) {
          case 'ch.sudo.cyberduck':
            $file = '/Applications/Cyberduck.app/Contents/MacOS/Cyberduck';
              break;
          case 'com.panic.transmit':
            $file = '/Applications/Transmit.app/Contents/MacOS/Transmit';
              break;
          case 'filezilla':
            $file = '/Applications/FileZilla.app/Contents/MacOS/filezilla';
              break;
        }
      case 'LIN':
        switch ($file) {
          case 'filezilla':
            $file = '/usr/bin/filezilla';
              break;
        }
          break;
    }
    exec("ls $file", $output);
    if (empty($output)) {
      $this->failure("$file does not exist.");
      return false;
    }
    return true;
  }

}
