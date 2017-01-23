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
$sftp = getenv('TERMINUS_FILER_SFTP_LOC');

// Operating system specific checks
define('OS', strtoupper(substr(PHP_OS, 0, 3)));
switch (OS) {
    case 'DAR':
    case 'LIN':
        if (!$filezilla) {
            $filezilla = 'filezilla';
        }
        if (!$sftp) {
            $sftp = 'sftp';
        }
        define('FILEZILLA', $filezilla);
        define('SFTP', $sftp);
        define(
            'SUPPORTED_APPS', serialize(
                array(
                    '',
                    FILEZILLA,
                    SFTP,
                )
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
        if (!$sftp) {
            $sftp = 'sftp';
        }
        define('BITKINEX', "\"$bitkinex\"");
        define('CYBERDUCK', "\"$cyberduck\"");
        define('FILEZILLA', "\"$filezilla\"");
        define('WINSCP', "\"$winscp\"");
        define('SFTP', "\"$sftp\"");
        define(
            'SUPPORTED_APPS', serialize(
                array(
                    '',
                    BITKINEX,
                    CYBERDUCK,
                    FILEZILLA,
                    WINSCP,
                    SFTP,
                )
            )
        );
            break;
    default:
        throw new TerminusException('Operating system not supported.');
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
            throw new TerminusException('--app or --bundle flag is required.');
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
                        throw new TerminusException('Operating system not supported.');
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
                if ($app == 'sftp') {
                    $command = "{$connection_info['command']} 2>&1";
                } else {
                    $command = sprintf($connect, $type, $app, $app_args, $connection);
                }
                    break;
            case 'LIN':
                $connect = '%s %s %s %s';
                $redirect = '> /dev/null 2> /dev/null &';
                if ($app == 'sftp') {
                    $command = "{$connection_info['command']} 2>&1";
                } else {
                    $command = sprintf($connect, $app, $app_args, $connection, $redirect);
                }
                    break;
            case 'WIN':
                $connect = 'start "" /b %s %s %s';
                if ($app == 'sftp') {
                    $command = "{$connection_info['command']}";
                } else {
                    $command = sprintf($connect, $app, $app_args, $connection);
                }
                    break;
        }

        $this->log()->notice(
            'Opening {domain} in {app}', array('domain' => $domain, 'app' => $app)
        );

        if ($this->validCommand($app)){
            if ($app == 'sftp') {
                passthru($command);
            } else {
                exec($command);
            }
        }
    }

    /**
     * Opens the Site using Transmit SFTP Client
     *
     * @authorize
     *
     * @command site:filer:transmit
     * @aliases site:filer:panic site:transmit site:panic transmit panic
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:transmit <site>.<env>
     * @usage terminus site:filer:panic <site>.<env>
     * @usage terminus site:transmit <site>.<env>
     * @usage terminus site:panic <site>.<env>
     * @usage terminus transmit <site>.<env>
     * @usage terminus panic <site>.<env>
     */
    public function transmit($site_env) {
        if (OS != 'DAR') {
            throw new TerminusException('Operating system not supported.');
        }
        $this->filer($site_env, ['bundle' => 'com.panic.transmit']);
    }

    /**
     * Opens the Site using Cyberduck SFTP Client
     *
     * @authorize
     *
     * @command site:filer:cyberduck
     * @aliases site:filer:duck site:cyberduck site:duck cyberduck duck
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:cyberduck <site>.<env>
     * @usage terminus site:filer:duck <site>.<env>
     * @usage terminus site:cyberduck <site>.<env>
     * @usage terminus site:duck <site>.<env>
     * @usage terminus cyberduck <site>.<env>
     * @usage terminus duck <site>.<env>
     */
    public function cyberduck($site_env) {
        switch (OS) {
            case 'DAR':
                $options['bundle'] = 'ch.sudo.cyberduck';
                    break;
            case 'WIN':
                $options['app'] = CYBERDUCK;
                $options['persistant'] = true;
                    break;
            case 'LIN':
            default:
                throw new TerminusException('Operating system not supported.');
        }
        $this->filer($site_env, $options);
    }

    /**
     * Opens the Site using FileZilla SFTP Client
     *
     * @authorize
     *
     * @command site:filer:filezilla
     * @aliases site:filer:zilla site:filezilla site:zilla filezilla zilla
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:filezilla <site>.<env>
     * @usage terminus site:filer:zilla <site>.<env>
     * @usage terminus site:filezilla <site>.<env>
     * @usage terminus site:zilla <site>.<env>
     * @usage terminus filezilla <site>.<env>
     * @usage terminus zilla <site>.<env>
     */
    public function filezilla($site_env) {
        $options['app'] = FILEZILLA;
        $options['app_args'] = '-l ask';
        $this->filer($site_env, $options);
    }

    /**
     * Opens the Site using BitKinex SFTP Client
     *
     * @authorize
     *
     * @command site:filer:bitkinex
     * @aliases site:filer:bit site:bitkinex site:bit bitkinex bit
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:bitkinex <site>.<env>
     * @usage terminus site:filer:bit <site>.<env>
     * @usage terminus site:bitkinex <site>.<env>
     * @usage terminus site:bit <site>.<env>
     * @usage terminus bitkinex <site>.<env>
     * @usage terminus bit <site>.<env>
     */
    public function bitkinex($site_env) {
        if (OS != 'WIN') {
            throw new TerminusException('Operating system not supported.');
        }
        $options['app'] = BITKINEX;
        $options['app_args'] = 'browse';
        $this->filer($site_env, $options);
    }

    /**
     * Opens the Site using WinSCP SFTP Client
     *
     * @authorize
     *
     * @command site:filer:winscp
     * @aliases site:filer:scp site:winscp site:scp winscp scp
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:winscp <site>.<env>
     * @usage terminus site:filer:scp <site>.<env>
     * @usage terminus site:winscp <site>.<env>
     * @usage terminus site:scp <site>.<env>
     * @usage terminus winscp <site>.<env>
     * @usage terminus scp <site>.<env>
     */
    public function winscp($site_env) {
        if (OS != 'WIN') {
            throw new TerminusException('Operating system not supported.');
        }
        $options['app'] = WINSCP;
        $this->filer($site_env, $options);
    }

    /**
     * Opens the Site using SFTP Client
     *
     * @authorize
     *
     * @command site:filer:sftp
     * @aliases site:sftp sftp
     *
     * @param string $site_env Site & environment in the format `site-name.env`
     *
     * @usage terminus site:filer:sftp <site>.<env>
     * @usage terminus site:sftp <site>.<env>
     * @usage terminus sftp <site>.<env>
     */
    public function sftp($site_env) {
        $options['app'] = SFTP;
        $this->filer($site_env, $options);
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
            throw new TerminusException($e->getMessage());
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
                    case 'sftp':
                        $file = '/usr/bin/sftp';
                            break;
                }
        }
        exec("ls $file", $output);
        if (empty($output)) {
            throw new TerminusException("{$file} does not exist.");
        }
        return true;
    }

}
