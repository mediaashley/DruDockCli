<?php

namespace Docker\Drupal;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Console\Application as ParentApplication;
use Docker\Drupal\Extension\ApplicationConfigExtension;
use Docker\Drupal\Style\DruDockStyle;

const DEV_MYSQL_PASS = 'DEVPASSWORD';
const LOCALHOST = '127.0.0.1';
const CONFIG_PATH = '.config.yml';
const PATH_PREFIX = 'docker_';

/**
 * Class Application
 *
 * @package Docker\Drupal
 */
class Application extends ParentApplication {

  const NAME = 'Docker Drupal';

  const VERSION = '1.4-alpha1.0.8';

  // const CDN = 'http://d1gem705zq3obi.cloudfront.net';

  const CDN = 'https://s3.eu-west-2.amazonaws.com/drudock';

  protected $cfa;

  /**
   * @var string
   */
  protected $directoryRoot;

  public function __construct() {
    parent::__construct($this::NAME, $this::VERSION);

    $this->setDefaultTimezone();
    $this->addCommands($this->registerCommands());
  }

  /**
   * {@inheritdoc}
   */
  public function doRun(InputInterface $input, OutputInterface $output) {
    // Check docker is running.
    $this->checkDocker($input, $output);
    // Continue Bootstrap.
    $this->registerCommands();
    parent::doRun($input, $output);
  }

  /**
   * @return string
   */
  public function getUtilRoot() {
    return realpath(__DIR__ . '/../') . '/';
  }

  /**
   * @return string
   */
  public function getVersion() {
    return $this::VERSION;
  }


  /**
   * @return ContainerBuilder
   */
  public function getContainer() {
    return $this->container;
  }

