<?php

use Feijs\ModelImporter\ModelImporterServiceProvider;
use Mockery as m;

class TestServiceProvider extends MITestCase
{
    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function testProviders()
    {
        $app = m::mock('Illuminate\Foundation\Application');
        $provider = new ModelImporterServiceProvider($app);

        $this->assertCount(2, $provider->provides());
        $this->assertContains('model-importer', $provider->provides());
        $this->assertContains('model-importer.distributor', $provider->provides());
    }
}