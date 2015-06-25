# Model Importer

This package aims to provide a flexible and reusable solution to import Laravel Eloquent model data directly from excel and csv files

## Features

* Couple column titles to model attributes from user input.
* Import related models from one or multiple data files
* Queued importing

## Installation

Add the package in `composer.json` and run `composer update`

```php
"require": {
	...
	"feijs/model-importer": "dev-master"
}
"repositories": [ 
	{
		"type": "vcs",
        "url": "https://github.com/Feijs/model-importer" 
    }
],
```

Add the ServiceProvider to the providers in `config\app.php`

```php
'Feijs\ModelImporter\ModelImporterServiceProvider',
```

## Usage

### Importable models

To allow a model to be imported, it should implement `Model\ImportableInterface`

```php
use Feijs\ModelImporter\Model\ImportableInterface as ImportableModel;

class SomeModel implements ImportableModel
```

Some of the interface functions have a default implementation in `Model\ImportableTrait`

```php
use Feijs\ModelImporter\Model\ImportableTrait as ImportableModelTrait;

class SomeModel implements ImportableModel
{
	use ImportableModelTrait;
}
```

Functions which should be implemented:

##### Matching attributes

```php
	/**
	 * Return the set of attributes which should be
	 *  imported and which are used to match 
	 *  existing models on
	 * @return string[]
	 */
	public function getMatchAttributes() 
	{ 
		return ['unique_column1', 'unique_column2'];
	}

	/** These attributes should be included in the fillable array */
	public $fillable = ['unique_column1', 'unique_column2', '...'];
```

##### Non-matching attributes

```php
	/**
	 * Return the set of attributes which should be
	 *  imported, but not necessarily unique
	 * @return string[]
	 */
	public function getImportAttributes()
	{ 
		return ['other', 'attributes', 'which', 'do', 'not', 'have', 'to', 'be', 'unique'];
	}		
```

##### Importable relations

The following relations may be imported: `HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`

```php
    public function related_model(){
        return $this->hasMany('RelatedModel');
    }

	/* ... */

	public function getImportRelations() { return ['related_model']; }
```

### Controller

Example controller for importing a single file per request

```php
	use Feijs\ModelImporter\ModelImporter;

	/** ... */
	class ImportController extends \BaseController 
	{
		/** Base view */
		protected $layout = "layouts.main";

		protected $importer;

		/**
	     * Controller constructor
	     *
	     * Initialize the importer
	     */
		public function __construct(ModelImporter $importer, SomeModel $importable_model)
		{
	    	$this->importer = $importer;
	    	$this->importer->setModel($importable_model);
		}
		
		/**
		 * Index of import
		 *
		 * @return Response
		 */
		public function getIndex()
		{
			$this->layout->content = View::make('import');
		}

		/**
		 * Import csv file
		 *
		 * @return Response
		 */
		public function postIndex()
		{
			$this->importer->setSettings(Input::only('csv', 'encoding'));
			
			if( !$this->importer->import(Input::except('csv', 'encoding')) )
				return Redirect::to('import')->withErrors($this->importer->validationErrors(), 'validation');

			return Redirect::to('import')
							 ->withErrors($this->importer->errors(), 'import')
							 ->with('message', $this->importer->getImported());
		}
	}
```

To import multiple files you can use the queue distributor

```php
	use Feijs\ModelImporter\Queue\Distributor;

	/** ... */
	class ImportController extends \BaseController 
	{
		/**
	     * Controller constructor
	     *
	     * Initialize the importer
	     */
		public function __construct(Distributor $distributor)
		{
	    	$this->distributor = $distributor;
		}

		/* ... */

		/**
		 * Import multiple csv files
		 *
		 * @return Response
		 */
		public function postIndex()
		{
			$count = $this->distributor->importFiles(
							Input::file('files'), 
							Input::except('csv', 'encoding', 'files'),
							Input::only('csv', 'encoding'),
							'SomeModel'
						);

			return Redirect::to('customers/import')->with('flash_notice', $count . ' files worden geimporteerd.');
		}
	}
```