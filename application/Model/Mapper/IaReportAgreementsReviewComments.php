<?php

/**
 *  Ia Report Agreements Review Comments mapper
 */
class Model_Mapper_IaReportAgreementsReviewComments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_agreements_review_comments';
    protected $_description = 'IA Report Agreements review comments';
    protected $_shortDescription = 'IA Report Agreements review comments';
    protected $_logsKeys = array(
        'ia_agreement_id',
        'comment_id'
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
