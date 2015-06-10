<?php
use Mockery as m;

/**
 * Package config test
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class TestConfig extends MITestCase
{
    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function testDateFormatConfig()
    {
        $this->assertEquals('d-m-Y', Config::get('model-importer::config.date_format'));
    }
}