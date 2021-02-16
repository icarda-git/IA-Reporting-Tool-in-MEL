<?php

/**
 *  Ia Report Agreements mapper
 */
class Model_Mapper_IaReportAgreements extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_agreements';
    protected $_description = 'IA report agreements';
    protected $_shortDescription = 'IA report agreements';
    protected $_logsKeys = array(
        'ia_report_id',
        'ia_agreement_id',
        'agreement_title'
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
