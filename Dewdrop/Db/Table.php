<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Db;

use Dewdrop\Paths;
use Dewdrop\Exception;
use Dewdrop\Db\Eav\Definition as EavDefinition;
use Dewdrop\Db\ManyToMany\Field as ManyToManyField;
use Dewdrop\Db\ManyToMany\Relationship as ManyToManyRelationship;
use Dewdrop\Db\Row;
use Dewdrop\Db\Field;

/**
 * The table class provides a gateway to the a single DB table by providing
 * utility methods for querying the table and finding specific rows within
 * it.
 */
abstract class Table
{
    /**
     * Field providers can check for the existence of and create objects
     * for fields of various types (e.g. concrete DB columns or EAV
     * fields).
     *
     * @var array
     */
    private $fieldProviders = array();

    /**
     * Any field objects that have been generated for this table.
     *
     * @var array
     */
    private $fields = array();

    /**
     * The ManyToMany relationships that have been added to this table.  These
     * relationships integrate with the other aspects of the DB API.
     *
     * @var array
     */
    private $manyToMany = array();

    /**
     * If this table has a corresponding set of EAV tables that can be used to
     * define custom fields/attibutes for the model, this variable will be
     * a reference to the EAV definition object.
     *
     * @var \Dewdrop\Db\Eav\Definition
     */
    private $eav;

    /**
     * Callbacks assigned during the init() method of your table sub-class,
     * which will be used to tweak the default field settings away from the
     * defaults inferred from DB metadata.
     *
     * @var array
     */
    private $fieldCustomizationCallbacks = array();

    /**
     * The default row class for this table object.  If you'd like to use
     * a custom row class for your model, you can set it in your init()
     * method.
     *
     * @var string
     */
    private $rowClass = '\Dewdrop\Db\Row';

    /**
     * The database adapter used by this table
     *
     * @var \Dewdrop\Db\Adapter
     */
    private $db;

    /**
     * Paths utility to help in finding DB metadata files
     *
     * @var \Dewdrop\Paths
     */
    private $paths;

    /**
     * The name of the DB table represented by this table class.
     *
     * @var string
     */
    private $tableName;

    /**
     * The metadata generated by the db-metadata CLI command or the dbdeploy
     * CLI command for this table.  This is used to provide your plugin with
     * information about the columns and constraints on the underlying DB
     * table.
     *
     * @var array
     */
    private $metadata;

    /**
     * A pluralized version of this table's title.  If not manually specified,
     * the title will be inflected from teh table name.
     *
     * @var string
     */
    private $pluralTitle;

    /**
     * A singularized version of this table's title.  If not manually specified,
     * the title will be inflected from teh table name.
     *
     * @var string
     */
    private $singularTitle;

    /**
     * Create new table object with supplied DB adapter
     *
     * @param Adapter $db
     * @param Paths $paths
     */
    public function __construct(Adapter $db, Paths $paths = null)
    {
        $this->db    = $db;
        $this->paths = ($paths ?: new Paths());

        $this->fieldProviders[] = new FieldProvider\Metadata($this);
        $this->fieldProviders[] = new FieldProvider\ManyToMany($this);
        $this->fieldProviders[] = new FieldProvider\Eav($this);

        $this->init();

        if (!$this->tableName) {
            throw new Exception('You must call setTableName() in your init() method.');
        }
    }

    /**
     * This method should be used by sub-classes to set the table name,
     * create field customization callbacks, etc.
     *
     * @return void
     */
    abstract public function init();

    /**
     * Return a listing for the admin.
     *
     * @return array
     */
    public function fetchAdminListing()
    {
        $orderSpec = array();

        foreach ($this->getPrimaryKey() as $primaryKeyColumn) {
            $orderSpec[] = $primaryKeyColumn;
        }

        $select = $this->select()
            ->from($this->getTableName())
            ->order($orderSpec);

        return $this->db->fetchAll($select);
    }

    /**
     * Register an EAV definition with this table.
     *
     * @param array $options Additional options to pass to the EAV definition.
     * @return \Dewdrop\Db\Table
     */
    public function registerEav(array $options = array())
    {
        $this->eav = new EavDefinition($this, $options);

        return $this;
    }

    /**
     * Check to see if this model has an EAV definition.
     *
     * @return boolean
     */
    public function hasEav()
    {
        return $this->eav instanceof EavDefinition;
    }

    /**
     * Get the EAV definition associated with this table.
     *
     * @return \Dewdrop\Db\Eav\Definition
     */
    public function getEav()
    {
        return $this->eav;
    }

