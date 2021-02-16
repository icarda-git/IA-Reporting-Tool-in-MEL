const IaReviewEvents = function () {
    const initEvents = function () {
        $('body')
            .on('click', '.btn_add_ia_agreement_review', function () {
                let modal = $('#ia_report_agreement_review_modal');
                const ia_agreement_id = $(this).data('ia_agreement_id');

                modal.find('form').show();
                modal.find('input, select, textarea').val('').change();
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_agreement_id]').val(ia_agreement_id);

                $.getJSON(CheckIaReportAgreementReviewStatusLink + '/ia_agreement_id/' + ia_agreement_id, function (data) {
                    if (!data.result) {
                        App.alert({
                            type: 'danger',  // alert's type
                            message: data.message,  // alert's message
                            closeInSeconds: 10, // auto close after defined seconds
                        });
                        return;
                    }
                    modal.find('select[name=grade] option').prop('disabled', false);
                    modal.find('select[name=grade]').parents('.form-group:first').show()
                        .find('select').prop('disabled', false).select2();

                    let previous_reviews = '';

                    $.each(data.data, function (index, review) {
                        let reviewed_by = review.reviewed_by != null ? review.reviewed_by + ' (' + review.from_display_name + ')' : review.from_display_name;
                        let reviewed_to = review.user_group_to != null ? '<b>To: </b><i>' + review.to_display_name + '</i>' : '<b>' + review.to_display_name + '</b>';

                        if (parseInt(review.is_draft) === 1) {
                            modal.find('select[name=grade]').val(review.grade).change();
                            modal.find('textarea[name=comments]').val(review.comments);
                            modal.find('textarea[name=justification_comments]').val(review.justification_comments);
                            modal.find('textarea[name=mls_abs_comments]').val(review.mls_abs_comments);
                            modal.find('textarea[name=evaluation_comments]').val(review.evaluation_comments);
                        } else if (parseInt(review.user_group_from) === 11) { //user_group_to = User group 11 Intellectual Property Focal Point
                            previous_reviews += '<div class="col-md-12 review-block center-revise">' +
                                '    <b>By: </b><i>' + reviewed_by + '</i> <i>- ' + review.review_date + '</i><br>' + reviewed_to +
                                '</div>';
                        } else {
                            previous_reviews += '<div class="col-md-12 review-block grade-' + review.grade + '">' +
                                '    <label class="control-label">';
                            if (review.grade_display_name != null)
                                previous_reviews += '<b>' + review.grade_display_name + '</b>';

                            if (review.comments != null && review.comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>Internal Comments:</b><br>' + review.comments + '</p>';
                            if (review.justification_comments != null && review.justification_comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>Justification:</b><br>' + review.justification_comments + '</p>';
                            if (review.mls_abs_comments != null && review.mls_abs_comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>MLS/ABS:</b><br>' + review.mls_abs_comments + '</p>';
                            if (review.evaluation_comments != null && review.evaluation_comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>Evaluation:</b><br>' + review.evaluation_comments + '</p>';

                            previous_reviews += '<div><b>By: </b><i>' + reviewed_by + '</i> <i>- ' + review.review_date + '</i><br>' + reviewed_to + '</div></div>';
                        }
                    });
                    modal.find('.previous_reviews').html(previous_reviews);

                    modal.find('.review_form_submit').hide();
                    modal.find('.review_form_submit[data-user_group_to="-1"]').show();

                    /**
                     * User groups
                     * 11: Intellectual Property Focal Point
                     * 12: Intellectual Property Focal Point (PMU)
                     * 28: ABS
                     * 29: SCIPG
                     */
                    if (data.can_review_ia_report && data.can_review_step) {
                        if (parseInt(data.user_group_to) === 12) {
                            modal.find('.review_form_submit[data-user_group_to="28"]').show();//To MLS
                            modal.find('.review_form_submit[data-user_group_to="29"]').show();//To SCIPG
                            modal.find('.review_form_submit[data-user_group_to="11"]').show();//To Center
                            modal.find('.review_form_submit[data-user_group_to="0"]').show();//Final submit
                        } else if (parseInt(data.user_group_to) === 28) {
                            modal.find('select[name=grade]').parents('.form-group:first').hide()
                                .find('select').prop('disabled', true).select2(); //MLS cannot grade
                            modal.find('.review_form_submit[data-user_group_to="12"]').show();//To SMO
                        } else if (parseInt(data.user_group_to) === 29) {
                            modal.find('.review_form_submit[data-user_group_to="12"]').show();//To SMO
                        } else {
                            modal.find('form').hide();
                            modal.find('.review_form_submit[data-user_group_to="-1"]').hide();
                        }
                    } else {
                        modal.find('form').hide();
                        modal.find('.review_form_submit[data-user_group_to="-1"]').hide();
                    }
                    if (previous_reviews === '')
                        modal.find('.previous_reviews').html('<div class="no_review_available">Review data is not available</div>');
                    modal.modal();
                });
            })
            .on('click', '.btn_add_ia_public_disclosure_review', function () {
                let modal = $('#ia_report_public_disclosure_review_modal');
                const ia_agreement_public_disclosure_id = $(this).data('ia_agreement_public_disclosure_id');

                modal.find('form').show();
                modal.find('input, select, textarea').val('').change();
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_agreement_public_disclosure_id]').val(ia_agreement_public_disclosure_id);

                $.getJSON(CheckIaReportAgreementReviewStatusLink + '/ia_agreement_public_disclosure_id/' + ia_agreement_public_disclosure_id, function (data) {
                    if (!data.result) {
                        App.alert({
                            type: 'danger',  // alert's type
                            message: data.message,  // alert's message
                            closeInSeconds: 10, // auto close after defined seconds
                        });
                        return;
                    }
                    modal.find('select[name=grade] option').prop('disabled', false).select2();
                    modal.find('select[name=grade]').parents('.form-group:first').show()
                        .find('select').prop('disabled', false).select2();

                    let previous_reviews = '';

                    $.each(data.data, function (index, review) {
                        let reviewed_by = review.reviewed_by != null ? review.reviewed_by + ' (' + review.from_display_name + ')' : review.from_display_name;
                        let reviewed_to = review.user_group_to != null ? '<b>To: </b><i>' + review.to_display_name + '</i>' : '<b>' + review.to_display_name + '</b>';

                        if (parseInt(review.is_draft) === 1) {
                            modal.find('select[name=grade]').val(review.grade).change();
                            modal.find('textarea[name=comments]').val(review.comments);
                            modal.find('textarea[name=external_comments]').val(review.external_comments);
                        } else if (parseInt(review.user_group_from) === 11) { //user_group_to = User group 11 Intellectual Property Focal Point
                            previous_reviews += '<div class="col-md-12 review-block center-revise">' +
                                '    <b>By: </b><i>' + reviewed_by + ' - ' + review.review_date + '</i><br>' + reviewed_to +
                                '</div>';
                        } else {
                            previous_reviews += '<div class="col-md-12 review-block grade-' + review.grade + '">' +
                                '    <label class="control-label">';
                            if (review.grade_display_name != null)
                                previous_reviews += '<b>' + review.grade_display_name + '</b>';
                            if (review.comments != null && review.comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>Internal Comments:</b><br>' + review.comments + '</p>';
                            if (review.external_comments != null && review.external_comments !== '')
                                previous_reviews += '<p style="white-space: pre-line;"><b>Comments:</b><br>' + review.external_comments + '</p>';
                            previous_reviews += '<div><b>By: </b><i>' + reviewed_by + ' - ' + review.review_date + '</i><br>' + reviewed_to + '</div></div>';
                        }
                    });

                    modal.find('.previous_reviews').html(previous_reviews);

                    modal.find('.review_form_submit').hide();
                    modal.find('.review_form_submit[data-user_group_to="-1"]').show();

                    /**
                     * User groups
                     * 11: Intellectual Property Focal Point
                     * 12: Intellectual Property Focal Point (PMU)
                     * 28: ABS
                     * 29: SCIPG
                     */
                    if (data.can_review_ia_report && data.can_review_step) {
                        if (parseInt(data.user_group_to) === 12) {
                            modal.find('.review_form_submit[data-user_group_to="28"]').show();//To MLS
                            modal.find('.review_form_submit[data-user_group_to="29"]').show();//To SCIPG
                            modal.find('.review_form_submit[data-user_group_to="11"]').show();//To Center
                            modal.find('.review_form_submit[data-user_group_to="0"]').show();//Final submit
                        } else if (parseInt(data.user_group_to) === 28) {
                            modal.find('select[name=grade]').parents('.form-group:first').hide()
                                .find('select').prop('disabled', true).select2(); //MLS cannot grade
                            modal.find('.review_form_submit[data-user_group_to="12"]').show();//To SMO
                        } else if (parseInt(data.user_group_to) === 29) {
                            modal.find('.review_form_submit[data-user_group_to="12"]').show();//To SMO
                        } else {
                            modal.find('form').hide();
                            modal.find('.review_form_submit[data-user_group_to="-1"]').hide();
                        }
                    } else {
                        modal.find('form').hide();
                        modal.find('.review_form_submit[data-user_group_to="-1"]').hide();
                    }

                    if (previous_reviews === '')
                        modal.find('.previous_reviews').html('<div class="no_review_available">Review data is not available</div>');
                    modal.modal();
                });
            })
            .on('click', '.review_form_submit', function () {
                let target_form = $($(this).data('form'));
                let user_group_to = $(this).data('user_group_to');
                target_form.find('input[name=related_items_confirmed]').val('0');
                target_form.find('input[name=user_group_to]').val(user_group_to);
                target_form.submit();
            });
    };

    const initForms = function () {
        /**
         * Form validation
         */
        IaEvents.handleValidation($('#ia_report_agreement_review_form'), 'form-group', [], true, null, function (data, target_modal, target_form) {
            review_submit_callback(data, target_modal, target_form, 'This item explanation has been marked as identical to the following item(s)');
        });
        IaEvents.handleValidation($('#ia_report_public_disclosure_review_form'), 'form-group', [], true, null, function (data, target_modal, target_form) {
            review_submit_callback(data, target_modal, target_form, 'The explanation for the LEA/ RUA/ IP Application related to this item has been marked as identical to the following items LEA/ RUA/ IP Application');
        });

        function review_submit_callback(data, target_modal, target_form, message_text) {
            if (!data.result && data.hasOwnProperty('review_has_related_items') && data.review_has_related_items === true) {
                if (data.hasOwnProperty('related_items') && data.related_items.length > 0) {
                    let message = '<p>' + message_text + ', do you want to apply the current review for those item(s) too?</p>';
                    message += '<ul>';
                    $.each(data.related_items, function (index, item) {
                        message += '<li>' + item.display_name + '</li>';
                    });
                    message += '</ul>';

                    bootbox.dialog({
                        message: message,
                        className: 'dialogWide',
                        buttons: {
                            blue: {
                                label: 'Yes',
                                className: 'btn-success',
                                callback: function () {
                                    target_form.find('input[name=related_items_confirmed]').val('yes');
                                    target_form.submit();
                                }
                            },
                            red: {
                                label: 'No',
                                className: 'btn-info',
                                callback: function () {
                                    target_form.find('input[name=related_items_confirmed]').val('ignore');
                                    target_form.submit();
                                }
                            },
                            white: {
                                label: 'Cancel',
                                className: 'btn-default',
                            }
                        }
                    });
                }
            } else if (data.result && data.hasOwnProperty('failed_related_reviews') && data.failed_related_reviews.length > 0) {
                $('.show_responsibilities').change();

                let message = data.message + ' Except for the following items:'
                message += '<ul>';
                $.each(data.failed_related_reviews, function (index, item) {
                    message += '<li>' + item.display_name + '</li>';
                });
                message += '</ul>';
                bootbox.alert(message);

            } else if (data.result) {
                $('.show_responsibilities').change();
            }
        }

        let parents = $('#ia_report_agreement_review_form, #ia_report_public_disclosure_review_form');

        parents.find('select.select2').select2({allowClear: true});
    };

    return {
        //main function to initiate the module
        init: function () {
            initEvents();
            initForms();
        }
    };
}();
