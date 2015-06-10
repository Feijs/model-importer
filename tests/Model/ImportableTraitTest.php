<?php

use Mockery as m;

/**
 * Test functions implementing Model\Importable Interface
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class ImportableTraitTest extends MITestCase
{
	public $importable_model;

	public function setUp()
	{
		parent::setUp();
		$this->importable_model = $this->getObjectForTrait('Feijs\ModelImporter\Model\ImportableTrait');
	}

    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    public function testPrefix()
    {
    	$prefix = $this->importable_model->getPrefix();
    	$this->assertRegExp('/importable_trait/', $prefix);	   //expect mock class, but should contain the trait class name
    	$this->assertRegExp('/^[a-z0-9_]*$/', $prefix);		   //valid snake case
    }

    public function testDateAttributeCheck()
    {
        
    }
}