<?php

/**
 *  Ia Report mapper
 */
class Model_Mapper_IaReport extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report';
    protected $_description = 'IA report';
    protected $_shortDescription = 'IA report';
    protected $_logsKeys = array(
        'ia_report_id',
        'ia_description'
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
