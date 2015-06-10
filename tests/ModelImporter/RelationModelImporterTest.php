<?php

use Mockery as m;
use Feijs\ModelImporter\RelationModelImporter;

/**
 * RelationModelImporter class tests
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class RelationModelImporterTest extends MITestCase
{
    public $model_importer;
    public $reflection;

    public $db;
    public $validator;

    public $importable_model;

	public function setUp()
	{
		parent::setUp();

        $this->setMocks();
	}

    public function tearDown()
    {
        parent::tearDown();
        m::close();
    }

    /*---------------------------------
     * Helpers
     */

    public function setMocks()
    {
        $this->db = m::mock('Illuminate\Database\DatabaseManager');
        $this->validator = m::mock('Illuminate\Validation\Factory');
        $this->importable_model = m::mock('Feijs\ModelImporter\Model\ImportableInterface');
    }

    /*---------------------------------
     * Tests
     */

    public function testRelationModelImporterCreation()
    {
        $importer = App::make('Feijs\ModelImporter\RelationModelImporter');
        $this->assertInstanceOf('Feijs\ModelImporter\RelationModelImporter', $importer);
    }

    public function testSetRelationOneToMany()
    {
        /* Preparation */
        $this->model_importer = new RelationModelImporter($this->db, $this->validator);
        $this->reflection = new ReflectionObject( $this->model_importer );

        /* Expectation */
        $relation = m::mock('alias:Illuminate\Database\Eloquent\Relations\BelongsTo');
        $relation->shouldReceive('getForeignKey')->once()->andReturn('id');
        $relation->shouldReceive('getOtherKey')->once()->andReturn('father_id');
        $relation->shouldReceive('getRelated')->once()->andReturn($this->importable_model);

        $this->importable_model->shouldReceive('father')->andReturn($relation);
        $this->importable_model->shouldReceive('getImportRelations')->andReturn([]);
        $this->importable_model->shouldReceive('getPrefix')->andReturn('some_student');

        /* Execution */
        $this->model_importer->setRelation('father', $this->importable_model);

        /* Assertion */
        $this->assertEquals('id', $this->getPrivateProperty($this->reflection, 'local_key_attribute')
                                       ->getValue($this->model_importer)
                            );
        $this->assertEquals('father_id', $this->getPrivateProperty($this->reflection, 'parent_key_attribute')
                                       ->getValue($this->model_importer)
                            );
        $this->assertEquals(null, $this->getPrivateProperty($this->reflection, 'pivot_table')
                                       ->getValue($this->model_importer)
                            );
    }

    public function testSetRelationManyToMany()
    {
        /* Preparation */
        $this->model_importer = new RelationModelImporter($this->db, $this->validator);
        $this->reflection = new ReflectionObject( $this->model_importer );

        /* Expectation */
        $relation = m::mock('alias:Illuminate\Database\Eloquent\Relations\BelongsToMany');
        $relation->shouldReceive('getForeignKey')->once()->andReturn('child_id');
        $relation->shouldReceive('getOtherKey')->once()->andReturn('father_id');
        $relation->shouldReceive('getTable')->once()->andReturn('father_childeren');
        $relation->shouldReceive('getRelated')->once()->andReturn($this->importable_model);

        $this->importable_model->shouldReceive('father')->andReturn($relation);
        $this->importable_model->shouldReceive('getImportRelations')->andReturn([]);
        $this->importable_model->shouldReceive('getPrefix')->andReturn('some_student');

        /* Execution */
        $this->model_importer->setRelation('father', $this->importable_model);

        /* Assertion */
        $this->assertEquals('child_id', $this->getPrivateProperty($this->reflection, 'local_key_attribute')
                                       ->getValue($this->model_importer)
                            );
        $this->assertEquals('father_id', $this->getPrivateProperty($this->reflection, 'parent_key_attribute')
                                       ->getValue($this->model_importer)
                            );
        $this->assertEquals('father_childeren', $this->getPrivateProperty($this->reflection, 'pivot_table')
                                       ->getValue($this->model_importer)
                            );
    }

}