    /**
     * Register a many-to-many relationship with this table.  This will allow
     * you to retrieve and set the values for this relationship from row
     * objects and also generate field objects representing this relationship.
     *
     * Generally, supplying the relationship name and the cross-reference table
     * name are all you need to do to register the relationship.  However, if
     * Dewdrop cannot determine the additional pieces of information needed to
     * support the relationship, you can specify those manually using the
     * additional options array.
     *
     * Once registered, you can use the relationship name you supplied as if it
     * was a normal field.  So, you can do things like:
     *
     * <pre>
     * $row->field('my_relationship_name');
     * </pre>
     *
     * Or:
     *
     * <pre>
     * $this->insert(
     *     array(
     *         'name'                 => 'Concrete DB column value',
     *         'foo_id'               => 2,
     *         'my_relationship_name' => array(1, 2, 3)
     *     )
     * );
     * </pre>
     *
     * In the latter example, Dewdrop will automatically save the cross-reference
     * table values following the primary INSERT query.
     *
     * @param string $relationshipName
     * @param string $xrefTableName
     * @param array $additionalOptions
     */
    public function hasMany($relationshipName, $xrefTableName, array $additionalOptions = array())
    {
        $relationship = new ManyToManyRelationship($this, $xrefTableName);

        $relationship->setOptions($additionalOptions);

        if (array_key_exists($relationshipName, $this->manyToMany)) {
            throw new Exception(
                "Db\\Table: A ManyToMany relationship named \"$relationshipName\" already"
                . 'exists on this table.  Please supply an alternative relationship name '
                . 'as the second parameter to the hasMany() method.'
            );
        }

        $this->manyToMany[$relationshipName] = $relationship;

        return $this;
    }

    /**
     * Determine if this table has a many-to-many relationship with the given
     * name.
     *
     * @param string $name
     * @return boolean
     */
    public function hasManyToManyRelationship($name)
    {
        return array_key_exists($name, $this->manyToMany);
    }

    /**
     * Retrieve the many-to-many relationship with the given name.  You should
     * call hasManyToManyRelationship() prior to this to ensure the relationship
     * exists.
     *
     * @param string $name
     * @return \Dewdrop\Db\ManyToMany\Relationship
     */
    public function getManyToManyRelationship($name)
    {
        return $this->manyToMany[$name];
    }

    /**
     * Return array of all many-to-many relationship assigned to this table.
     *
     * @return array
     */
    public function getManyToManyRelationships()
    {
        return $this->manyToMany;
    }

    /**
     * Get an array representing the valid column names that can be get or set
     * on a row object tied to this table.  This will include the concrete
     * columns present in the physical database table and the many-to-many
     * or EAV fields registered with and managed by this table object.
     *
     * @return array
     */
    public function getRowColumns()
    {
        $columns = array();

        foreach ($this->fieldProviders as $provider) {
            $columns = array_merge($columns, $provider->getAllNames());
        }

        return $columns;
    }

    /**
     * Retrieve the field object associated with the specified name.
     *
     * @param string $name
     * @return \Dewdrop\Db\Field
     */
    public function field($name)
    {
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        $field = null;

        foreach ($this->fieldProviders as $provider) {
            if ($provider->has($name)) {
                $field = $provider->instantiate($name);
            }
        }

        if (!$field) {
            throw new Exception("Db\\Table: Attempting to retrieve unknown field \"{$name}\"");
        }

        // Store reference to field so we can return the same instance on subsequent calls
        $this->fields[$name] = $field;

        if (isset($this->fieldCustomizationCallbacks[$name])) {
            call_user_func($this->fieldCustomizationCallbacks[$name], $field);
        }

        return $field;
    }

    /**
     * Assign a callback that will allow you to further customize a field
     * object whenever that object is requested using the table's field()
     * method.
     *
     * @param string $name
     * @param mixed $callback
     * @return \Dewdrop\Db\Table
     */
    public function customizeField($name, $callback)
    {
        if (!$this->getMetadata('columns', $name) && !array_key_exists($name, $this->manyToMany)) {
            throw new Exception("Db\\Table: Setting customization callback for unknown column \"{$name}\"");
        }

        $this->fieldCustomizationCallbacks[$name] = $callback;

        return $this;
    }

    /**
     * Assign a DB table name to this model.
     *
     * @param string $tableName
     * @returns \Dewdrop\Db\Table
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;

        return $this;
    }

    /**
     * Get the DB table name assigned to this model.
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * Allow setting of custom row class for this table.
     *
     * @param string $rowClass
     * @return \Dewdrop\Db\Table
     */
    public function setRowClass($rowClass)
    {
        $this->rowClass = $rowClass;

        return $this;
    }

