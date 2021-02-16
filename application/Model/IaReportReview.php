<?php

/**
 *  Ia Report Review domain model
 */
class Model_IaReportReview extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_review';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'review_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'ia_agreement_id' => COLUMN_INT,
        'ia_agreement_public_disclosure_id' => COLUMN_INT,
        'reviewer_id' => COLUMN_PROFILE_ID,
        'review_date' => COLUMN_DATE,
        'grade' => COLUMN_INT,
        'comments' => COLUMN_STRING,
        'external_comments' => COLUMN_STRING,
        'justification_comments' => COLUMN_STRING,
        'mls_abs_comments' => COLUMN_STRING,
        'evaluation_comments' => COLUMN_STRING,
        'is_draft' => COLUMN_INT,
        'user_group_from' => COLUMN_INT,
        'user_group_to' => COLUMN_INT
    );

    public function _getGradeDisplayName()
    {
        if ($this->ia_agreement_id != null) {
            /**
             * Grade
             * 0: Pending
             * 1: Unsatisfactory
             * 2: Satisfactory
             */
            switch ($this->grade) {
                case '0':
                    return '<span style="color: #F1C40F;">Pending</span>';
                case '1':
                    return '<span style="color: #e7505a;">Unsatisfactory</span>';
                case '2':
                    return '<span style="color: #5f7357;">Satisfactory</span>';
                default:
                    return 'NA';
            }
        } elseif ($this->ia_agreement_public_disclosure_id != null) {
            /**
             * Grade
             * 0: Pending
             * 1: Unsatisfactory
             * 2: Satisfactory
             * 3: Significant improvements
             */
            switch ($this->grade) {
                case '0':
                    return '<span style="color: #F1C40F;">Pending</span>';
                case '1':
                    return '<span style="color: #e7505a;">Improvements recommended</span>';
                case '2':
                    return '<span style="color: #5f7357;">Appears to be satisfactory</span>';
                case '3':
                    return '<span style="color: #e7505a;">Significant improvements recommended (potential compliance or reputational risk issue)</span>';
                default:
                    return 'NA';
            }
        }
        return null;
    }

    public function _getReviewedBy()
    {
        try {
            $_userMapper = new Model_Mapper_UserShort();
            return trim($_userMapper->fetchOne(array('user_id' => $this->reviewer_id))->name);
        } catch (Exception $e) {
            return null;
        }
    }

    public function ReviewerDisplayName($_groupId)
    {
        switch ($_groupId) {
            case '11':
                return 'Center feedback';
            case '12':
                return 'SMO';
            case '28':
                return 'ABS';
            case '29':
                return 'SCIPG';
            default:
                return 'Final review';
        }
    }
}
