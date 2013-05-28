<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Db;

use Dewdrop\Fields\OptionPairs;
use Zend\Filter;
use Zend\InputFilter\Input;
use Zend\Validator;

/**
 * Field objects provide a way to leverage database metadata throughout
 * your application and establish a centralized source of information about
 * how the field should be labeled, any notes that should be displayed with
 * it, any validators that should be included, etc.
 */
class Field
{
    /**
     * A \Dewdrop\Fields\OptionPairs object for use in retrieving key-value
     * pair options for a foreign key field.
     *
     * @var \Dewdrop\Fields\OptionPairs
     */
    protected $optionPairs;

    /**
     * The table this field is associated with.
     *
     * @var \Dewdrop\Db\Table
     */
    protected $table;

    /**
     * The row this field is associated with.  There will not always be a row
     * associated with the field.  The getValue() and setValue() methods will
     * not be functional unless a row is present.
     *
     * @var \Dewdrop\Db\Row
     */
    protected $row;

    /**
     * Whether this field is required or not
     *
     * @var boolean
     */
    protected $required;

    /**
     * The name of the column this field represents.
     *
     * @var string
     */
    protected $name;

    /**
     * How this field should be labeled when included in UI such as form fields
     * or table headers.
     *
     * @var string
     */
    private $label;

    /**
     * Any notes that should be displayed along with this field when it is
     * displayed to users.
     *
     * @var string
     */
    private $note = '';

    /**
     * The metadata related to this column.  The metadata includes the
     * following fields:
     *
     * - SCHEMA_NAME
     * - TABLE_NAME
     * - COLUMN_NAME
     * - COLUMN_POSITION
     * - DATA_TYPE
     * - DEFAULT
     * - NULLABLE
     * - LENGTH
     * - SCALE
     * - PRECISION
     * - UNSIGNED
     * - PRIMARY
     * - PRIMARY_POSITION
     * - IDENTITY
     *
     * @var array
     */
    private $metadata;

    /**
     * An identifier for this field when it is associated with input controls
     * or other UI elements.  This can be changed manually to disambiguate
     * fields when multiple instances of a model and its fields are used on a
     * single request.
     *
     * By default, this property with have the value of:
     *
     * <code>
     * table_name:column_name
     * </code>
     *
     * @var string
     */
    private $controlName;

    /**
     * The \Zend\InputFilter\Filter instance used to validate and filter this
     * field.
     *
     * @var \Zend\InputFilter\Filter
     */
    private $inputFilter;

    /**
     * Create new field with a reference to the table that instantiated it,
     * the name of the DB column it represents and metadata from the DB about
     * its type, constraints, etc.
     *
     * @param Table $table
     * @param string $name
     * @param array $metadata
     */
    public function __construct(Table $table, $name, array $metadata)
    {
        $this->table    = $table;
        $this->name     = $name;
        $this->metadata = $metadata;
    }

    /**
     * Get a reference to the table that generated this field object.
     *
     * @return \Dewdrop\Db\Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Manually override the default setting regarding whether this field
     * is required
     *
     * @param boolean $required
     * @return \Dewdrop\Db\Field
     */
    public function setRequired($required)
    {
        $this->required = (boolean) $required;

        return $this;
    }

    /**
     * Check whether this field is required.  If setRequired() has not been
     * called, then we look to the DB metadata to determine whether the
     * field is required.  When the metadata says the column is not NULLABLE,
     * then it is marked as being required.
     *
     * @return boolaen
     */
    public function isRequired()
    {
        if (null === $this->required) {
            $this->required = (false === $this->metadata['NULLABLE']);
        }

        return $this->required;
    }

    /**
     * Associate a row with this field object so that it can be used to retrieve
     * and/or set the value of the associated column in the row.
     *
     * @param Row $row
     * @return \Dewdrop\Db\Field
     */
    public function setRow(Row $row)
    {
        $this->row = $row;

        return $this;
    }

    /**
     * Set the value of this field on the associated row, if available.
     *
     * @param mixed $value
     * @return \Dewdrop\Db\Field
     */
    public function setValue($value)
    {
        $this->row->set($this->name, $value);

        return $this;
    }

