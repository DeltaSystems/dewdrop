<?php

/**
 * Dewdrop
 *
 * @link      https://github.com/DeltaSystems/dewdrop
 * @copyright Delta Systems (http://deltasys.com)
 * @license   https://github.com/DeltaSystems/dewdrop/LICENSE
 */

namespace Dewdrop\Db\Driver;

use Dewdrop\Db\Select;

/**
 * Implementing this interface will allow you to integrate other RDBMS vendors
 * with \Dewdrop\Db\Adapter.  This interface contains methods for the platform-
 * specific portions of the database API, whereas Adapter has the code shared
 * amongst all supported platforms.
 */
interface DriverInterface
{
    /**
     * Retrieve the raw connection object used by this driver.  For example,
     * this could be a wpdb object or a PDO connection object.
     *
     * @return mixed
     */
    public function getConnection();

    /**
     * Fetch all results for the supplied SQL query.
     *
     * The SQL query can be a simple string or a Select object.  The bind array
     * should supply values for all the parameters, either named or numeric, in
     * the query.  And the fetch mode should match one of these 4 class constants
     * from \Dewdrop\Db\Adapter: ARRAY_A, ARRAY_N, OBJECT, or OBJECT_K.
     *
     * @param mixed $sql
     * @param array $bind
     * @param string $fetchMode
     * @return array
     */
    public function fetchAll($sql, $bind = array(), $fetchMode = null);

    /**
     * Fetch all results for the supplied SQL query using a PHP generator.
     *
     * This approach uses less memory, but the result set has a forward-only cursor.
     *
     * The SQL query can be a simple string or a Select object.  The bind array
     * should supply values for all the parameters, either named or numeric, in
     * the query.  And the fetch mode should match one of these 4 class constants
     * from \Dewdrop\Db\Adapter: ARRAY_A, ARRAY_N, OBJECT, or OBJECT_K.
     *
     * @param string|Select $sql
     * @param array $bind
     * @param string $fetchMode
     * @return \Generator
     */
    public function fetchAllWithGenerator($sql, $bind = [], $fetchMode = null);

    /**
     * Fetch a single column of the results from the supplied SQL statement.
     *
     * @param string|\Dewdrop\Db\Select $sql
     * @param array $bind
     * @return array
     */
    public function fetchCol($sql, $bind = array());

    /**
     * Fetch a single scalar value from the results of the supplied SQL
     * statement.
     *
     * @param string|\Dewdrop\Db\Select $sql
     * @param array $bind
     * @return mixed
     */
    public function fetchOne($sql, $bind = array());

    /**
     * Run the supplied query, binding the supplied data to the statement
     * prior to execution.
     *
     * @param string|\Dewdrop\Db\Select $sql
     * @param array $bind
     * @return mixed
     */
    public function query($sql, $bind = array());

    /**
     * Get the last insert ID from the driver after performing an insert on a table
     * with an auto-incrementing primary key.
     *
     * @return integer
     */
    public function lastInsertId();

    /**
     * Returns the symbol the adapter uses for delimited identifiers.
     *
     * @return string
     */
    public function getQuoteIdentifierSymbol();

    /**
     * Returns a list of the tables in the database.
     *
     * @return array
     */
    public function listTables();

    /**
     * Returns an associative array containing all the foreign key relationships
     * associated with the supplied table.
     *
     * The array has the following format:
     *
     * <pre>
     * array(
     *     'column_name' => array(
     *         'table'  => 'foreign_table',
     *         'column' => 'foreign_column'
     *     )
     * )
     * </pre>
     *
     * @param string $tableName
     * @return array
     */
    public function listForeignKeyReferences($tableName);

    /**
     * List all missing foreign key indexes on the supplied table.
     *
     * The resulting array has the following shape:
     *
     * <pre>
     * [
     *     ['foreign_key_id'],
     *     ['multi_column_foreign_key_id', 'second_column_foreign_key_id']
     * ]
     * </pre>
     *
     * @param $tableName
     * @return mixed
     */
    public function listMissingForeignKeyIndexes($tableName);

    /**
     * Returns an associative array containing all the unique constraints on a table.
     *
     * The array has the following format:
     *
     * <pre>
     * array(
     *     'key_name' => array(
     *         sequence_in_index => 'column_name'
     *     )
     * )
     * </pre>
     *
     * @param string $tableName
     * @return array
     */
    public function listUniqueConstraints($tableName);

    /**
     * Returns the column descriptions for a table.
     *
     * The return value is an associative array keyed by the column name,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * COLUMN_POSITION  => number; ordinal position of column in table
     * DATA_TYPE        => string; SQL datatype name of column
     * DEFAULT          => string; default expression of column, null if none
     * NULLABLE         => boolean; true if column can have nulls
     * LENGTH           => number; length of CHAR/VARCHAR
     * SCALE            => number; scale of NUMERIC/DECIMAL
     * PRECISION        => number; precision of NUMERIC/DECIMAL
     * UNSIGNED         => boolean; unsigned property of an integer type
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @param string $tableName
     * @return array
     */
    public function describeTable($tableName);

    /**
     * Given the supplied native data type, return a generic data type that can
     * be used in Dewdrop to make decisions about columns/fields:
     *
     * 1) boolean - A true/false value.
     * 2) integer - Whole number.
     * 3) float - Floating point number.
     * 4) text - Fixed-length, shorter text value.
     * 5) clob - Character large object.  Large text field.
     * 6) timestamp - Date and time combined.
     * 7) date - Just a date.
     * 8) time - Just the time.
     * 9) money - Get money, get paid.
     * 10) blob - Binary large object.
     *
     * @param string $nativeType
     * @param mixed $length
     * @return string
     */
    public function mapNativeTypeToGenericType($nativeType, $length);

    /**
     * Begin a new transaction.
     *
     * @return void
     */
    public function beginTransaction();

    /**
     * Commit the current transaction.
     *
     * @return void
     */
    public function commit();

    /**
     * Rollback the current transaction.
     *
     * @return void
     */
    public function rollback();

    /**
     * Modify a \Dewdrop\Db\Select object so that the RDBMS can calculate the
     * total number of rows that would have been returned if no LIMIT was
     * present.
     *
     * @param Select $select
     * @return void
     */
    public function prepareSelectForTotalRowCalculation(Select $select);

    /**
     * Fetch the number of rows that would have been fetched had no LIMIT
     * clause been applied to a statement.  The result set is supplied here
     * for RDBMS types (e.g. Postgres) where the total count is embedded in
     * the result set.  However, some systems (e.g. MySQL) will not need
     * to reference it.
     *
     * @param array $resultSet
     * @return integer
     */
    public function fetchTotalRowCount(array $resultSet);

    /**
     * Return the operator that can be used for case-insensitive LIKE
     * comparisons.
     *
     * @return string
     */
    public function getCaseInsensitiveLikeOperator();

    /**
     * Use the functions available in the RDBMS to truncate the provided timestamp
     * column to a date.
     *
     * @param string $timestamp
     * @return string
     */
    public function truncateTimeStampToDate($timestamp);

    /**
     * Quote the supplied input using the appropriate method for your database
     * platform/driver.
     *
     * @param string $input
     * @return string
     */
    public function quoteInternal($input);

    /**
     * Generate an SQL statement to create an index on the supplied table and
     * column(s).
     *
     * @param string $tableName
     * @param array $columnNames
     * @return string
     */
    public function generateCreateIndexStatement($tableName, array $columnNames);

    /**
     * Generate an SQL statement to analyze a table.
     *
     * @param string $tableName
     * @return string
     */
    public function generateAnalyzeTableStatement($tableName);
}