    /**
     * Override the default singular title.
     *
     * @param string $singularTitle
     * @return \Dewdrop\Db\Table
     */
    public function setSingularTitle($singularTitle)
    {
        $this->singularTitle = $singularTitle;

        return $this;
    }

    /**
     * Get a singular title (e.g. "Fruit", not "Fruits") for this model.
     *
     * If no title is set, we'll pull the inflected version from the table's
     * metadata.
     *
     * @return string
     */
    public function getSingularTitle()
    {
        if (!$this->singularTitle) {
            $this->singularTitle = $this->getMetadata('titles', 'singular');
        }

        return $this->singularTitle;
    }

    /**
     * Manually override the inflected plural title for this model.
     *
     * @param string $pluralTitle
     * @return \Dewdrop\Db\Table
     */
    public function setPluralTitle($pluralTitle)
    {
        $this->pluralTitle = $pluralTitle;

        return $this;
    }

    /**
     * Get a singular title (e.g. "Fruits", not "Fruit") for this model.
     *
     * If no title is set, we'll pull the inflected version from the table's
     * metadata.
     *
     * @return string
     */
    public function getPluralTitle()
    {
        if (!$this->pluralTitle) {
            $this->pluralTitle = $this->getMetadata('titles', 'plural');
        }

        return $this->pluralTitle;
    }

    /**
     * Load this table's metadata from the file generated by the db-metadata
     * CLI command.  The metadata currently has two sections:
     *
     * - titles: Default singular and plural titles for the model.
     * - columns: The columns in the table with types, constraints, etc.
     *
     * You can retrieve the entirety of the metadata information by providing
     * null values to both arguments.  You can retrieve an entire section of
     * the metdata by only specifying the first argument.  Or, you can specify
     * values for both arguments to retrieve a specific member of a specific
     * section.
     *
     * For example, to get metadata only for the "name" column, you would call:
     *
     * <code>
     * $this->getMetadata('columns', 'name');
     * </code>
     *
     * @param string $section
     * @param string $index
     * @return array
     */
    public function getMetadata($section = null, $index = null)
    {
        if (!$this->metadata) {
            $this->metadata = $this->db->getTableMetadata($this->tableName);
        }

        if ($section && $index) {
            if (isset($this->metadata[$section][$index])) {
                return $this->metadata[$section][$index];
            } else {
                return false;
            }
        } elseif ($section) {
            if (isset($this->metadata[$section])) {
                return $this->metadata[$section];
            } else {
                return false;
            }
        } else {
            return $this->metadata;
        }
    }

    /**
     * Get the names of the columns in the primary key.  This will always
     * return an array of column names, even if there is only one column
     * in the table's primary key.
     *
     * @return array
     */
    public function getPrimaryKey()
    {
        $columns = array();

        foreach ($this->getMetadata('columns') as $column => $metadata) {
            if ($metadata['PRIMARY']) {
                $position  = $metadata['PRIMARY_POSITION'];

                $columns[$position] = $column;
            }
        }

        ksort($columns);

        return array_values($columns);
    }

    /**
     * Get the DB adapter associated with this table object.
     *
     * @return \Dewdrop\Db\Adapter
     */
    public function getAdapter()
    {
        return $this->db;
    }

    /**
     * Create a new \Dewdrop\Db\Select object.
     *
     * @return \Dewdrop\Db\Select
     */
    public function select()
    {
        return $this->db->select();
    }

    /**
     * Insert a new row.
     *
     * Data should be supplied as key value pairs, with the keys representing
     * the column names.
     *
     * @param array $data
     * @return integer Number of affected rows.
     */
    public function insert(array $data)
    {
        $result = $this->db->insert(
            $this->tableName,
            $this->filterDataArrayForPhysicalColumns($data)
        );

        $this
            ->saveManyToManyRelationships($data)
            ->saveEav($data);

        return $result;
    }

    /**
     * Update an existing row.
     *
     * Data should be supplied as key value pairs, with the keys representing
     * the column names.  The where clause should be an already assembled
     * and quoted string.  It should not be prefixed with the "WHERE" keyword.
     *
     * @param array $data
     * @param string $where
     */
    public function update(array $data, $where)
    {
        $updateData = $this->filterDataArrayForPhysicalColumns($data);

        // Only perform primary update statement if a physical column is being updated
        if (count($updateData)) {
            $result = $this->db->update($this->tableName, $updateData, $where);
        }

        $this
            ->saveManyToManyRelationships($data)
            ->saveEav($data);

        return $result;
    }

