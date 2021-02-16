<?php

/**
 *  Ia Report Comments User Groups domain model
 */
class Model_IaReportCommentsUserGroups extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_comments_user_groups';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'comment_id' => COLUMN_INT,
        'group_id' => COLUMN_INT
    );
}
