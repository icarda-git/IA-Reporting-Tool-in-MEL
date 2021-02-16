<?php

/**
 *   Ia Report Comments User Groups mapper
 */
class Model_Mapper_IaReportCommentsUserGroups extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_comments_user_groups';
    protected $_description = 'Who can see IA report review comments';
    protected $_shortDescription = 'Who can see IA report review comments';
    protected $_logsKeys = array(
        'comment_id',
        'group_id'
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
