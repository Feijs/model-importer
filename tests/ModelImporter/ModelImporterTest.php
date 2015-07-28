<?php

use Mockery as m;
use Feijs\ModelImporter\ModelImporter;

/**
 * ModelImporter class tests
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class ModelImporterTest extends MITestCase
{
    public $model_importer;

    public $db;
    public $file;
    public $validator;
    public $file_importer;

    public $sample_input;
    public $importable_model;

	public function setUp()
	{
		parent::setUp();

        $this->setMocks();
        $this->createVars();
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
        $this->file_importer = m::mock('Maatwebsite\Excel\Excel');
        $this->file = m::mock('Symfony\Component\HttpFoundation\File\UploadedFile');
        $this->importable_model = m::mock('Feijs\ModelImporter\Model\ImportableInterface');
    }

    public function createVars()
    {
        $this->sample_input = [
            'file'                          => $this->file,
            'overwrite'                     => 'false',
            'some_student' => [
                'firstname' => 'Voornaam',
                'lastname'  => 'Achternaam',
                'email'     => 'E-mail Adres'
            ],
            'defaults' => [
                'some_student' => [
                    'email'    => 'test@provider.com'
                ]
            ],
            'partner' => [
                'lastname'  => 'Achternaam (partner)'
            ]
        ];
    }

    /*---------------------------------
     * Tests
     */

    public function testModelImporterCreation()
    {
        $importer = App::make('Feijs\ModelImporter\ModelImporter');
        $this->assertInstanceOf('Feijs\ModelImporter\ModelImporter', $importer);
    }

    public function testFileLoading()
    {
        /* Preparation */
        $this->model_importer = new ModelImporter($this->db, $this->validator, $this->file_importer);

        /* Expectation */
        $this->file->shouldReceive('getRealPath')->once()->andReturn('dir');

        $this->file_importer->shouldReceive('load')->once()->with('dir', m::any());
        
        /* Execution */
        $this->model_importer->loadFile(['file' => $this->file]);
    }

    public function testSetTopModel()
    {
        /* Preparation */
        $this->model_importer = new ModelImporter($this->db, $this->validator, $this->file_importer);

        /* Expectation */
        $this->importable_model->shouldReceive('getPrefix')->andReturn('some_student');
        $this->importable_model->shouldReceive('getImportRelations')->andReturn([]);

        /* Execution */
        $this->model_importer->setModel($this->importable_model);
    }

	public function testFailedValidation() 
    {
		/* Preparation */
        $this->model_importer = 
            m::mock('Feijs\ModelImporter\ModelImporter[initialized]', 
                    array($this->db, App::make('Illuminate\Validation\Factory'), $this->file_importer)
            );
		$this->model_importer->shouldReceive('initialized')->once()->andReturn(true);

        /* Execution */
        $this->assertFalse($this->model_importer->import( [] ));
        $this->assertCount(3, $this->model_importer->validationErrors());
    }

    /*---------------------------------
     * Test import function in fases,
     */

    public function testUnitialized() 
    {
        $this->model_importer = new ModelImporter($this->db, $this->validator, $this->file_importer);
        $this->assertFalse($this->model_importer->import($this->sample_input));
    }

    public function importMocks()
    {
        /* Preparation */
        $this->model_importer = 
            m::mock('Feijs\ModelImporter\ModelImporter[loadFile,initialized]', 
                    array($this->db, $this->validator, $this->file_importer)
            );

        /* Expectation */  
        $this->model_importer->shouldReceive('initialized')->once()->andReturn(true);
        $this->model_importer->shouldReceive('loadFile')->once()->with($this->sample_input);

        $validator_instance = m::mock('Illuminate\Validation\Validator');
        $validator_instance->shouldReceive('fails')->once()->andReturn(false);
        $this->validator->shouldReceive('make')->once()->andReturn($validator_instance);

        $this->importable_model->shouldReceive('getPrefix')->once()->andReturn('some_student');
        $this->importable_model->shouldReceive('getImportRelations')->andReturn([]);
        $this->importable_model->shouldReceive('getMatchAttributes')->once()->andReturn(['firstname', 'lastname']);
        $this->importable_model->shouldReceive('getImportAttributes')->once()->andReturn(['email']);
        $this->importable_model->shouldReceive('isDateAttribute')->once()->with('email')->andReturn(false);
    }

    public function testColumnNameMapping()
    {
        $this->importMocks();

        $expected = [
            'firstname' => 'voornaam',
            'lastname' => 'achternaam',
            'email' => 'e_mail_adres'
        ];

        /** Execution */
        $this->model_importer->setModel($this->importable_model);
        $this->model_importer->import($this->sample_input);

        /* Assertion */
        $this->assertEquals($expected, $this->model_importer->getMapping());
    }

    public function testSetDefaultData()
    {
        $this->importMocks();

        $expected = [
            'email' => 'test@provider.com',
        ];

        /** Execution */
        $this->model_importer->setModel($this->importable_model);
        $this->model_importer->import($this->sample_input);

        /* Assertion */
        $this->assertEquals($expected, $this->model_importer->getDefaultValues());
    }
}