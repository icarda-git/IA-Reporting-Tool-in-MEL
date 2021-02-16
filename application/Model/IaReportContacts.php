<?php

/**
 *  Ia Report Contacts domain model
 */
class Model_IaReportContacts extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_contacts';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_report_id' => COLUMN_INT,
        'profile_id' => COLUMN_PROFILE_ID,
        'is_primary' => COLUMN_INT
    );

    public function _getUser()
    {
        try {
            $_userMapper = new Model_Mapper_UserShort();
            return $_userMapper->fetchOne(array('user_id' => $this->profile_id));
        } catch (Exception $e) {
            return new Model_UserShort();
        }
    }
}
