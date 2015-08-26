<?php

namespace Feijs\ModelImporter;

use Carbon\Carbon;
use Feijs\ModelImporter\Model\ImportableInterface as ImportableModel;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Factory as Validator;

/**
 * ModelImporter class.
 *
 * Generic functionality for importing Eloquent Models from
 *  input files (using ExcelFile)
 *
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
abstract class AbstractModelImporter
{
    /** 
     * Importable Model.
     *
     * @var Model\ImportableInterface
     */
    protected $importable_model;

    /** 
     * Match attribute names -> column titles.
     *
     * @var string[]
     */
    protected $match_names;

    /** 
     * Import attribute names -> column titles.
     *
     * @var string[]
     */
    protected $import_names;

    /** 
     * Default values.
     *
     * @var mixed[]
     */
    protected $default_data;

    /** 
     * External values to inject.
     *
     * @var mixed[]
     */
    protected $injected_data;

    /** 
     * Linked external columns.
     *
     * @var string[]
     *               ex. ['bedrijfsnaam' => 'nawt_id']
     */
    protected $injected_data_pairs;

    /** 
     * Update/overwrite existing data.
     *
     * @var bool
     */
    private $overwrite;

    /** 
     * Related child model importers.
     *
     * @var RelationModelImporter[]
     */
    protected $children;

    /** 
     * Related parent model importers.
     *
     * @var RelationModelImporter[]
     */
    protected $parents;

    /** 
     * error message container.
     *
     * @var MessageBag
     */
    protected $errorMessageBag;

    /** 
     * validation error message container.
     *
     * @var MessageBag
     */
    protected $validationMessageBag;

    /** 
     * Number of models imported.
     *
     * @var int
     */
    protected $num_imported;

    /** 
     * Loaded model form prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Match attributes as set (true) or individually (false).
     *
     * @var bool
     */
    protected $set_matching;

    /** 
     * Loaded model uses Laravel-Model-Validation.
     *
     * @var bool
     */
    protected $model_validation;

    /** 
     * Injected dependecy objects.
     *
     * @var type
     */
    protected $db;
    protected $validator;

    /**
     * Construct new ModelImporter.
     *
     * @param Illuminate\Validation\Factory       $validator
     * @param Illuminate\Database\DatabaseManager $db
     */
    public function __construct(DB $db, Validator $validator)
    {
        $this->db = $db;
        $this->validator = $validator;

        $this->parents = [];
        $this->children = [];
        $this->importable_model = null;
        $this->errorMessageBag = new MessageBag();
        $this->validationMessageBag = new MessageBag();

        $this->injected_data = null;
        $this->injected_data_pairs = [];

        Config::addNamespace('model-importer', __DIR__.'/../../config');
        $this->set_matching = Config::get('model-importer::config.set_matching');
    }

    /**
     * Initialize the model class to import.
     *
     * @param Model\ImportableInterface $importable_model
     */
    public function setModel(ImportableModel $importable_model)
    {
        $this->importable_model = $importable_model;
        $this->prefix = $this->importable_model->getPrefix();
        $this->model_validation = method_exists($importable_model, 'getErrors');

        $this->initRelationImporters();
    }

    /** Load model importers for related models */
    protected function initRelationImporters()
    {
        foreach ($this->importable_model->getImportRelations() as $relation) {
            $relation_importer = new RelationModelImporter($this->db, $this->validator);
            $relation_importer->setRelation($relation, $this->importable_model);

            if ($relation_importer->isParent()) {
                $this->parents[] = $relation_importer;
            } else {
                $this->children[] = $relation_importer;
            }
        }
    }

    /**
     * Return whether the importer is properly initialized.
     *
     * @return bool
     */
    public function initialized()
    {
        return !is_null($this->importable_model);
    }

    /*-------------------------------------------
     * Functions to map relevant
     *  input keys to the model attributes
     *  and their corresponding column name
     *  value
     */

    /** 
     * Map all attribute values to specified column names.
     *
     * @param string[]
     */
    protected function mapColumnNames($input)
    {
        //Get all (stripped) inputs for this model
        $relevant_input = array_key_exists($this->prefix, $input) ? $input[$this->prefix] : [];

        //Map attributes to column names
        $this->match_names = $this->mapNames(
                                array_intersect_key(
                                    $relevant_input,
                                    array_flip($this->importable_model->getMatchAttributes())
                                )
                            );

        $this->import_names = $this->mapNames(
                                array_intersect_key(
                                    $relevant_input,
                                    array_flip($this->importable_model->getImportAttributes())
                                )
                            );

        //Pass original input to relation model importers
        foreach ($this->getRelations() as $relation) {
            $relation->mapColumnNames($input);
        }
    }

    /** 
     * Map specified attribute values to specified column names.
     *
     * @param string[]
     *
     * @return string[]
     */
    protected function mapNames($input)
    {
        $result = [];
        foreach ($input as $internal_name => $external_name) {
            $result[$internal_name] = $this->to_slug($external_name);
        }

        return $result;
    }

    /** 
     * Get all external column names to load from the imported file.
     *
     * @return string[]
     */
    protected function getNamesToLoad()
    {
        $names = array_merge($this->match_names,
                             $this->import_names,
                             $this->injected_data_pairs);

        foreach ($this->getRelations() as $relation) {
            $names = array_merge($names, $relation->getNamesToLoad());
        }

        return array_values($names);
    }

    /** 
     * Fill an array with specified default
     *  values for relevant model attribute
     *  inputs.
     *
     * @param string[]
     */
    public function setDefaultData($input)
    {
        //Set default data
        if (array_key_exists('defaults', $input) && array_key_exists($this->prefix, $input['defaults'])) {
            array_walk($input['defaults'][$this->prefix], [$this, 'setDefaultValue']);
        }

        foreach ($this->children as $child) {
            $child->setDefaultData($input);
        }
    }

    /**
     * Set a default value in the default data array.
     * 
     * Does not change $value!
     *
     * @param string $value
     * @param string $key
     */
    protected function setDefaultValue(&$value, $key)
    {
        if ($value != '') {
            //Check if this should be converted as a date object
            if ($this->importable_model->isDateAttribute($key)) {
                $this->default_data[$key] =
                    Carbon::CreateFromFormat(
                        Config::get('model-importer::config.date_format'),
                        $value
                    );
            } else {
                $this->default_data[$key] = $value;
            }
        }
    }

    /** 
     * Return the (new or matched) model instance.
     *
     * @param string[] $row
     * @param int      $parent_id
     *
     * @return Object
     */
    protected function matchModelFromRow($row, $match_data = [])
    {
        //Fill data to match upon
        foreach ($this->match_names as $internal_name => $external_name) {
            if (!is_null($row->$external_name)) {
                $match_data[$internal_name] = $row->$external_name;
            }
            //Check for default values
            elseif (isset($this->default_data[$internal_name])) {
                $match_data[$internal_name] = $this->default_data[$internal_name];
            } elseif (!is_null($value = $this->getInjectedValue($external_name, $row))) {
                $match_data[$internal_name] = $value;
            } else {
                $this->addError($this->prefix, 'Not enough data to match on: missing '.$external_name);

                return;
            }
        }

        $model_instance = $this->firstOrNew($match_data);

        //Did we find or make a valid instance?
        if (is_null($model_instance)) {
            return; //next row
        }

        return $model_instance;
    }

    /** 
     * Get the first record matching the attributes or instantiate it.
     * 
     * Either match on complete set (default) or on any single attribute
     *
     * @param array $match_data
     */
    protected function firstOrNew($match_data)
    {
        //Find an existing instance, or create a new one
        if ($this->set_matching) {
            $model_instance = $this->importable_model->firstOrNew($match_data);
        } else {
            $query = $this->importable_model->query();
            foreach ($match_data as $key => $value) {
                $query->orWhere($key, '=', $value);
            }
            $model_instance = $query->first();

            if (is_null($model_instance)) {
                $model_instance = $this->importable_model->newInstance($match_data);
            }
        }

        return $model_instance;
    }

    /** 
     * Set or update a model instance with new data.
     *
     * @param string[] $row
     * @param Object   $model_instance
     *
     * @return Object
     */
    protected function importDataFromRow($row, $model_instance)
    {
        if ($model_instance->exists && !$this->overwrite) {
            return $model_instance;
        }

        //Fill model data by mapped columns
        foreach ($this->import_names as $internal_name => $external_name) {
            if (!is_null($row->$external_name)) {
                $model_instance->$internal_name = $row->$external_name;
            }
            //Check for default values
            elseif (isset($this->default_data[$internal_name])) {
                $model_instance->$internal_name = $this->default_data[$internal_name];
            }
            //Check for injected data
            elseif (!is_null($value = $this->getInjectedValue($external_name, $row))) {
                $model_instance->$internal_name = $value;
            }
        }

        return $model_instance;
    }

    /** 
     * Inject a set of related data.
     *
     * @param Collection $data
     * @param string     $foreign_key
     * @param string     $local_key
     * @param string[]   $local_columns
     */
    public function injectData($data, $foreign_key, $local_key, $local_columns)
    {
        $this->injected_data = $data->keyBy($foreign_key)->toArray();

        foreach ($local_columns as $column) {
            $this->injected_data_pairs[$column] = $local_key;
        }

        //todo: something less crude
        foreach ($this->getRelations() as $relation) {
            $relation->injectData($data, $foreign_key, $local_key, $local_columns);
        }
    }

    protected function getInjectedValue($local_column, $row)
    {
        if (!is_null($this->injected_data_pairs) &&
           array_key_exists($local_column, $this->injected_data_pairs)) {
            $injected_key = $this->injected_data_pairs[$local_column];
            $injected_values = $this->injected_data[$row->$injected_key];

            if (!is_null($injected_values)) {
                return $injected_values[$local_column];
            }
        }

        return;
    }

    /*----------------------------
     * Input valdiation for the 
     *  scope of variables used 
     *  by this importer
     */

    /**
     * Validate the input.
     *
     * @return bool
     */
    protected function validate($input)
    {
        $validator = $this->validator->make($input, $this->getValidationRules());
        if ($validator->fails()) {
            $this->validationMessageBag->merge($validator);

            return false;
        }

        return true;
    }

    /**
     * Return validation rules for this and child models.
     *
     * @return string[]
     */
    protected function getValidationRules()
    {
        $rules = ['overwrite' => 'required'];

        foreach ($this->children as $child) {
            $rules = array_merge($rules, $child->getValidationRules());
        }

        return $rules;
    }

    /*----------------------------
     * Setters & Getters
     */

    /** 
     * Set overwrite member variable.
     *
     * @param bool
     */
    public function setOverwrite($value)
    {
        $this->overwrite = ($value == 'true');

        foreach ($this->getRelations() as $relation) {
            $relation->setOverwrite($value);
        }
    }

    /**
     * Return error messages.
     *
     * @return MessageBag
     */
    public function errors()
    {
        return $this->errorMessageBag;
    }

    /**
     * Return validation error messages.
     *
     * @return MessageBag
     */
    public function validationErrors()
    {
        return $this->validationMessageBag;
    }

    /**
     * Return number of imported models.
     *
     * @return int
     */
    public function getImported()
    {
        return $this->num_imported;
    }

    /**
     * Return the mapping of input keys to column titles.
     *
     * @return string[]
     */
    public function getMapping()
    {
        return array_merge($this->match_names, $this->import_names);
    }

    /**
     * Return the mapping of input keys to column titles.
     *
     * @return string[]
     */
    public function getDefaultValues()
    {
        return $this->default_data;
    }

    /**
     * Return all related model importers.
     *
     * @return ModelImporter[]
     */
    protected function getRelations()
    {
        return array_merge($this->parents, $this->children);
    }

    /**
     * Set whether to match on complete set of attributes.
     *
     * @param bool
     */
    public function setSetMatching($value)
    {
        $this->set_matching = $value;
    }

    /*----------------------------
     * Helper functions
     */

    /** 
     * Convert a string to slug (with underscores) to match ExcelFile column name slugs.
     *
     * @param string
     *
     * @return string
     */
    protected function to_slug($string)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9_]+/', '_', $string)));
    }

    /**
     * Filter an array by its keys using a callback.
     * 
     * @param $array
     * @param $callback
     *
     * @return string[]
     */
    protected function array_filter_keys(array $array, $callback)
    {
        $matchedKeys = array_filter(array_keys($array), $callback);

        return array_intersect_key($array, array_flip($matchedKeys));
    }

    /** 
     * Return whether the given string ends with substring.
     *
     * @param string $string
     * @param string $find
     *
     * @return bool
     */
    protected function strEnds($string, $find)
    {
        $sl = strlen($string);
        $fl = strlen($find);
        if ($fl > $sl) {
            return false;
        }

        return (strpos($string, $find, $sl - $fl) != false);
    }

    /** 
     * Add a new error.
     *
     * @param string $key
     * @param string $message
     */
    protected function addError($key, $message)
    {
        $this->errorMessageBag->add($key, $message);
    }

    /** 
     * Gather and merge import errors from 
     *  related model importers.
     */
    public function gatherErrors()
    {
        foreach ($this->parents as $parent) {
            $parent->gatherErrors();
            $this->errorMessageBag->merge($parent->errors());
        }

        foreach ($this->children as $child) {
            $child->gatherErrors();
            $this->errorMessageBag->merge($child->errors());
        }
    }
}
