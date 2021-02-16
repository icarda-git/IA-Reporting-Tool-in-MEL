<?php

/**
 *   Ia Report General Comments mapper
 */
class Model_Mapper_IaReportGeneralComments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_general_comments';
    protected $_description = 'IA report general comments';
    protected $_shortDescription = 'IA report general comments';
    protected $_logsKeys = array(
        'id',
        'field_name',
        'reporting_year'
    );
    /**
     * @var int
     * 0 =   child
     * 1 =   parent
     * 2 =   conditional
     */
    protected $_showInLogs = 1;
    /**
     * @var string
     * the log condition to add trigger to
     * if $_showInLogs = 2 then put condition here
     * if $_showInLogs is not 2 then put null here
     */
    protected $_LogCondition = null;
}
