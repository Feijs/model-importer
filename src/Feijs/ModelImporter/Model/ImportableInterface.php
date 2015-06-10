<?php namespace Feijs\ModelImporter\Model;

/**
 * Importable Model Interface
 *
 * Eloquent models which can be imported should
 * implement this interface
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
interface ImportableInterface
{
    /**
	 * Return the import form attribute prefix
	 * @return string
	 */
	public function getPrefix();

	/**
	 * Return the set of attributes which should be
	 *  imported and which are used to match 
	 *  existing models on
	 * @return string[]
	 */
	public function getMatchAttributes();

	/**
	 * Return the set of attributes which should be
	 *  imported, but not unique
	 * @return string[]
	 */
	public function getImportAttributes();

	/**
	 * Return whether the specified key
	 *  should be imported as a date object
	 * @return boolean
	 */
	public function isDateAttribute($key);

	/** 
	 * Return an array with date attribute names
	 * (default already implemented by eloquent)
	 */
	public function getDates();

	public function getImportRelations();
	
}