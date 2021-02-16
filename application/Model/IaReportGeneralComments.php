<?php

/**
 *  Ia Report General Comments domain model
 */
class Model_IaReportGeneralComments extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_general_comments';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'date' => COLUMN_DATE,
        'field_name' => COLUMN_STRING,
        'comment' => COLUMN_STRING,
        'reporting_year' => COLUMN_INT
    );
}
