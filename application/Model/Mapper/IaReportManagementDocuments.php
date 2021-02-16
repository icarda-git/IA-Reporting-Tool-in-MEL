<?php

/**
 *  Ia Report Management Documents mapper
 */
class Model_Mapper_IaReportManagementDocuments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_management_documents';
    protected $_description = 'Center IA Report Management Related document';
    protected $_shortDescription = 'Center IA Report Management Related document';
    protected $_logsKeys = array(
        'ia_report_id',
        'ia_management_document_id',
        'policy_title',
        'other_document_type',
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
