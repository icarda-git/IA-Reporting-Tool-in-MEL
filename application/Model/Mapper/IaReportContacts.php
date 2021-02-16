<?php

/**
 *  Ia Report Contacts mapper
 */
class Model_Mapper_IaReportContacts extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_contacts';
    protected $_description = 'IA report contacts';
    protected $_shortDescription = 'IA report contacts';
    protected $_logsKeys = array(
        'ia_report_id',
        'profile_id'
    );
    /**
     * @var int
     * 0 =   child
     * 1 =   parent
     * 2 =   conditional
     */
    protected $_showInLogs = 0;
    /**
     * @var string
     * the log condition to add trigger to
     * if $_showInLogs = 2 then put condition here
     * if $_showInLogs is not 2 then put null here
     */
    protected $_LogCondition = null;
}
