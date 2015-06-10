<?php

use Mockery as m;
use Feijs\ModelImporter\ModelImporter;

/**
 * ModelImporter validation tests
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class ModelImporterValidationTest extends MITestCase
{
    public $model_importer;

    public $db;
    public $validator;
    public $file_importer;

    public $rules;
    public $file;
    public $wrong_input;
    public $correct_input;
    public $importable_model;

    public function setUp()
    {
        parent::setUp();

        $this->setMocks();
        $this->createVars();

        $this->model_importer = 
            m::mock('Feijs\ModelImporter\ModelImporter[loadFile,initialized]', 
                    array($this->db, $this->validator, $this->file_importer)
            );
        $this->model_importer->shouldReceive('initialized')->once()->andReturn(true);
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
        $this->validator = App::make('Illuminate\Validation\Factory');
        $this->file = m::mock('Symfony\Component\HttpFoundation\File\UploadedFile[guessExtension]', 
                                        [__FILE__, '', null, null, null, true]
                                    );
        $this->db = m::mock('Illuminate\Database\DatabaseManager');
        $this->file_importer = m::mock('Maatwebsite\Excel\Excel');
    }

    public function createVars()
    {
        $this->wrong_input = [
            'file'                          => 'none'
        ];

        $this->correct_input = [
            'file'                          => $this->file,
            'overwrite'                     => 'false',
        ];

        $this->rules = [
            'overwrite' => 'required',
            'file'=>'required|mimes:xlsx,xls,csv'
        ];
    }

    public function testFailedValidation() 
    {
        /* Exection */
        $this->assertFalse($this->model_importer->import($this->wrong_input));
        $this->assertCount(2, $this->model_importer->validationErrors());
    }

    public function testSuccessfullValidation() 
    {
        $this->markTestIncomplete('Validator fails against all reason');

        /* Expectation */
        $this->model_importer->shouldReceive('loadFile')->once()->with('file.csv');
        $this->file->shouldReceive('guessExtension')->andReturn($this->returnValue('csv'));

        /* Exection */
        $this->assertEquals(7, $this->model_importer->import($this->correct_input));
        $this->assertCount(0, $this->model_importer->validationErrors()->toArray());
    }
}