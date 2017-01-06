<?php

namespace Pantheon\TerminusFiler\Commands;

use Pantheon\Terminus\Commands\TerminusCommand;
use Pantheon\Terminus\Site\SiteAwareInterface;
use Pantheon\Terminus\Site\SiteAwareTrait;
use Pantheon\Terminus\Collections\Sites;
use Pantheon\Terminus\Exceptions\TerminusException;

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
    $this->log()->error('Operating system not supported.');
    die;
}

/**
 * Class FilerCommand
 * Opens the Site using an SFTP Client
 */
class FilerCommand extends TerminusCommand implements SiteAwareInterface
{
  
  use SiteAwareTrait;

  /**
   * Opens the Site using an SFTP Client
   *
   * @authorize
   *
   * @command site:filer
   * @aliases filer
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   * @option string $app Application to Open (optional)
   * @option string $bundle Bundle Identifier (optional)
   * @option boolean $persistant Whether to persist the connection (optional)
   * @option string $app_args Application arguments (option)
   *
   * @usage terminus site:filer <site>.<env> --app=<app>
   * @usage terminus site:filer <site>.<env> --bundle=<bundle>
   * @usage terminus site:filer <site>.<env> --app=<app> --persistant=<persistant> --app_args=<app_args>
   */
  public function filer($site_env, $options = ['app' => NULL, 'bundle' => NULL, 'persistant' => false, 'app_args' => NULL]) {
    $supported_apps = unserialize(SUPPORTED_APPS);
    $app = '';
    if (isset($options['app'])) {
      $app = $options['app'];
    }
    if (!in_array($app, $supported_apps)) {
      $this->log()->warning('App not tested.');
    }
    $supported_bundles = array(
      '',
      'com.panic.transmit',
      'ch.sudo.cyberduck',
    );
    $bundle = '';
    if (isset($options['bundle'])) {
      $bundle = $options['bundle'];
    }

    if (!in_array($bundle, $supported_bundles)) {
      $this->log()->warning('Bundle currently not tested.');
    }

    if(!isset($options['bundle']) && !isset($options['app'])){
      $this->log()->error('--app or --bundle flag is required');
      die;
    }

    $persist = false;
    if (isset($options['persistant'])) {
      $persist = $options['persistant'];
    }

    $app_args = '';
    if (isset($options['app_args'])) {
      $app_args = $options['app_args'];
    }

    $type = 'b';
    if ($app) {
      $type = 'a';
    } else {
      $app = $bundle;
    }
    
    list($site, $env) = $this->getSiteEnv($site_env);
    $connection_info = $env->sftpConnectionInfo();
    $domain = $env->id . '-' . $site->get('name') . '.pantheon.io';

    if ($persist) {
      // Additional connection information
      $id = substr(md5($domain), 0, 8) . '-' . $site->id;
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
            $this->log()->error('Operating system not supported.');
            die;
        }
        $bookmark_xml = $this->getBookmarkXml($connection_info);
        if ($this->writeXml($bookmark_file, $bookmark_xml)) {
          $connection = $bookmark_file;
        }
      }
    } else {
      $connection = $connection_info['url'];
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

    $this->log()->notice(
      'Opening {domain} in {app}', array('domain' => $domain, 'app' => $app)
    );

    if ($this->validCommand($app)){
      exec($command);
    }
  }

  /**
   * Opens the Site using Transmit SFTP Client
   *
   * @authorize
   *
   * @command site:filer:transmit
   * @aliases site:filer:panic site:transmit site:panic
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @usage terminus site:filer:transmit <site>.<env>
   * @usage terminus site:filer:panic <site>.<env>
   * @usage terminus site:transmit <site>.<env>
   * @usage terminus site:panic <site>.<env>
   */
  public function transmit($site_env) {
    if (OS != 'DAR') {
      $this->log()->error('Operating system not supported.');
      die;
    }
    $this->filer($site_env, ['bundle' => 'com.panic.transmit']);
  }

  /**
   * Opens the Site using Cyberduck SFTP Client
   *
   * @authorize
   *
   * @command site:filer:cyberduck
   * @aliases site:filer:duck site:cyberduck site:duck
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @usage terminus site:filer:cyberduck <site>.<env>
   * @usage terminus site:filer:duck <site>.<env>
   * @usage terminus site:cyberduck <site>.<env>
   * @usage terminus site:duck <site>.<env>
   */
  public function cyberduck($args, $assoc_args) {
    switch (OS) {
      case 'DAR':
        $assoc_args['bundle'] = 'ch.sudo.cyberduck';
          break;
      case 'WIN':
        $assoc_args['app'] = CYBERDUCK;
        $assoc_args['persistant'] = true;
          break;
      case 'LIN':
      default:
        $this->log()->error('Operating system not supported.');
        die;
    }
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using FileZilla SFTP Client
   *
   * @authorize
   *
   * @command site:filer:filezilla
   * @aliases site:filer:zilla site:filezilla site:zilla
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @usage terminus site:filer:filezilla <site>.<env>
   * @usage terminus site:filer:zilla <site>.<env>
   * @usage terminus site:filezilla <site>.<env>
   * @usage terminus site:zilla <site>.<env>
   */
  public function filezilla($args, $assoc_args) {
    $assoc_args['app'] = FILEZILLA;
    $assoc_args['app_args'] = '-l ask';
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using BitKinex SFTP Client
   *
   * @authorize
   *
   * @command site:filer:bitkinex
   * @aliases site:filer:bit site:bitkinex site:bit
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @usage terminus site:filer:bitkinex <site>.<env>
   * @usage terminus site:filer:bit <site>.<env>
   * @usage terminus site:bitkinex <site>.<env>
   * @usage terminus site:bit <site>.<env>
   */
  public function bitkinex($args, $assoc_args) {
    if (!defined(PHP_WINDOWS_VERSION_MAJOR)) {
      $this->log()->error('Operating system not supported.');
      die;
    }
    $assoc_args['app'] = BITKINEX;
    $assoc_args['app_args'] = 'browse';
    $this->filer($args, $assoc_args);
  }

  /**
   * Opens the Site using WinSCP SFTP Client
   *
   * @authorize
   *
   * @command site:filer:winscp
   * @aliases site:filer:scp site:winscp site:scp
   *
   * @param string $site_env Site & environment in the format `site-name.env`
   *
   * @usage terminus site:filer:winscp <site>.<env>
   * @usage terminus site:filer:scp <site>.<env>
   * @usage terminus site:winscp <site>.<env>
   * @usage terminus site:scp <site>.<env>
   */
  public function winscp($args, $assoc_args) {
    if (!defined(PHP_WINDOWS_VERSION_MAJOR)) {
      $this->log()->error('Operating system not supported.');
      die;
    }
    $assoc_args['app'] = WINSCP;
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
  <string>{$ci['host']}</string>
  <key>Port</key>
  <string>{$ci['port']}</string>
  <key>Username</key>
  <string>{$ci['username']}</string>
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
      $this->log()->error($e->getMessage());
      die;
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
          case 'Cyberduck':
          case 'ch.sudo.cyberduck':
            $file = '/Applications/Cyberduck.app/Contents/MacOS/Cyberduck';
              break;
          case 'Transmit':
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
      $this->log()->error("$file does not exist.");
      die;
      return false;
    }
    return true;
  }

}
