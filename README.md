# Model Importer

This package aims to provide a flexible and reusable solution to import Laravel Eloquent model data directly from excel and csv files

## Features

* Simple & direct import
* Match **column titles** to **model attributes**
* Import related models
* Queued importing for multiple files

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

Any model which can be imported should implement `Model\ImportableInterface` and use `Model\ImportableTrait`

```php
use Feijs\ModelImporter\Model\ImportableTrait as ImportableModelTrait;

class Student extends Eloquent implements ImportableModel
{
	use ImportableModelTrait;
}
```

##### Attributes

Imported data is matched with existing data on a set of **match attributes**. These should be returned by the `getMatchAttributes` method.

```php
	public function getMatchAttributes() 
	{ 
		return ['student_id', 'phonenumber'];
	}
```

These **match attributes** should be mass assignable, and thus be included in the `fillable` array

```php
	public $fillable = ['student_id', 'phonenumber', '...'];
```

Any attributes which should be importable, but not distict, should be returned by the `getImportAttributes`.
These **import attributes** do not necassarily need to be included in the `fillable` array.

```php
	public function getImportAttributes()
	{ 
		return ['name', 'email', 'street', 'zipcode', 'city'];
	}		
```

##### Relations

To import relation data, or link the newly imported models to existing relations, you can specify relations which should be imported. 
These should be returned by the `getImportRelations` method. Note that any models specified here should implement `Model\ImportableInterface` as well.

The following relations may be imported: `HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`.

```php
    public function bankAccount(){
        return $this->hasMany('BankAccount');
    }

	public function getImportRelations() { return ['bankAccount']; }
```

### Import functionality

#### Initialisation

```php
	$importer = App::make('Feijs\ModelImporter\ModelImporter');
	$importer->setModel('Student');
```

#### CSV Import Settings

You may override the csv import settings and file encoding with the `setSettings` method.

```php
	$importer->setSettings([
		'csv' => [
			'enclosure' => '"',		//Default
			'delimiter' => ","		//Default
		],
		'encoding' => 'UTF-8'		//Default

	]);
```

#### Input

Input for the importer should include at least:

- `file` (`Symfony\Component\HttpFoundation\File\UploadedFile`),
- `overwrite`: Update existing data? (`boolean`)
- `model`: an array of **model attribute** -> **column title** translations

All model names, attributes and column names should be in spinal-case (slugs)

#### Importing single file

```php
	$success = $importer->import(Input::all());

	//Equivalent

	$success = $importer->import([
		'file' 		=> Input::file('data.csv'),
		'overwrite' => false,
		'student'	=> [
				'student_id' 	=> 'studentnumber',
				'phonenumber' 	=> 'mobile',
				'city' 			=> 'city',
		]
	]
```

#### Importing multiple files

To import multiple files from a single request, use the `Distributor class` and the `importFiles` method. This will queue each file for import. Results will be written to the log.

- The first parameter is an array with files
- The second parameter is the same input array as on single file import (except the file) 
- The third parameter is an array with csv & encoding settings
- The fourth parameter is the classname of the model which is to be imported

```php
	$distributor = App::make('Feijs\ModelImporter\Queue\Distributor;');

	$jobs_queued = distributor->importFiles(
							'file' 	=> Input::file('files'),
							Input::except('csv', 'encoding', 'files'),
							Input::only('csv', 'encoding'),
							'Student'
						);
```

### Output

#### Input Validation

To get any validation errors (for the model-importer input) call the `validationErrors` method

```php
	$importer->validationErrors()
```

#### Model validation

To get errors encountered during the import (ex. from in-model validation) call the `errors` method

```php
	$importer->errors()
```

#### Success

To find the number of data lines which were succesfully imported, call the `getImported` method

```php
	$importer->getImported()
```

### Customization

#### Model input slug

By default the input should include the slugged model classname. To change this, override the `getPrefix` method

```php
	public function getPrefix()
	{
		return snake_case(class_basename(get_class($this)));	//default
	}
```

#### Dates

To change which attributes should be parsed as Carbon objects, override the `isDateAttribute` function

```php
	public function isDateAttribute($key)
	{
		return in_array($key, $this->getDates());				//default
	}
```

