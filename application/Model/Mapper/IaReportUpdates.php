<?php

/**
 *  Ia Report Updates mapper
 */
class Model_Mapper_IaReportUpdates extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_updates';
    protected $_description = 'IA report entities updates';
    protected $_shortDescription = 'IA report entities updates';
    protected $_logsKeys = array(
        'ia_report_update_id',
        'ia_report_id',
        'ia_report_crp_id',
        'ia_management_document_id',
        'ia_portfolio_document_id',
        'ia_agreement_id',
        'ia_agreement_public_disclosure_id',
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