  /**
   * Set the default timezone.
   *
   * PHP 5.4 has removed the autodetection of the system timezone,
   * so it needs to be done manually.
   * UTC is the fallback in case autodetection fails.
   */
  protected function setDefaultTimezone() {
    $timezone = 'UTC';
    if (is_link('/etc/localtime')) {
      // Mac OS X (and older Linuxes)
      // /etc/localtime is a symlink to the timezone in /usr/share/zoneinfo.
      $filename = readlink('/etc/localtime');
      if (strpos($filename, '/usr/share/zoneinfo/') === 0) {
        $timezone = substr($filename, 20);
      }
    }
    elseif (file_exists('/etc/timezone')) {
      // Ubuntu / Debian.
      $data = file_get_contents('/etc/timezone');
      if ($data) {
        $timezone = trim($data);
      }
    }
    elseif (file_exists('/etc/sysconfig/clock')) {
      // RHEL/CentOS
      $data = parse_ini_file('/etc/sysconfig/clock');
      if (!empty($data['ZONE'])) {
        $timezone = trim($data['ZONE']);
      }
    }

    date_default_timezone_set($timezone);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultInputDefinition() {
    return new InputDefinition(
      [
        new InputArgument('command', InputArgument::REQUIRED),
        new InputOption('--help', '-h', InputOption::VALUE_NONE, 'Display this help message'),
        new InputOption('--quiet', '-q', InputOption::VALUE_NONE, 'Do not output any message'),
        new InputOption('--clean-output', '-co', InputOption::VALUE_NONE, 'Clean output without section labels'),
        new InputOption('--verbose', '-v|vv|vvv', InputOption::VALUE_NONE, 'Increase the verbosity of messages'),
        new InputOption('--version', '-V', InputOption::VALUE_NONE, 'Display this application version'),
        new InputOption('--ansi', '', InputOption::VALUE_NONE, 'Force ANSI output'),
        new InputOption('--no-ansi', '', InputOption::VALUE_NONE, 'Disable ANSI output'),
        new InputOption('--no-interaction', '-n', InputOption::VALUE_NONE, 'Do not ask any interactive question'),
        //new InputOption('--yes', '-y', InputOption::VALUE_NONE, 'Answer "yes" to all prompts'),
      ]
    );
  }

  /**
   * @return \Symfony\Component\Console\Command\Command[]
   */
  protected function registerCommands() {
    static $commands = [];
    if (count($commands)) {
      return $commands;
    }

    $commands[] = new Command\App\InitCommand();
    $commands[] = new Command\App\InitBuildCommand();
    $commands[] = new Command\App\BuildCommand();
    $commands[] = new Command\App\StopCommand();
    $commands[] = new Command\App\StartCommand();
    $commands[] = new Command\App\RestartCommand();
    $commands[] = new Command\App\DestroyCommand();
    $commands[] = new Command\App\StatusCommand();
    $commands[] = new Command\App\ExecCommand();
    $commands[] = new Command\App\AboutCommand();
    $commands[] = new Command\App\UpdateCommand();
    $commands[] = new Command\App\UpdateConfigCommand();
    $commands[] = new Command\App\OpenAppCommand();
    $commands[] = new Command\App\InitContainersCommand();
    $commands[] = new Command\App\SSHCommand();
    $commands[] = new Command\App\SelfUpdateCommand();
    $commands[] = new Command\App\UpdateServicesCommand();
    $commands[] = new Command\App\BashCommand();

    $commands[] = new Command\Mysql\MysqlImportCommand();
    $commands[] = new Command\Mysql\MysqlExportCommand();
    $commands[] = new Command\Mysql\MysqlMonitorCommand();

    $commands[] = new Command\Nginx\NginxMonitorCommand();
    $commands[] = new Command\Nginx\NginxReloadCommand();
    $commands[] = new Command\Nginx\NginxFlushPagespeedCommand();
    $commands[] = new Command\Nginx\NginxSetHostCommand();
    $commands[] = new Command\Nginx\NginxProxyStartCommand();
    $commands[] = new Command\Nginx\NginxProxyStopCommand();

    $commands[] = new Command\Drush\DrushRegistryRebuildCommand();
    $commands[] = new Command\Drush\DrushClearCacheCommand();
    $commands[] = new Command\Drush\DrushLoginCommand();
    $commands[] = new Command\Drush\DrushModuleEnableCommand();
    $commands[] = new Command\Drush\DrushModuleDisableCommand();
    $commands[] = new Command\Drush\DrushUpDbCommand();
    $commands[] = new Command\Drush\DrushInitConfigCommand();
    $commands[] = new Command\Drush\DrushConfigExportCommand();
    $commands[] = new Command\Drush\DrushConfigImportCommand();

    $commands[] = new Command\Redis\RedisMonitorCommand();
    $commands[] = new Command\Redis\RedisPingCommand();
    $commands[] = new Command\Redis\RedisFlushCommand();
    $commands[] = new Command\Redis\RedisInfoCommand();

    $commands[] = new Command\Behat\BehatStatusCommand();
    $commands[] = new Command\Behat\BehatMonitorCommand();
    $commands[] = new Command\Behat\BehatCommand();

    $commands[] = new Command\Prod\ProdUpdateCommand();

    return $commands;
  }

  /**
   * @return string
   */
  public function getDockerVersion() {
    $command = 'docker --version';
    $process = new Process($command);
    $process->setTimeout(2);
    $process->run();
    return $process->getOutput();
  }


  /**
   * @param $io
   * @param string $appname
   * @param bool $skip_checks
   *
   * @return mixed
   */
  public function getAppConfig($io, $appname = '', $skip_checks = FALSE) {
    if (file_exists(CONFIG_PATH)) {
      $config = Yaml::parse(file_get_contents(CONFIG_PATH));
    }
    if (file_exists($appname . '/' . CONFIG_PATH)) {
      $config = Yaml::parse(file_get_contents($appname . '/' . CONFIG_PATH));
    }
    if (!isset($config)) {
      $io->error('You\'re not currently in an APP directory. APP ' . CONFIG_PATH . ' not found.');
      exit;
    }
    $config_keys = array_keys($config);
    $requirements = $this->getDDrequirements();

    if (!$skip_checks) {
      $missing_dist = [];
      foreach ($requirements as $req) {
        if (!in_array($req, $config_keys)) {
          $missing_dist[] = $req;
        }
      }

      if (count($missing_dist) > 0) {
        $io->info('Your app is missing the following config, please run [drudock docker:update:config] : ');
        foreach ($missing_dist as $req) {
          $io->warning($req);
        }
        exit;
      }
    }

    if (substr($this->getVersion(), 0, 1) != substr($config['drudock']['version'], 0, 1)) {
      $io->warning('You\'re installed DruDock version is different to setup app version and may not work');
    }

    return $config;
  }

  /**
   * @return Boolean
   */

  public function setAppConfig($config, $io) {
    if (file_exists(CONFIG_PATH)) {
      $yaml = Yaml::dump($config);
      file_put_contents(CONFIG_PATH, $yaml);
      return TRUE;
    }
    else {
      $io->error('You\'re not currently in an APP directory. APP .config.yml not found.');
      exit;
    }
  }

  /**
   * @return string
   */
  public function getProxyComposePath($appname, $io) {

    $system_appname = strtolower(str_replace(' ', '', $appname));

    if ($config = $this->getAppConfig($io)) {
      $dist = $config['dist'];
    }

    $fs = new Filesystem();

    if (isset($dist) && $dist == 'Prod') {
      $project = '--project-name=proxy';
    }
    else {
      $io->error("docker-compose-data.yml : Not Found");
      exit;
    }

    if ($fs->exists('./' . PATH_PREFIX . $system_appname . '/docker-compose-nginx-proxy.yml')) {
      return 'docker-compose -f ./' . PATH_PREFIX . $system_appname . '/docker-compose-nginx-proxy.yml ' . $project . ' ';
    }
    else {
      $io->error("docker-compose-data.yml : Not Found");
      exit;
    }
  }


  /**
   * @return string
   *
   * @param $command
   * @param $io
   * @param $TTY
   * @param $timeout
   * @param $idleTimeout
   */
  public function runcommand($command, $io, $TTY = FALSE, $timeout = 3600, $idleTimeout = 600) {

    global $output;
    $output = $io;

    $process = new Process($command);
    $process->setTimeout($timeout);
    $process->setIdleTimeout($idleTimeout);
    $process->setTty($TTY);
    $process->run(function ($type, $buffer) {
      global $output;
      if ($output) {
        $type = NULL;
        $output->info($buffer);
      }
    });

    // Hack to ignore docker-compose exec exit code when using Tty
    // https://github.com/docker/compose/issues/3379.
    if (!$process->isSuccessful() && ($process->getExitCode() != 129)) {
      throw new ProcessFailedException($process);
    }
  }

  /**
   * @return string
   */
  public function setNginxHost($io) {

    if ($config = $this->getAppConfig($io)) {
      $appname = $config['appname'];
      $apphost = $config['host'];
      $dist = $config['dist'];
    }

    if (!isset($apphost)) {
      $apphost = 'drudock.localhost';
    }

    $system_appname = strtolower(str_replace(' ', '', $appname));
    $nginxconfig = 'server {
    listen   80;
    listen   [::]:80;

    index index.php index.html;
    server_name ' . $apphost . ';
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /app/www;

    ## GENERIC
    sendfile off;

    client_max_body_size 20M;

    location = /favicon.ico {
        log_not_found off;
        access_log off;
    }

    location = /robots.txt {
        allow all;
        log_not_found off;
        access_log off;
    }

    # Very rarely should these ever be accessed outside of your lan
    location ~* \.(txt|log)$ {
        allow 192.168.0.0/16;
        deny all;
    }

    location ~ \..*/.*\.php$ {
        return 403;
    }

    location ~ ^/sites/.*/private/ {
        return 403;
    }

    # Allow "Well-Known URIs" as per RFC 5785
    location ~* ^/.well-known/ {
        allow all;
    }

    # Block access to "hidden" files and directories whose names begin with a
    # period. This includes directories used by version control systems such
    # as Subversion or Git to store control files.
    location ~ (^|/)\. {
        return 403;
    }

    location @drupal {
        rewrite ^/(.*)$ /index.php?q=$1 last;
    }

    location / {
        try_files $uri @drupal;
    }

    # Don\'t allow direct access to PHP files in the vendor directory.
    location ~ /vendor/.*\.php$ {
        deny all;
        return 404;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_param REMOTE_ADDR $http_x_real_ip;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_cache  off;
        fastcgi_intercept_errors on;
        fastcgi_hide_header \'X-Drupal-Cache\';
        fastcgi_hide_header \'X-Generator\';
    }

    location @rewrite {
        rewrite ^/(.*)$ /index.php?q=$1;
    }

    # Fighting with Styles? This little gem is amazing.
    location ~ ^/sites/.*/files/styles/ { # For Drupal >= 7
        try_files $uri @rewrite;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico)$ {
        expires max;
        log_not_found off;
    }
}';

    switch ($dist) {
      case 'Prod':
      case 'Stage':
        file_put_contents('./' . PATH_PREFIX . $system_appname . '/config/nginx/' . $apphost, $nginxconfig);

        $nginxenv = "VIRTUAL_HOST=$apphost
APPS_PATH=~/app
VIRTUAL_NETWORK=nginx-proxy";

        file_put_contents('./' . PATH_PREFIX . $system_appname . '/nginx.env', $nginxenv);
        break;
      default:
        file_put_contents('./' . PATH_PREFIX . $system_appname . '/config/nginx/drudock.localhost', $nginxconfig);
    }
  }


