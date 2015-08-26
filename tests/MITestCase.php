<?php

use Orchestra\Testbench\TestCase as TestBenchTestCase;

/**
 * Package test case.
 *
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
abstract class MITestCase extends TestBenchTestCase
{
    protected function getPackageProviders()
    {
        return ['Feijs\ModelImporter\ModelImporterServiceProvider'];
    }

    protected function getPackagePath()
    {
        return realpath(implode(DIRECTORY_SEPARATOR, [
            __DIR__,
            '..',
            'src',
            'Feijs',
            'ModelImporter',
        ]));
    }

    public function getPrivateProperty($reflection, $property_name)
    {
        $property = $reflection->getProperty($property_name);
        $property->setAccessible(true);

        return $property;
    }
}
