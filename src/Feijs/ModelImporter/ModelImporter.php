<?php namespace Feijs\ModelImporter;

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Factory as Validator;
use Illuminate\Database\DatabaseManager as DB;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Maatwebsite\Excel\Excel as FileImporter;
use Feijs\ModelImporter\Model\ImportableInterface as ImportableModel;

/**
 * ModelImporter class
 *
 * Generic functionality for importing Eloquent Models from
 *  input files (using ExcelFile)
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class ModelImporter extends AbstractModelImporter
{
    /** 
     * Input file instance
     * @var UploadedFile
     */
	protected $input_file;

    /** 
     * Loaded row data
     * @var RowCollection
     */
    protected $loaded_data;

    /** 
     * Input file encoding
     * @var string
     */
    protected $encoding;

    /** 
     * Injected dependecy objects
     * @var type
     */
    protected $file_importer;

    /**
     * Construct new ModelImporter
     *
     * @param Validator
     */
    public function __construct(DB $db, Validator $validator, FileImporter $file_importer) 
    {
        parent::__construct($db, $validator);

        $this->file_importer = $file_importer;
        $this->encoding = 'utf-8';
    }

    /**
     * Entry function for importing from controllers
     *
     * @param string[] $input
     * @return boolean
     */
    public function import($input) 
    {
        $this->num_imported = 0;

        //Check if importer is properly initialized
        if(!$this->initialized()) return false;

        //Validate input
        if(!$this->validate($input)) return false;

        $this->setOverwrite($input['overwrite']);

        //Initial load
        $this->loadFile($input['file']);

        //Map columns based on file input
        $this->mapColumnNames($input);

        //Store default values
        $this->setDefaultData($input);

        $this->num_imported += $this->importModels();

        return true;
    }

    /**
     * Initialize the model class to import
     *
     * @param Model\ImportableInterface $importable_model
     */
    public function setModel(ImportableModel $importable_model)
    {
        parent::setModel($importable_model);

        $this->initRelationImporters();
    }

    /** Load model importers for related models */
    protected function initRelationImporters()
    {
        foreach($this->importable_model->getImportRelations() as $relation)
        {
            $relation_importer = new RelationModelImporter($this->db, $this->validator);
            $relation_importer->setRelation($relation, $this->importable_model);

            if($relation_importer->isParent()) {
                $this->parents[] = $relation_importer;
            }
            else {
                $this->children[] = $relation_importer;
            }
        }
    }

    /**
     * Set and load from new input file
     * @param UploadedFile $file
     */
    public function loadFile(UploadedFile $file)
    {
        $this->input_file = $file;
        $this->loaded_data = $this->file_importer->load($file->getRealPath(), $this->encoding);
    }

    /** 
     * Import data from input file and create new (or update) model instances in DB
     * @return int
     */
    protected function importModels()
    {
        $success = 0;

        if(is_null($this->loaded_data)) return 0;

        if(Config::get('model-importer::config.disable_query_log')) {   //Limits memory usage
            $this->db->connection()->disableQueryLog();    
        }
        $this->db->beginTransaction();

        //Get only data as specified in name arrays
        $this->loaded_data->ignoreEmpty(false);
        $data = $this->loaded_data->get( $this->getNamesToLoad());
        
        foreach($data as $row) 
        {
            $model_instance = $this->matchModelFromRow($row);
        
            if($model_instance != null) 
            {
                $model_instance = $this->importDataFromRow($row, $model_instance);

                //Import parents before saving
                foreach($this->parents as $parent) {
                    $parent->importModelFromRow($row, $model_instance);
                    $this->errorMessageBag->merge($parent->errors());
                }

                if(!$model_instance->save()) 
                { 
                    $this->errorMessageBag->merge($model_instance->getErrors());
                    continue;
                }

                //Import children after saving
                foreach($this->children as $child) {
                    $child->importModelFromRow($row, $model_instance);
                    $this->errorMessageBag->merge($child->errors());
                }
                $success++;
            }
        }

        $this->db->commit();

        return $success;
    }

    /**
     * Return validation rules for this and child models
     * @return string[]
     */
    protected function getValidationRules() 
    {
        $rules = parent::getValidationRules();
        $rules['file'] = 'required|mimes:xlsx,xls,csv,txt';     //valid file types
            
        return $rules;
    }

    /**
     * Set any global settings
     * @return 
     */
    public function setSettings($input) 
    { 
        if(array_key_exists('csv', $input)) {
            if(array_key_exists('enclosure', $input['csv'])) $this->file_importer->setDelimiter($input['csv']['enclosure']);
            if(array_key_exists('delimiter', $input['csv'])) $this->file_importer->setDelimiter($input['csv']['delimiter']);
        }
        if(array_key_exists('encoding', $input)) $this->setEncoding($input['encoding']);
    }

    public function setEncoding($value) { $this->encoding = $value; }

    public function getExcelData($columns)
    {
        if(is_null($this->loaded_data)) return null;

        return $this->loaded_data->get($columns);
    }

    public function formatDates($setting) { $this->file_importer->formatDates($setting); }
}