    /**
     * Retrieve the value of this field for the associated row, if available.
     * @return mixed
     */
    public function getValue()
    {
        return $this->row->get($this->name);
    }

    /**
     * Get the name of the DB column associated with this field.
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Manually specify a label for this field, overriding the default
     * inflection-based naming.
     *
     * @param string $label
     * @return \Dewdrop\Db\Field
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get the label for this field, inflecting it from the DB column name if
     * one hasn't be assigned explicitly.
     *
     * @return string
     */
    public function getLabel()
    {
        if (null === $this->label) {
            $this->label = $this->inflectLabel();
        }

        return $this->label;
    }

    /**
     * Set a note that will be displayed alongside this field when it is used
     * in a UI.
     *
     * @param string $note
     * @return \Dewdrop\Db\Field
     */
    public function setNote($note)
    {
        $this->note = $note;
    }

    /**
     * Get the note associated with this field.
     *
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Manually override the default control name for this field.
     *
     * This can be useful and necessary if you are using multiple instances of
     * the same model and field on a single page and you need to disambiguate
     * them.
     *
     * @param string $controlName
     * @return \Dewdrop\Db\Field
     */
    public function setControlName($controlName)
    {
        $this->controlName = $controlName;

        return $this;
    }

    /**
     * Get the control name, using the default of "table_name:column_name" if
     * no control name has been set explicitly.
     *
     * @return string
     */
    public function getControlName()
    {
        if (null === $this->controlName) {
            $this->controlName = $this->table->getTableName() . ':' . $this->name;
        }

        return $this->controlName;
    }

    /**
     * Get a version of the control name using underscores as word separators to
     * be more friendly in CSS selectors, etc.
     *
     * @return string
     */
    public function getHtmlId()
    {
        return str_replace(':', '_', $this->getControlName());
    }

    /**
     * Get the \Zend\InputFilter\Filter object associated with this field.  This
     * object allows us to easily filter and validate values assigned to the
     * field.
     *
     * @return \Zend\InputFilter\Filter
     */
    public function getInputFilter()
    {
        if (null === $this->inputFilter) {
            $this->inputFilter = new Input($this->getControlName());

            $this->addFiltersAndValidatorsUsingMetadata($this->inputFilter);
        }

        return $this->inputFilter;
    }

    /**
     * Get an OptionPairs object for this field.  Allows you to easily
     * fetch key-value option pairs for foreign keys.
     *
     * @return \Dewdrop\Fields\OptionPairs
     */
    public function getOptionPairs()
    {
        if (null === $this->optionPairs) {
            $this->optionPairs = new OptionPairs($this->table->getAdapter());

            $ref = $this->table->getMetadata('references', $this->name);

            if ($ref) {
                $this->optionPairs->setOptions(
                    array(
                        'tableName'   => $ref['table'],
                        'valueColumn' => $ref['column']
                    )
                );
            }
        }

        return $this->optionPairs;
    }

    /**
     * Convenience method that lets you access the validator chain directly
     * instead of first having to retrieve the input filter.
     *
     * @return \Zend\Validator\ValidatorChain
     */
    public function getValidatorChain()
    {
        return $this->getInputFilter()->getValidatorChain();
    }

    /**
     * Convenience method that lets you access the filter chain directly
     * instead of first having to retrieve the input filter.
     *
     * @return \Zend\Filter\FilterChain
     */
    public function getFilterChain()
    {
        return $this->getInputFilter()->getFilterChain();
    }

    /**
     * Generate a label for this field based up the underlying database
     * column's name.
     *
     * @return string
     */
    private function inflectLabel()
    {
        return str_replace(
            array(' Of ', ' The ', ' A ', ' From '),
            array(' of ', ' the ', ' a ', ' from '),
            ucwords(
                str_replace(
                    '_',
                    ' ',
                    preg_replace('/_id$/', '', $this->name)
                )
            )
        );
    }

