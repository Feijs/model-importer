<?php namespace Feijs\ModelImporter\Model;

/**
 * Importable Model Trait
 *
 * Eloquent models which can be imported should
 * implement this interface
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
trait ImportableTrait
{
	/**
	 * Return the import form attribute prefix
	 * @return string
	 */
	public function getPrefix()
	{
		return snake_case(class_basename(get_class($this)));
	}

	/**
	 * Return whether the specified key
	 *  should be imported as a date object
	 * @return boolean
	 */
	public function isDateAttribute($key)
	{
		return in_array($key, $this->getDates());
	}

}