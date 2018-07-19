<?php

namespace Madmatt\EncryptAtRest\FieldType;

use Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBInt;
use Madmatt\EncryptAtRest\AtRestCryptoService;

/**
 * Class EncryptedInt
 * @package EncryptAtRest\Fieldtypes
 *
 * This class wraps around an Int, storing the value in the database as an encrypted string in a varchar field, but
 * returning it to SilverStripe as a decrypted Int object.
 */
class EncryptedInt extends DBInt
{

    public $is_encrypted = true;
    /**
     * @var AtRestCryptoService
     */
    protected $service;

    public function __construct($name = null, $defaultVal = 0)
    {
        parent::__construct($name, $defaultVal);
        $this->service = Injector::inst()->get(AtRestCryptoService::class);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        if (array_key_exists($this->name, $record) && $value === null) {
            $this->value = $record[$this->name];
        } else {
            $this->value = $value;
        }
    }

    public function getDecryptedValue($value)
    {
        // Test if we're actually an encrypted value;
        if (ctype_xdigit($value) && strlen($value) > 130) {
            try {
                return $this->service->decrypt($value);
            } catch (Exception $e) {
                // We were unable to decrypt. Possibly a false positive, but return the unencrypted value
                return $value;
            }
        }
        return $value;
    }

    public function getValue()
    {
        return $this->getDecryptedValue($this->value);
    }

    public function requireField()
    {
        $values = array(
            'type'  => 'text',
            'parts' => array(
                'datatype'   => 'text',
//                'precision'  => $this->service->calculateRequiredFieldSize(strlen('Y-m-d H:i:s')),
                'null'       => 'not null',
                'default'    => $this->defaultVal,
                'arrayValue' => $this->arrayValue
            )
        );

        DB::require_field($this->tableName, $this->name, $values);
    }

    public function prepValueForDB($value)
    {
        $value = parent::prepValueForDB($value);
        $ciphertext = $this->service->encrypt($value);
        $this->value = $ciphertext;
        return $ciphertext;
    }

}
