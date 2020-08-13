<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\Yaml\Yaml;

require 'vendor/autoload.php';

$settings = include 'conf/settings.php';
$settings = $settings['settings']['doctrine'];

// this one parses constants
class CustomYamlDriver extends Doctrine\ORM\Mapping\Driver\YamlDriver
{
    protected function loadMappingFile($file)
    {
        return Symfony\Component\Yaml\Yaml::parse(file_get_contents($file), Yaml::PARSE_CONSTANT);
    }
}

$config = \Doctrine\ORM\Tools\Setup::createConfiguration(
    $settings['meta']['dev_mode'],
    $settings['meta']['proxy_dir'],
    $settings['meta']['cache']
);
$driver = new \Doctrine\ORM\Mapping\Driver\XmlDriver($settings['meta']['entity_path']);
$config->setMetadataDriverImpl($driver);

$em = \Doctrine\ORM\EntityManager::create($settings['connection'], $config);

return ConsoleRunner::createHelperSet($em);