    /**
     * Create some basic filters and validators using the DB metadata.
     *
     * The following filters and validators are added:
     *
     * <ul>
     *     <li>For required fields, a NotEmpty validator is added.</li>
     *     <li>For text-based fields, a length validator and trim filter are added.</li>
     *     <li>For integers, an integer validator is added.</li>
     *     <li>For all floating point types, a float validator is added.</li>
     * </ul>
     *
     * @param Input $inputFilter
     * @return void
     */
    protected function addFiltersAndValidatorsUsingMetadata(Input $inputFilter)
    {
        $validators = $inputFilter->getValidatorChain();
        $filters    = $inputFilter->getFilterChain();
        $metadata   = $this->metadata;

        if ($this->isRequired() && !$this->isType('tinyint')) {
            $inputFilter->setAllowEmpty(false);
        } else {
            $inputFilter->setAllowEmpty(true);
        }

        if ($this->isType('string')) {
            if ($metadata['LENGTH']) {
                $validators->addValidator(new Validator\StringLength(0, $metadata['LENGTH']));
            }

            $filters->attach(new Filter\StringTrim());
            $filters->attach(new Filter\Null(Filter\Null::TYPE_STRING));
        } elseif ($this->isType('date')) {
            $validators->addValidator(new Validator\Date());
        } elseif ($this->isType('boolean')) {
            $filters->attach(new Filter\Int());
        } elseif ($this->isType('integer')) {
            $filters->attach(new Filter\Digits());
            $validators->addValidator(new \Zend\I18n\Validator\Int());
        } elseif ($this->isType('float')) {
            $filters->attach(new Filter\Digits());
            $validators->addValidator(new \Zend\I18n\Validator\Flaot());
        }
    }

    /**
     * Check whether the field is of the specified type.  One or more
     * types can be provided as individual arguments to this method.
     * If the field matches any of the supplied types, this method
     * will return true.
     *
     * @return boolean
     */
    public function isType()
    {
        $args = func_get_args();

        if (in_array($this->metadata['DATA_TYPE'], $args)) {
            return true;
        }

        foreach ($args as $arg) {
            $method = 'isType' . ucfirst($arg);

            if (method_exists($this, $method) && $this->$method()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convenience method for checking for common string types.  You can call
     * isType('string') to check that your field matches one of the common
     * MySQL string types.
     *
     * @return boolean
     */
    protected function isTypeString()
    {
        return $this->isType('varchar', 'char', 'text');
    }

    /**
     * Convenience method for checking for large, variable-length text data
     * types in MySQL.  You can call isType('clob') to check to see if your
     * field is a "character large object".
     *
     * @return boolean
     */
    protected function isTypeClob()
    {
        return $this->isType('text', 'mediumtext', 'longtext');
    }

    /**
     * Convenience method to check if the field is a boolean.  You can call
     * isType('boolean') and isType() will in turn call this method to see
     * if the field is a boolean.
     *
     * @return boolean
     */
    protected function isTypeBoolean()
    {
        return $this->isType('tinyint');
    }

    /**
     * Check to see if this field is numeric, either integer or float.  Calling
     * isType('numeric') will delegate to this method automatically.
     *
     * @return boolean
     */
    protected function isTypeNumeric()
    {
        return $this->isTypeInteger() || $this->isTypeFloat();
    }

    /**
     * Check to see if this field matches any of the common MySQL integer types.
     * Calling isType('integer') will automatically delegate to this method.
     *
     * @return boolean
     */
    protected function isTypeInteger()
    {
        return 'integer' === $this->metadata['DATA_TYPE'] ||
            $this->isType('int', 'mediumint', 'smallint', 'bigint');
    }

    /**
     * Check to see if this field matches any of the common MySQL float types.
     * Calling isType('float') will automatically delegate to this method.
     *
     * @return boolean
     */
    protected function isTypeFloat()
    {
        return 'float' === $this->metadata['DATA_TYPE'] ||
            $this->isType('dec', 'decimal', 'double', 'double precision', 'fixed');
    }

    /**
     * Check to see if this field is a foreign key.  Calling isType('reference')
     * will automatically delegate to this method.
     *
     * @return boolean
     */
    protected function isTypeReference()
    {
        return (boolean) $this->table->getMetadata('references', $this->name);
    }

    /**
     * Check to see if this is a many-to-many field.  Always false here, always true
     * when Dewdrop\Db\ManyToMany\Field sub-classes.
     *
     * @return boolean
     */
    protected function isTypeManyToMany()
    {
        return false;
    }
}