  /**
   * @return string
   */
  public function checkDocker(InputInterface $input, OutputInterface $output) {
    $command = 'docker info';
    $process = new Process($command);
    $process->setTimeout(2);
    $process->run();
    if (!$process->isSuccessful()) {
      $io = new DruDockStyle($input, $output);
      $io->error('Cannot connect to the Docker daemon. Is the docker daemon running?');
      exit;
    }
    return TRUE;
  }

  /**
   * @return string
   */
  public function getOs() {
    return PHP_OS;
  }

  /**
   * @param $io
   */
  public function requireUpdate($io) {

    $io->warning('This app .config.yml is out of date and missing data. Please run [drudock up:config].');
    exit;
  }

  /**
   * @return array
   *  Return keys of current required app config.
   */
  public function getDDrequirements() {
    return [
      'appname',
      'apptype',
      'host',
      'dist',
      'src',
    ];
  }

  /**
   * Set currently assigned config into yaml format and file.
   */
  public function setConfig($config) {
    $yaml = Yaml::dump($config);
    file_put_contents(CONFIG_PATH, $yaml);
  }

  /**
   * @param $target
   * @param $content
   *
   * @throws \Exception
   */
  public function renderFile($target, $content) {
    $filesystem = new Filesystem();
    try {
      $filesystem->dumpFile(
        $target,
        $content
      );
    } catch (IOException $e) {
      throw $e;
    }
  }

  /**
   * RUn tests and save artifacts.
   *
   * @param $appname
   */
  public function runTest($appname) {
    $this->cfa = new ApplicationConfigExtension();
    $system_appname = strtolower(str_replace(' ', '', $appname));
    $nginx_port = $this->cfa->containerPort($system_appname, 'nginx', '80');
    $command = 'curl  http://drudock.localhost:' . $nginx_port . ' > /tmp/' . $system_appname . '.html';
    shell_exec($command);
  }
}
