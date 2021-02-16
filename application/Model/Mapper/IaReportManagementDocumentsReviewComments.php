<?php

/**
 *   Ia Report Management Documents Review Comments mapper
 */
class Model_Mapper_IaReportManagementDocumentsReviewComments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_management_documents_review_comments';
    protected $_description = 'IA Report Management Documents review comments';
    protected $_shortDescription = 'IA Report Management Documents review comments';
    protected $_logsKeys = array(
        'ia_management_document_id',
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
