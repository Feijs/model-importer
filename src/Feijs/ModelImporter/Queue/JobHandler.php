<?php namespace Feijs\ModelImporter\Queue;

use Illuminate\Log\Writer as Log;
use Feijs\ModelImporter\ModelImporter;
use Feijs\ModelImporter\Model\ImportableInterface as ImportableModel;

/**
 * Queue job handler for model-importing
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class JobHandler 
{
    /**
     * Log import warnings
     * @var boolean
     */
    protected $log_import = false;

    /**
     * Dependencies
     *
     * @var Type
     */
    protected $importer;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ModelImporter $importer, Log $log)
    {
        $this->importer = $importer;
        $this->log = $log;
    }

    /**
     * Job handler
     * @param Illuminate\Queue\Jobs\Job $job
     * @param string[]
     */
    public function fire($job, $data)
    {
		if(is_null($data) || $job->attempts() > 2) {
            $this->log->error('Failed importing job', $data);
            $job->delete();
            return;
        }

        //Extract data
        $input = $data['input'];
        $settings = $data['settings'];
        $importable_model = new $data['importable-model'];

        //Initialize importer
        $this->importer->setModel($importable_model);
        $this->importer->setSettings($settings);

        //Import data
        if( !$this->importer->import($input) ) 
        {
            $this->log->error('Import job validation errors', $this->importer->validationErrors()->toArray());
            $job->delete();
            return;
        }

        //Log results
        if($this->log_import) 
        {
            $this->log->warning('Import job errors', $this->importer->errors()->toArray());
        }

        $this->log->info('Import job result', [$data['importable-model'] => $this->importer->getImported()]);

        $job->delete();	
    }

}
