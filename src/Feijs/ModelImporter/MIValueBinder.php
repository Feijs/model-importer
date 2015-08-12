<?php namespace Feijs\ModelImporter;

use PHPExcel_Cell;
use PHPExcel_RichText;
use PHPExcel_Shared_String;
use PHPExcel_Cell_DataType;
use PHPExcel_Cell_IValueBinder;
use PHPExcel_Cell_DefaultValueBinder;

/** 
 * Override any numeric columns to be interpreted as string
 *  to avoid PHPExcel trimming leading & trailing zeros
 *  
 */
class MIValueBinder extends PHPExcel_Cell_DefaultValueBinder implements PHPExcel_Cell_IValueBinder
{
    /**
     * Bind value to a cell
     *
     * @param PHPExcel_Cell $cell    Cell to bind value to
     * @param mixed $value            Value to bind in cell
     * @return boolean 
     */
    public function bindValue(PHPExcel_Cell $cell, $value = null)
    {
        // sanitize UTF-8 strings
        if (is_string($value)) {
            $value = PHPExcel_Shared_String::SanitizeUTF8($value);
        } elseif (is_object($value)) {
            // Handle any objects that might be injected
            if ($value instanceof DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (!($value instanceof PHPExcel_RichText)) {
                $value = (string) $value;
            }
        }

        $type = self::dataTypeForValue($value);
        if($type == PHPExcel_Cell_DataType::TYPE_NUMERIC) {
            $type = PHPExcel_Cell_DataType::TYPE_STRING;
        }

        // Set value explicit
        $cell->setValueExplicit($value, $type);

        // Done!
        return true;
    }
}
