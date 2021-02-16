<?php

/**
 *   Ia Report Updates Review Comments mapper
 */
class Model_Mapper_IaReportUpdatesReviewComments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_updates_review_comments';
    protected $_description = 'IA Report Updates review comments';
    protected $_shortDescription = 'IA Report Updates review comments';
    protected $_logsKeys = array(
        'ia_report_update_id',
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