    /**
     * Deletes existing rows.
     *
     * @param  array|string $where SQL WHERE clause(s).
     * @return int          The number of rows deleted.
     */
    public function delete($where)
    {
        return $this->db->delete($this->tableName, $where);
    }

    /**
     * Find a single row based upon primary key value.
     *
     * @return \Dewdrop\Db\Row
     */
    public function find()
    {
        return $this->fetchRow($this->assembleFindSql(func_get_args()));
    }

    /**
     * Find the data needed to refresh a row object's data based upon its
     * primary key value.
     *
     * @param array $args
     * @return array
     */
    public function findRowRefreshData(array $args)
    {
        return $this->db->fetchRow(
            $this->assembleFindSql($args),
            array(),
            ARRAY_A
        );
    }

    /**
     * Create a new row object, assigning the provided data as its initial
     * state.
     *
     * @param array $data
     * @return \Dewdrop\Db\Row
     */
    public function createRow(array $data = array())
    {
        $className = $this->rowClass;
        return new $className($this, $data);
    }

    /**
     * Fetch a single row by running the provided SQL.
     *
     * @param string|\Dewdrop\Db\Select $sql
     * @return \Dewdrop\Db\Row
     */
    public function fetchRow($sql)
    {
        $className = $this->rowClass;
        $data      = $this->db->fetchRow($sql, array(), ARRAY_A);

        return new $className($this, $data);
    }

    /**
     * Assemble WHERE clause for  for finding a row by its primary key.
     *
     * @param array $args The primary key values
     * @return string
     */
    public function assembleFindWhere(array $args)
    {
        $pkey = $this->getPrimaryKey();

        foreach ($pkey as $index => $column) {
            if (!isset($args[$index])) {
                $pkeyColumnCount = count($pkey);
                throw new Exception(
                    "Db\\Table: You must specify a value for all {$pkeyColumnCount} primary key columns"
                );
            }

            $where[] = $this->db->quoteInto(
                $this->db->quoteIdentifier($column) . ' = ?',
                $args[$index]
            );
        }

        return implode(' AND ', $where);
    }

    /**
     * Assemble SQL for finding a row by its primary key.
     *
     * @param array $args The primary key values
     * @return string
     */
    private function assembleFindSql(array $args)
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->db->quoteIdentifier($this->tableName),
            $this->assembleFindWhere($args)
        );

        return $sql;
    }

    /**
     * Filter the data array that was passed to either insert() or update() so
     * that it only has keys that match physical database columns, not
     * many-to-many relationships managed by this table.
     *
     * @param array $data
     * @return array The filtered version of the data array.
     */
    private function filterDataArrayForPhysicalColumns(array $data)
    {
        foreach ($data as $column => $value) {
            if (!$this->getMetadata('columns', $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    /**
     * Save any many-to-many relationship values that were passed to
     * insert() or update().
     *
     * @param array $data
     * @return \Dewdrop\Db\Table
     */
    private function saveManyToManyRelationships(array $data)
    {
        foreach ($data as $name => $values) {
            if ($this->hasManyToManyRelationship($name)) {
                $relationship = $this->getManyToManyRelationship($name);
                $anchorName   = $relationship->getSourceColumnName();

                // When the anchor column already has a value, use it, otherwise get insert ID
                if (isset($data[$anchorName]) && $data[$anchorName]) {
                    $anchorValue = $data[$anchorName];
                } else {
                    $anchorValue = $this->getAdapter()->lastInsertId();
                }

                // Can only save xref values if we have anchor value
                if ($anchorValue) {
                    $relationship->save($values, $anchorValue);
                }
            }
        }

        return $this;
    }

    /**
     * Save any EAV attributes matching keys in the supplied data array.  We can
     * only save EAV attributes, if there is a primary key value available, either
     * via the $data array itself or the lastInsertId().
     *
     * @param array $data
     * @return \Dewdrop\Db\Table
     */
    private function saveEav(array $data)
    {
        if ($this->hasEav()) {
            $pkey = array();
            $eav  = $this->getEav();

            foreach ($this->getPrimaryKey() as $pkeyColumn) {
                if (isset($data[$pkeyColumn]) && $data[$pkeyColumn]) {
                    $pkey[] = $data[$pkeyColumn];
                } else {
                    $pkey[] = $this->getAdapter()->lastInsertId();
                }
            }

            foreach ($data as $name => $value) {
                if ($eav->hasAttribute($name)) {
                    $eav->save($name, $value, $pkey);
                }
            }
        }

        return $this;
    }
}
