<?php namespace Feijs\ModelImporter;

use Exception;
use Illuminate\Support\Facades\Config;

/**
 * RelationModelImporter class
 *
 * Importing related Eloquent Models from
 *  single input row
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class RelationModelImporter extends AbstractModelImporter
{
    /** 
     * Name of model attribute containing the parent id
     * @var string
     */
    protected $parent_key_attribute;

    /** 
     * Name of local key attribute
     * @var string
     */
    protected $local_key_attribute;

    /** 
     * (Optional) name of pivot table (for the relation with parent)
     * @var string
     */
    protected $pivot_table;

    /** 
     * Owns the related model
     * @var boolean
     */
    protected $is_parent;

    /** 
     * Set the parent -> child relation
     *
     * @param string $relation
     * @param Eloquent $parent
     */
    public function setRelation($relation, $parent)
    {
        $function_call = call_user_func_array(array($parent, $relation), []);

        $type = class_basename(get_class($function_call));
        
        switch($type) 
        {
            case 'BelongsTo':
            {
                $this->is_parent = true;
                $this->pivot_table = null;
                $this->local_key_attribute = $function_call->getForeignKey();
                $this->parent_key_attribute = $function_call->getOtherKey();
                break;
            }
            case 'BelongsToMany':
            {
                $this->is_parent = false;
                $this->pivot_table = $function_call->getTable();
                $this->local_key_attribute = $function_call->getForeignKey();
                $this->parent_key_attribute = $function_call->getOtherKey();
                break;
            }
            case 'HasOne':
            case 'HasMany':
            {
                $this->is_parent = false;
                $this->pivot_table = null;
                $this->parent_key_attribute = $function_call->getPlainForeignKey();
                break;
            }
            default:
            {
                throw new Exception('Unhandled relation type');
                break;
            }
        }

        $this->setModel($function_call->getRelated());
    }

    /** 
     * Import data from input file and create new (or update) model instances in DB
     *
     * @param string[] $row
     * @param int $parent_id
     * @return Object
     */
    public function importModelFromRow($row, $related_model)
    {
        $model_instance = $this->matchModelFromRow($row, $this->initMatchData($related_model));

        if($model_instance != null) 
        {
            $model_instance = $this->importDataFromRow($row, $model_instance);
            
            foreach($this->parents as $parent) {
                $parent->importModelFromRow($row, $model_instance);
            }

            if(!$model_instance->save()) { 
                $this->errorMessageBag->merge($model_instance->getErrors());
                return null;
            }

            //Insert pivot table relation
            if(!is_null($this->pivot_table)) {
                $this->db->table($this->pivot_table)->insert([
                    $this->parent_key_attribute => $related_model->id,
                    $this->local_key_attribute => $model_instance->id
                ]);
            }
            elseif($this->is_parent)
            {
                $related_model->{$this->local_key_attribute} = $model_instance->id;
            }

            foreach($this->children as $child) {
                $child->importModelFromRow($row, $model_instance);
            }
        }

        return $model_instance;
    }

    /** 
     * Initial parameters to match the model on
     * @return mixed[]
     */
    protected function initMatchData($related) 
    {
        if(!is_null($this->pivot_table) || $this->is_parent) 
            return [];
        else
            return [$this->parent_key_attribute => $related->id];

    }

    public function isParent() { return $this->is_parent; }
}