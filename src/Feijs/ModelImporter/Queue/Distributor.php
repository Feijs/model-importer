<?php namespace Feijs\ModelImporter\Queue;

use Feijs\ModelImporter\ModelImporter;
use Illuminate\Queue\QueueManager as Queue;

/**
 * Work load distributor for model-importing
 *
 * @package    Feijs/ModelImporter
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */
class Distributor
{
    /**
     * Dependencies
     *
     * @var Type
     */
    protected $queue;

    /**
     * Create a new distributor
     *
     * @return void
     */
    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    /**
     * Import muliple files
     *
     * @param string[] $input
     * @return boolean
     */
    public function importFiles($files, $input, $settings, $importable_model_class)
    {
        $count = 0;
        $data = [
            'settings'          => $settings,
            'importable-model'  => $importable_model_class
        ];

        foreach($files as $file)
        {
            //Move file from tmp folder
            $moved_file = $file->move(storage_path() . '/uploads');
            $data['input'] = array_merge($input, ['path' => $moved_file->getRealPath()]);

            //Push new import job to queue stack
            $this->queue->push('Feijs\ModelImporter\Queue\JobHandler', $data);
            $count++;
        }

        return $count;       
	}

}
