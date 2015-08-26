<?php

/**
 * Valid file types for import.
 *
 * @author     Mike Feijs <mfeijs@gmail.com>
 * @copyright  (c) 2015, Mike Feijs
 */

return [
    'csv'   => ['text/x-comma-separated-values', 'text/comma-separated-values', 'application/csv',
                     'application/octet-stream', 'text/plain', 'text/csv', 'application/txt', ],
    'xls'   => ['application/excel', 'application/vnd.ms-excel', 'application/msexcel'],
    'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',

];
