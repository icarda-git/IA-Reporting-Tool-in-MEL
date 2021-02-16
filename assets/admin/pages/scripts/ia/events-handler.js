const IaEvents = function () {
    const initEvents = function () {

        const policyModalNumbering = function (parent, prefix) {
            parent.find('.numbering:not(.ignore_numbering)').each(function (index, element) {
                $(this).text(prefix + (index + 1));
            });
        };

        $('body')
            .on('change', '#ia_form input[name=reporting_year]', function () {
                let element = $(this);
                let parent = element.parents('.portlet:first');

                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let reporting_year = element.val();

                if (parseInt(ia_report_data_json.reporting_year) === parseInt(reporting_year))
                    $('#ia_form input[name=ia_report_id]').val(ia_report_data_json.ia_report_id);

                $.getJSON(GetIaReportDataLink + '/reporting_year/' + reporting_year + '/ia_report_id/' + IaEvents.getIaReportId(), function (data) {
                    if (parseInt(data.ia_report_id) > 0) {
                        bootbox.dialog({
                            message: 'The year “' + reporting_year + '” has been already reported.',
                            buttons: {
                                close: {
                                    label: 'Cancel',
                                    className: 'btn-default',
                                    callback: function () {
                                        element.DatePicker('setDate', ia_report_data_json.reporting_year);
                                        App.unblockUI(parent);
                                    }
                                },
                                yes: {
                                    label: 'Go to “' + reporting_year + '” report',
                                    className: 'btn-info',
                                    callback: function () {
                                        window.location.pathname = '/ia/iareporting/ia_report_id/' + data.ia_report_id;
                                    }
                                }
                            }
                        });
                    } else {
                        App.unblockUI(parent);
                    }
                });
            })
            .on('click', '#ia_form #add_point_of_contact', function () {
                IaEvents.createNewRow('point_of_contact_row', $('#ia_form'), false);
            })
            .on('click', '.point_of_contact_rows .delete_point_of_contact', function () {
                $(this).parents('.point_of_contact_row:first').fadeOut('slow', function () {
                    $(this).find('select.select2User').userSelect2();
                    $(this).remove();
                });
            })
            .on('change', '#ia_form .ia_report_contacts_select', function () {
                window[$(this).attr('disabled_ids')] = [];
                $('#ia_form .ia_report_contacts_select').each(function () {
                    window[$(this).attr('disabled_ids')].push($(this).val());
                });

                let element = $(this);
                let profile_id = element.val();
                if (profile_id !== '' && profile_id != null)
                    handle_user_missing_data.init([element.find('option:selected').data().data], ['email'], element);
            })
            .on('change', '.related_explain_box', function () {
                let target = $(this).data('target');
                let show_value = null;
                try {
                    show_value = $(this).data('show_value').toString();
                } catch (e) {
                }
                let value = null;
                try {
                    if ($(this).is('[type=radio]'))
                        value = $('input[type=radio][data-target="' + target + '"].related_explain_box:checked').val().toString();
                    else if ($(this).is('[type=checkbox]'))
                        value = $('input[type=checkbox][data-target="' + target + '"].related_explain_box:checked').val().toString();
                    else
                        value = $('*[data-target="' + target + '"].related_explain_box').val().toString();
                } catch (e) {
                }

                let element = $('.form-group, .row').filter('.' + target);
                if (value === show_value)
                    element.slideDown('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                else
                    element.slideUp('slow').find('input, select, textarea').prop('disabled', true);
                $.uniform.update();
            })
            .on('change', 'input[name=restricted_agreement]', function () {
                if (parseInt($('input[name=restricted_agreement]:checked').val()) === 0)
                    $('.restricted_agreement_no_explain').slideDown('slow').find('textarea:not(.keep_disabled)').prop('disabled', false);
                else
                    $('.restricted_agreement_no_explain').slideUp('slow').find('textarea').prop('disabled', true);
            })
            .on('change', 'input[name=germplasm_incorporated]', function () {
                if (parseInt($('input[name=germplasm_incorporated]:checked').val()) === 0)
                    $('.germplasm_incorporated_no_explain').slideDown('slow').find('textarea:not(.keep_disabled)').prop('disabled', false);
                else
                    $('.germplasm_incorporated_no_explain').slideUp('slow').find('textarea').prop('disabled', true);
            })
            .on('change', 'input[name=biological_resources_utilized_benefit]', function () {
                if (parseInt($('input[name=biological_resources_utilized_benefit]:checked').val()) === 0)
                    $('.no_abs_obligations_apply').slideDown('slow').find('textarea:not(.keep_disabled)').prop('disabled', false);
                else
                    $('.no_abs_obligations_apply').slideUp('slow').find('textarea').prop('disabled', true);
            })
            .on('click', '.btn_edit_ia_report_crp', function () {
                let ia_report_id = $(this).data('ia_report_id');
                let ia_report_crp_id = $(this).data('id');
                //If add new item and the reports is not saved or if the reporting year is changed
                if ((!(parseInt(ia_report_crp_id) > 0) && !(parseInt(IaEvents.getIaReportId()) > 0)) || IaEvents.IaReportingYearChanged()) {
                    askToSubmitIAForm();
                    return;
                }

                let parent = $(this).parents('.portlet:first');
                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let modal = $('#ia_report_crp_modal');
                let ia_report_crp_form = modal.find('#ia_report_crp_form');
                DisableFormFields(ia_report_crp_form, false);

                modal.find('input:not([type=radio], [type=checkbox]), select, textarea').val('').change();
                modal.find('input[name=main_ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=reporting_year]').val(IaEvents.getIaReportingYear());
                modal.find('.update_rows').html('');

                let is_replicate = $(this).data('is_replicate');
                ia_report_crp_form.data('item_id', is_replicate || !ia_report_crp_id ? null : ia_report_crp_id);

                if (parseInt(ia_report_crp_id) > 0) {
                    $.getJSON(GetIaReportCrpDataLink + '/ia_report_crp_id/' + ia_report_crp_id + '/ia_report_id/' + ia_report_id, function (data) {
                        if (!data.result) {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 5, // auto close after defined seconds
                            });
                            App.unblockUI(parent);
                            return;
                        }

                        ia_report_crp_form.find('b.item_reporting_year').html(data.data.reporting_year);

                        $.each(data.data.updates, function (index, update) {
                            IaEvents.prepare_update_block(ia_report_crp_form, update);
                        });

                        if (data.data.can_edit_ia_report !== true && data.data.can_update_ia_report !== true && !is_replicate)
                            DisableFormFields(ia_report_crp_form, true);

                        ia_report_crp_form.find('.fa.add-comment').removeClass('ignore_commentable');
                        if (!iaReportReviewPage && data.data.reporting_year !== IaEvents.getIaReportingYear())
                            ia_report_crp_form.find('.fa.add-comment').addClass('ignore_commentable');

                        ia_report_crp_form.find('select[name="crp_id"]').partnerSelect2Val(data.data.crp_id, function () {
                            $.each(data.data, function (key, value) {
                                if (key === 'history')
                                    IaEvents.fillFormDataFieldsHistory(ia_report_crp_form, value, 'crp');
                                else if (key !== 'crp_id')
                                    fillFormDataFields(ia_report_crp_form, key, value);
                            });

                            if (is_replicate) {
                                fillFormDataFields(ia_report_crp_form, 'ia_report_id', IaEvents.getIaReportId());
                                fillFormDataFields(ia_report_crp_form, 'reporting_year', IaEvents.getIaReportingYear());
                                fillFormDataFields(ia_report_crp_form, 'ia_report_crp_id', null);
                            }

                            App.unblockUI(parent);
                            $.uniform.update();
                            modal.modal();
                        });
                    });
                } else {
                    IaEvents.prepare_update_block(ia_report_crp_form, {
                        reporting_year: IaEvents.getIaReportingYear(),
                        added_by_name: null,
                        added_date: null,
                        updated_by_name: null,
                        updated_date: null,
                        update_text: null,
                        can_update_ia_report: true,
                        history: {},
                    });
                    ia_report_crp_form.find('.fields_history').remove();
                    App.unblockUI(parent);
                    $.uniform.update();
                    modal.modal();
                }
            })
            .on('click', '.btn_delete_ia_report_crp', function () {
                let ia_report_crp_id = $(this).data('id');
                IaEvents.deleteEntity($(this), false, DeleteIaReportCrpLink + '/ia_report_crp_id/' + ia_report_crp_id, [$('#ia_report_crp_table')]);
            })
            .on('click', '.btn_edit_ia_management_document', function () {
                let ia_report_id = $(this).data('ia_report_id');
                let ia_management_document_id = $(this).data('id');
                //If add new item and the reports is not saved or if the reporting year is changed
                if ((!(parseInt(ia_management_document_id) > 0) && !(parseInt(IaEvents.getIaReportId()) > 0)) || IaEvents.IaReportingYearChanged()) {
                    askToSubmitIAForm();
                    return;
                }

                let parent = $(this).parents('.portlet:first');
                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let modal = $('#ia_management_document_modal');
                let ia_management_document_form = modal.find('#ia_management_document_form');
                DisableFormFields(ia_management_document_form, false);

                modal.find('input:not([type=radio], [type=checkbox]), select, textarea').val('').change();
                modal.find('input.date-picker').DatePicker('setDate', null);
                modal.find('input[type=radio], input[type=checkbox]').prop('checked', false).change();
                modal.find('input[name=main_ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=reporting_year]').val(IaEvents.getIaReportingYear());
                modal.find('label.fake-label').html('fake label').css('visibility', 'hidden');
                modal.find('.update_rows').html('');

                let is_replicate = $(this).data('is_replicate');
                ia_management_document_form.data('item_id', is_replicate || !ia_management_document_id ? null : ia_management_document_id);

                if (parseInt(ia_management_document_id) > 0) {
                    $.getJSON(GetIaManagementDocumentDataLink + '/ia_management_document_id/' + ia_management_document_id + '/ia_report_id/' + ia_report_id, function (data) {
                        if (!data.result) {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 5, // auto close after defined seconds
                            });
                            App.unblockUI(parent);
                            return;
                        }

                        $.each(data.data.updates, function (index, update) {
                            IaEvents.prepare_update_block(ia_management_document_form, update);
                        });

                        ia_management_document_form.find('b.item_reporting_year').html(data.data.reporting_year);

                        if (data.data.can_edit_ia_report !== true && data.data.can_update_ia_report !== true && !is_replicate)
                            DisableFormFields(ia_management_document_form, true);

                        ia_management_document_form.find('.fa.add-comment').removeClass('ignore_commentable');
                        if (!iaReportReviewPage && data.data.reporting_year !== IaEvents.getIaReportingYear())
                            ia_management_document_form.find('.fa.add-comment').addClass('ignore_commentable');

                        if (data.data.other_document_type != null) {
                            modal.find('label.title_label').html('<span class="numbering"></span> Name of resource <span class="required"> * </span>');
                            modal.find('.other_type_section').show().find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                            modal.find('.other_type_section').find('.numbering').removeClass('ignore_numbering');
                            policyModalNumbering(modal, '1.4.b.');
                        } else {
                            modal.find('label.title_label').html('<span class="numbering"></span> Name of official Policy <span class="required"> * </span>');
                            modal.find('.other_type_section').hide().find('input, select, textarea').prop('disabled', true);
                            modal.find('.other_type_section').find('.numbering').addClass('ignore_numbering');
                            policyModalNumbering(modal, '1.4.a.');
                        }

                        ia_management_document_form.find('select[name="crp_id[]"]').partnerSelect2Val(data.data.crp_id, function () {
                            $.each(data.data, function (key, value) {
                                if (key === 'history')
                                    IaEvents.fillFormDataFieldsHistory(ia_management_document_form, value, 'management_document');
                                else if (key !== 'crp_id')
                                    fillFormDataFields(ia_management_document_form, key, value);
                            });

                            if (is_replicate) {
                                fillFormDataFields(ia_management_document_form, 'ia_report_id', IaEvents.getIaReportId());
                                fillFormDataFields(ia_management_document_form, 'reporting_year', IaEvents.getIaReportingYear());
                                fillFormDataFields(ia_management_document_form, 'ia_management_document_id', null);
                            }

                            App.unblockUI(parent);
                            $.uniform.update();

                            modal.modal();
                        });
                    });
                } else {
                    if ($(this).data('is_other')) {
                        modal.find('label.title_label').html('<span class="numbering"></span> Name of resource <span class="required"> * </span>');
                        modal.find('.other_type_section').show().find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                        modal.find('.other_type_section').find('.numbering').removeClass('ignore_numbering');
                        policyModalNumbering(modal, '1.4.b.');
                    } else {
                        modal.find('label.title_label').html('<span class="numbering"></span> Name of official Policy <span class="required"> * </span>');
                        modal.find('.other_type_section').hide().find('input, select, textarea').prop('disabled', true);
                        modal.find('.other_type_section').find('.numbering').addClass('ignore_numbering');
                        policyModalNumbering(modal, '1.4.a.');
                    }

                    IaEvents.prepare_update_block(ia_management_document_form, {
                        reporting_year: IaEvents.getIaReportingYear(),
                        added_by_name: null,
                        added_date: null,
                        updated_by_name: null,
                        updated_date: null,
                        update_text: null,
                        can_update_ia_report: true,
                        history: {},
                    });
                    ia_management_document_form.find('.fields_history').remove();
                    App.unblockUI(parent);
                    $.uniform.update();
                    modal.modal();
                }
            })
            .on('change', '#ia_management_document_modal select[name=manage_status]', function () {
                let modal = $(this).parents('.modal:first');

                if ($(this).val() === 'Under development or consideration for adoption') {
                    modal.find('input[name=approval_date]').removeClass('required').blur()
                        .parents('.form-group:first').find('label.control-label span.required').hide();
                    modal.find('input[name=effective_date]').removeClass('required').blur()
                        .parents('.form-group:first').find('label.control-label span.required').hide();
                } else {
                    modal.find('input[name=approval_date]').addClass('required')
                        .parents('.form-group:first').find('label.control-label span.required').show();
                    modal.find('input[name=effective_date]').addClass('required')
                        .parents('.form-group:first').find('label.control-label span.required').show();
                }
            })
            .on('click', '.btn_delete_ia_management_document', function () {
                let ia_management_document_id = $(this).data('id');
                IaEvents.deleteEntity($(this), false, DeleteIaManagementDocumentLink + '/ia_management_document_id/' + ia_management_document_id, [$('#ia_management_document_table'), $('#ia_management_other_document_table')]);
            })
            .on('click', '.btn_edit_ia_portfolio_document', function () {
                let ia_report_id = $(this).data('ia_report_id');
                let ia_portfolio_document_id = $(this).data('id');
                //If add new item and the reports is not saved or if the reporting year is changed
                if ((!(parseInt(ia_portfolio_document_id) > 0) && !(parseInt(IaEvents.getIaReportId()) > 0)) || IaEvents.IaReportingYearChanged()) {
                    askToSubmitIAForm();
                    return;
                }

                let parent = $(this).parents('.portlet:first, .modal:first');

                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let modal = $('#ia_portfolio_document_modal');
                let ia_portfolio_document_form = modal.find('#ia_portfolio_document_form');
                DisableFormFields(ia_portfolio_document_form, false);

                modal.find('input:not([type=radio], [type=checkbox]), select, textarea').val('').change();
                modal.find('input.date-picker').DatePicker('setDate', null);
                modal.find('input[type=radio], input[type=checkbox]').prop('checked', false).change();
                modal.find('input[name=main_ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=reporting_year]').val(IaEvents.getIaReportingYear());
                ia_portfolio_document_form.find('select[name=portfolio_title]').data('selected_value', null);
                ia_portfolio_document_form.find('select[name=short_title]').data('selected_value', null);
                modal.find('.portfolio_country_rows .delete_country').click();
                modal.find('.update_rows').html('');

                let target_select = $(this).parents('.input-group:first').find('select');
                modal.find('.form_submit').data('target_select', target_select);

                let is_replicate = $(this).data('is_replicate');
                ia_portfolio_document_form.data('item_id', is_replicate || !ia_portfolio_document_id ? null : ia_portfolio_document_id);

                let ia_portfolio_type = $(this).data('ia_portfolio_type');
                if (parseInt(ia_portfolio_document_id) > 0) {
                    $.getJSON(GetIaPortfolioDocumentDataLink + '/ia_portfolio_document_id/' + ia_portfolio_document_id + '/ia_report_id/' + ia_report_id, function (data) {
                        if (!data.result) {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 5, // auto close after defined seconds
                            });
                            App.unblockUI(parent);
                            return;
                        }

                        $.each(data.data.updates, function (index, update) {
                            IaEvents.prepare_update_block(ia_portfolio_document_form, update);
                        });

                        ia_portfolio_document_form.find('b.item_reporting_year').html(data.data.reporting_year);

                        ia_portfolio_document_form.find('.fa.add-comment').removeClass('ignore_commentable');
                        if (!iaReportReviewPage && data.data.reporting_year !== IaEvents.getIaReportingYear())
                            ia_portfolio_document_form.find('.fa.add-comment').addClass('ignore_commentable');

                        let wanted_section = modal.find('.portfolio_documents_types[data-type="' + data.data.ia_portfolio_type + '"]');
                        wanted_section.find('select[name=portfolio_title]').data('selected_value', data.data.portfolio_title);
                        wanted_section.find('select[name=short_title]').data('selected_value', data.data.short_title);
                        ia_portfolio_document_form.find('input[type=radio][name=ia_portfolio_type][value="' + cleanStringValue(data.data.ia_portfolio_type) + '"]').prop('checked', true).change();
                        ia_portfolio_document_form.find('input[type=hidden][name=ia_portfolio_document_id]').val(data.data.ia_portfolio_document_id);

                        let partners_object = {
                            crp_id: {},
                            owner_applicant: {},
                        };
                        try {
                            data.data.crp_id = JSON.parse(data.data.crp_id);
                            $.each(data.data.crp_id, function (key, partner_id) {
                                partners_object.crp_id[partner_id] = wanted_section.find('select[name="crp_id[]"]');
                            });
                        } catch (e) {
                        }
                        try {
                            data.data.owner_applicant = JSON.parse(data.data.owner_applicant);
                            $.each(data.data.owner_applicant, function (key, partner_id) {
                                partners_object.owner_applicant[partner_id] = wanted_section.find('select[name="owner_applicant[]"]');
                            });
                        } catch (e) {
                        }

                        $.each(data.data.countries, function (index, country) {
                            let last_row = IaEvents.createNewRow('portfolio_country_row', wanted_section, false);
                            last_row.find('select[name="country_id[]"]').val(country.country_id).change();
                            last_row.find('select[name="status[]"]').val(country.status).change();
                        });

                        $('body').partnerSelect2Val(partners_object, function () {
                            $.each(data.data, function (key, value) {
                                if (key === 'history') {
                                    IaEvents.fillFormDataFieldsHistory(wanted_section, value, 'portfolio_document');
                                } else if (key === 'countries' || key === 'country_id' || key === 'status') {
                                    //Do nothing
                                } else if ((key !== 'portfolio_title' || data.data.ia_portfolio_type === 'Trademark') && key !== 'short_title' && key !== 'crp_id' && key !== 'owner_applicant' && key !== 'ia_portfolio_type' && value !== '' && value != null) {
                                    let is_selectize = key === 'trademark_type';
                                    fillFormDataFields(wanted_section, key, value, is_selectize);
                                }
                            });

                            if (is_replicate) {
                                fillFormDataFields(modal, 'ia_report_id', IaEvents.getIaReportId());
                                fillFormDataFields(modal, 'reporting_year', IaEvents.getIaReportingYear());
                                fillFormDataFields(modal, 'ia_portfolio_document_id', null);
                            }
                            wanted_section.find('select[name=filing_type_2]').change();

                            if (data.data.can_edit_ia_report !== true && data.data.can_update_ia_report !== true && !is_replicate)
                                DisableFormFields(ia_portfolio_document_form, true);

                            App.unblockUI(parent);
                            $.uniform.update();
                            modal.modal();
                        }, true);
                    });
                } else {
                    if (modal.find('input[name=ia_portfolio_type][value="' + cleanStringValue(ia_portfolio_type) + '"]').length > 0)
                        modal.find('input[name=ia_portfolio_type][value="' + cleanStringValue(ia_portfolio_type) + '"]').prop('checked', true).change();
                    else
                        modal.find('input[name=ia_portfolio_type]:first').prop('checked', true).change();

                    IaEvents.prepare_update_block(ia_portfolio_document_form, {
                        reporting_year: IaEvents.getIaReportingYear(),
                        added_by_name: null,
                        added_date: null,
                        updated_by_name: null,
                        updated_date: null,
                        update_text: null,
                        can_update_ia_report: true,
                        history: {},
                    });
                    ia_portfolio_document_form.find('.fields_history').remove();
                    App.unblockUI(parent);
                    $.uniform.update();
                    modal.modal();
                }
            })
            .on('click', '.btn_delete_ia_portfolio_document', function () {
                let ia_portfolio_document_id = $(this).data('id');
                let confirm_message = $(this).data('confirm');
                let deleteLink = DeleteIaPortfolioDocumentLink + '/ia_portfolio_document_id/' + ia_portfolio_document_id + '/is_confirm/' + (confirm_message ? 1 : 0);

                IaEvents.deleteEntity($(this), confirm_message, deleteLink, [$('#ia_portfolio_document_table_Patent'), $('#ia_portfolio_document_table_PVP'), $('#ia_portfolio_document_table_Trademark'), $('#ia_agreement_table'), $('#ia_agreement_public_disclosure_table')]);
            })
            .on('change', '#ia_portfolio_document_modal input[name=ia_portfolio_type]', function () {
                let modal = $(this).parents('.modal:first');
                let unwanted_sections = modal.find('.portfolio_documents_types');
                unwanted_sections.hide().find('input, select, textarea').prop('disabled', true).change();
                unwanted_sections.find('select.selectize').each(function () {
                    if ($(this)[0].selectize) {
                        $(this)[0].selectize.clear();
                        $(this)[0].selectize.disable();
                    }
                });

                let type = modal.find('input[name=ia_portfolio_type]:checked').val();
                if (type !== '' && type != null) {
                    let wanted_section = modal.find('.portfolio_documents_types[data-type="' + type + '"]');
                    wanted_section.fadeIn('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false).change();
                    wanted_section.find('select.selectize:not(.keep_disabled)').each(function () {
                        if ($(this)[0].selectize)
                            $(this)[0].selectize.enable();
                    });

                    App.blockUI({
                        target: wanted_section.find('select[name=portfolio_title]'),
                        boxed: true
                    });
                    App.blockUI({
                        target: wanted_section.find('select[name=short_title]'),
                        boxed: true
                    });

                    let portfolio_title = wanted_section.find('select[name=portfolio_title]').data('selected_value');
                    if (wanted_section.find('select[name=portfolio_title]').length > 0 && wanted_section.find('select[name=portfolio_title]')[0].selectize)
                        wanted_section.find('select[name=portfolio_title]')[0].selectize.destroy();
                    let short_title = wanted_section.find('select[name=short_title]').data('selected_value');
                    if (wanted_section.find('select[name=short_title]').length > 0 && wanted_section.find('select[name=short_title]')[0].selectize)
                        wanted_section.find('select[name=short_title]')[0].selectize.destroy();

                    $.getJSON(GetAllIaPortfolioDocumentsDataLink + '/ip_type/' + type + '/for_drop_down/2', function (data) {
                        let title_options = '<option value=""></option>';
                        $.each(data.portfolio_title, function (index, portfolio_title) {
                            title_options += '<option value="' + portfolio_title + '" >' + portfolio_title + '</option>';
                        });
                        if (wanted_section.find('select[name=portfolio_title]').length > 0)
                            wanted_section.find('select[name=portfolio_title]')
                                .html(title_options)
                                .val(portfolio_title)
                                .selectize({create: true});
                        App.unblockUI(wanted_section.find('select[name=portfolio_title]'));

                        let short_title_options = '<option value=""></option>';
                        $.each(data.short_title, function (index, short_title) {
                            short_title_options += '<option value="' + short_title + '">' + short_title + '</option>';
                        });
                        if (wanted_section.find('select[name=short_title]').length > 0)
                            wanted_section.find('select[name=short_title]')
                                .html(short_title_options)
                                .val(short_title)
                                .selectize({create: true});
                        App.unblockUI(wanted_section.find('select[name=short_title]'));
                    });
                }
                $.uniform.update();
            })
            .on('click', '#ia_portfolio_document_modal .add_country', function () {
                let parent = $(this).parents('.portfolio_documents_types:first');
                let last_row = IaEvents.createNewRow('portfolio_country_row', parent, false);
                last_row.find('select[name="country_id[]"]').change();
            })
            .on('click', '#ia_portfolio_document_modal .portfolio_country_rows .delete_country', function () {
                $(this).parents('.portfolio_country_row:first').find('select[name="country_id[]"]').val('').change();
                $(this).parents('.portfolio_country_row:first').fadeOut('slow', function () {
                    $(this).remove();
                });
            })
            .on('change', '.portfolio_country_rows select[name="country_id[]"]', function () {
                let parent = $(this).parents('.portfolio_country_rows:first');
                parent.find('select[name="country_id[]"] option:disabled').prop('disabled', false);
                parent.find('select[name="country_id[]"]').each(function () {
                    if ($(this).val() !== '' && $(this).val() != null)
                        parent.find('select[name="country_id[]"]').not(this).find('option[value=' + $(this).val() + ']').prop('disabled', true);
                });
                setTimeout(function (){
                    parent.find('select[name="country_id[]"]').select2({allowClear: true});
                }, 500);
            })
            .on('change', '.modal input[type=radio].crp_related_radio', function () {
                let modal = $(this).parents('.modal:first');
                let target = $(this).data('target');
                if (parseInt($(this).parents('.form-group:first').find('input[type=radio].crp_related_radio:checked').val()) === 1)
                    modal.find('.form-group.' + target).slideDown('slow').find('select.select2partner:not(.keep_disabled)').prop('disabled', false);
                else
                    modal.find('.form-group.' + target).slideUp('slow').find('select.select2partner').prop('disabled', true);
            })
            .on('click', '.btn_edit_ia_agreement', function () {
                let ia_report_id = $(this).data('ia_report_id');
                let ia_agreement_id = $(this).data('id');
                //If add new item and the reports is not saved or if the reporting year is changed
                if ((!(parseInt(ia_agreement_id) > 0) && !(parseInt(IaEvents.getIaReportId()) > 0)) || IaEvents.IaReportingYearChanged()) {
                    askToSubmitIAForm();
                    return;
                }

                let parent = $(this).parents('.portlet:first');
                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let modal = $('#ia_agreement_modal');
                let ia_agreement_form = modal.find('#ia_agreement_form');
                DisableFormFields(ia_agreement_form, false);

                modal.find('input:not([type=radio], [type=checkbox]), select, textarea').val('').change();
                modal.find('input.date-picker').DatePicker('setDate', null);
                modal.find('input[type=radio], input[type=checkbox]').prop('checked', false).change();
                modal.find('input[name=main_ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=reporting_year]').val(IaEvents.getIaReportingYear());
                modal.find('.update_rows').html('');
                modal.find('.modal-footer .btn_add_ia_agreement_review').remove();

                let is_replicate = $(this).data('is_replicate');
                ia_agreement_form.data('item_id', is_replicate || !ia_agreement_id ? null : ia_agreement_id);

                $.getJSON(GetAllIaAgreementsDataLink + '/ia_agreement_id/' + (is_replicate ? '' : ia_agreement_id) + '/for_drop_down/1', function (data) {
                    let agreement_related_options = '<option value=""></option>';
                    $.each(data.data, function (index, agreement) {
                        agreement_related_options += '<option value="' + agreement.ia_agreement_id + '" ' + (parseInt(agreement.is_deleted) === 1 ? 'disabled' : '') + ' data-agreement_type="' + agreement.ia_agreement_type + '">(' + agreement.unique_identifier + (agreement.country != null && agreement.country !== '' ? ' - ' + agreement.country : '') + ') ' + agreement.agreement_title + '</option>';
                    });
                    modal.find('select[name="agreement_related_post"]').html(agreement_related_options).select2({allowClear: true});
                    modal.find('select[name="agreement_related_post"]').data('available_options', modal.find('select[name="agreement_related_post"] option'));

                    if (parseInt(ia_agreement_id) > 0) {
                        $.getJSON(GetIaAgreementDataLink + '/ia_agreement_id/' + ia_agreement_id + '/ia_report_id/' + ia_report_id, function (data) {
                            if (!data.result) {
                                App.alert({
                                    type: 'danger',  // alert's type
                                    message: data.message,  // alert's message
                                    closeInSeconds: 5, // auto close after defined seconds
                                });
                                App.unblockUI(parent);
                                return;
                            }

                            let form_reporting_year = IaEvents.getIaReportingYear();
                            if (!is_replicate)
                                form_reporting_year = parseInt(data.data.reporting_year);
                            if (parseInt(form_reporting_year) > 2019) {
                                modal.find('.pre_2020').hide()
                                    .find('input, textarea')
                                    .prop('readonly', true);
                                modal.find('.post_2020').show()
                                    .find('input, textarea')
                                    .prop('readonly', false);
                            } else {
                                modal.find('.pre_2020').show()
                                    .find('input, textarea')
                                    .prop('readonly', false);
                                modal.find('.post_2020').hide()
                                    .find('input, textarea')
                                    .prop('readonly', true);
                            }

                            let editable_updates = null;
                            $.each(data.data.updates, function (index, update) {
                                editable_updates = IaEvents.prepare_update_block(ia_agreement_form, update);
                            });

                            ia_agreement_form.find('b.item_reporting_year').html(data.data.reporting_year);

                            if (data.data.can_edit_ia_report_agreement !== true && !is_replicate)
                                DisableFormFields(ia_agreement_form, true);
                            if (data.data.can_update_ia_report_agreement) {
                                ia_agreement_form.parents('.modal:first').find('.form_submit').prop('disabled', false).show();
                                if (editable_updates != null && editable_updates.length > 0)
                                    editable_updates.find('textarea').prop('disabled', false);
                            }

                            if (data.data.ia_report_is_draft === false && !is_replicate) {
                                if (modal.find('.modal-footer .btn_add_ia_agreement_review').length === 0)
                                    modal.find('.modal-footer').prepend('<button type="button" class="btn green btn_add_ia_agreement_review">Review</button>');
                                if (modal.find('.modal-header .actions-top').length === 0) {
                                    modal.find('.modal-header .close').remove();
                                    modal.find('.modal-header').prepend('<div class="pull-right actions-top"><button type="button" class="btn green btn_add_ia_agreement_review">Review</button><button type="button" data-dismiss="modal" class="btn btn-default">Cancel</button></div>');
                                }
                                modal.find('.modal-footer, .modal-header').find('.btn_add_ia_agreement_review').data('ia_agreement_id', ia_agreement_id);
                            } else {
                                modal.find('.modal-header .actions-top').remove();
                                if (modal.find('.modal-header .close').length === 0)
                                    modal.find('.modal-header').prepend('<button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>');
                            }

                            ia_agreement_form.find('.fa.add-comment').removeClass('ignore_commentable');
                            if (!iaReportReviewPage && data.data.reporting_year !== IaEvents.getIaReportingYear()) {
                                ia_agreement_form.find('.fa.add-comment').addClass('ignore_commentable');
                                modal.find('.modal-footer .btn_add_ia_agreement_review').remove();
                                modal.find('.modal-header .actions-top').remove();
                            }

                            modal.find('select[name=agreement_related_portfolio]').data('selected_options', data.data.agreement_related_portfolio);
                            modal.find('select[name="agreement_related_post"]').data('selected_value', data.data.agreement_related_post);

                            let partners_object = {
                                parties: {},
                                applicants: {},
                            };
                            try {
                                data.data.parties_name = JSON.parse(data.data.parties_name);
                                $.each(data.data.parties_name, function (key, partner_id) {
                                    partners_object.parties[partner_id] = ia_agreement_form.find('select[name="parties_name[]"]');
                                });
                            } catch (e) {
                            }
                            try {
                                data.data.applicant_name = JSON.parse(data.data.applicant_name);
                                $.each(data.data.applicant_name, function (key, partner_id) {
                                    partners_object.applicants[partner_id] = ia_agreement_form.find('select[name="applicant_name[]"]');
                                });
                            } catch (e) {
                            }

                            $('body').partnerSelect2Val(partners_object, function () {
                                $.each(data.data, function (key, value) {
                                    if (key === 'history')
                                        IaEvents.fillFormDataFieldsHistory(ia_agreement_form, value, 'agreement');
                                    else if (key === 'non_confidential') {
                                        try {
                                            value = JSON.parse(value);
                                            if (typeof value === 'object' && value !== null) {
                                                $.each(value, function (key_2, value_2) {
                                                    fillFormDataFields(ia_agreement_form, 'non_confidential_' + key_2, value_2, false);
                                                });
                                            }
                                        } catch (e) {
                                        }
                                    } else if (key !== 'agreement_related_portfolio' && key !== 'parties_name' && key !== 'applicant_name' && key !== 'agreement_related_post') {
                                        fillFormDataFields(ia_agreement_form, key, value, false);
                                    }
                                });

                                if (is_replicate) {
                                    fillFormDataFields(ia_agreement_form, 'ia_report_id', IaEvents.getIaReportId());
                                    fillFormDataFields(ia_agreement_form, 'reporting_year', IaEvents.getIaReportingYear());
                                    fillFormDataFields(ia_agreement_form, 'ia_agreement_id', null);
                                }

                                App.unblockUI(parent);
                                $.uniform.update();
                                modal.modal();
                            }, true);
                        });
                    } else {
                        modal.find('input[name=ia_agreement_type]').prop('checked', false).change();

                        if (parseInt(IaEvents.getIaReportingYear()) > 2019) {
                            modal.find('.pre_2020').hide()
                                .find('input, textarea')
                                .prop('readonly', true);
                            modal.find('.post_2020').show()
                                .find('input, textarea')
                                .prop('readonly', false);
                        } else {
                            modal.find('.pre_2020').show()
                                .find('input, textarea')
                                .prop('readonly', false);
                            modal.find('.post_2020').hide()
                                .find('input, textarea')
                                .prop('readonly', true);
                        }

                        IaEvents.prepare_update_block(ia_agreement_form, {
                            reporting_year: IaEvents.getIaReportingYear(),
                            added_by_name: null,
                            added_date: null,
                            updated_by_name: null,
                            updated_date: null,
                            update_text: null,
                            can_update_ia_report: true,
                            history: {},
                        });
                        ia_agreement_form.find('.fields_history').remove();
                        App.unblockUI(parent);
                        $.uniform.update();
                        modal.modal();
                    }
                });
            })
            .on('click', '.btn_delete_ia_agreement', function () {
                let ia_agreement_id = $(this).data('id');
                let confirm_message = $(this).data('confirm');
                let deleteLink = DeleteIaAgreementLink + '/ia_agreement_id/' + ia_agreement_id + '/is_confirm/' + (confirm_message ? 1 : 0);

                IaEvents.deleteEntity($(this), confirm_message, deleteLink, [$('#ia_agreement_table'), $('#ia_agreement_public_disclosure_table')]);
            })
            .on('change', '#ia_agreement_modal input[name=ia_agreement_type]', function () {
                let modal = $(this).parents('.modal:first');

                let selected_type = modal.find('input[name=ia_agreement_type]:checked').val();

                if (selected_type != null && selected_type !== '') {
                    let selected_agreement = modal.find('select[name="agreement_related_post"]').data('selected_value');
                    let related_agreements_options = modal.find('select[name="agreement_related_post"]').data('available_options')
                    if (related_agreements_options != null && related_agreements_options.length > 0) {
                        related_agreements_options = related_agreements_options.filter('[data-agreement_type="' + selected_type + '"]');
                        modal.find('select[name="agreement_related_post"]').html(related_agreements_options)
                            .val(selected_agreement).select2({allowClear: true}).change();
                    }
                }

                modal.find('.ia_agreement_type_sections').each(function () {
                    let related_types = $(this).data('type').split(',');

                    if (related_types.includes(selected_type)) {
                        $(this).slideDown('slow').find('input, select, textarea, button').filter(':not(.keep_disabled)').prop('disabled', false);
                    } else {
                        if ($(this).is('span'))
                            $(this).hide();
                        else
                            $(this).slideUp().find('input, select, textarea, button').prop('disabled', true);
                    }
                });

                $.uniform.update();
            })
            .on('change', '#ia_agreement_modal input[name=ip_type]', function () {
                let modal = $(this).parents('.modal:first');
                let portfolio_select = modal.find('select[name=agreement_related_portfolio]');
                App.blockUI({
                    target: portfolio_select,
                    boxed: true
                });

                let ip_type = modal.find('input[name=ip_type]:checked').val();
                modal.find('.btn_edit_ia_portfolio_document').data('ia_portfolio_type', ip_type);

                if (ip_type === '' || ip_type == null)
                    ip_type = -15;

                let original_selected = portfolio_select.val();
                if (original_selected == null || original_selected === '')
                    original_selected = portfolio_select.data('selected_options');

                $.getJSON(GetAllIaPortfolioDocumentsDataLink + '/ip_type/' + ip_type + '/for_drop_down/1', function (data) {
                    let options = '';
                    $.each(data.data, function (index, row) {
                        if (parseInt(modal.find('input[name=reporting_year]').val()) === parseInt(row.reporting_year))
                            options += '<option value="' + row.ia_portfolio_document_id + '" ' + (parseInt(row.is_deleted) === 1 ? 'disabled' : '') + '>(' + row.reporting_year + '-' + row.ia_portfolio_type + (row.country != null && row.country !== '' ? '-' + row.country : '') + ') ' + (row.short_title != null && row.short_title !== '' ? row.short_title : row.portfolio_title) + '</option>';
                    });
                    portfolio_select.html(options).val(original_selected).change();
                    App.unblockUI(portfolio_select);
                });
            })
            .on('change', '#ia_agreement_modal select[name=agreement_related_portfolio]', function () {
                let modal = $(this).parents('.modal:first');
                modal.find('.portfolio_related:not(.keep_disabled)').prop('disabled', false)
                    .parent().find('button:not(.keep_disabled)').prop('disabled', false);

                if (modal.find('input[name=ia_agreement_type]:checked').val() !== 'IP Application')
                    return;

                let agreement_related_portfolio = $(this).val();
                let ip_type = modal.find('input[name=ip_type]:checked').val();
                let related_fields = {
                    Patent: {
                        portfolio_title: 'agreement_title',
                        filing_type_1: 'filing_type_1',
                        filing_type_2: 'filing_type_2',
                        country_id: 'country_id',
                        filing_date: 'start_date',
                        owner_applicant: 'applicant_name'
                    },
                    PVP: {
                        portfolio_title: 'agreement_title',
                        country_id: 'country_id',
                        filing_date: 'start_date',
                        owner_applicant: 'applicant_name'
                    }
                };
                let related_elements = modal.find('.portfolio_related').parent();
                App.blockUI({
                    target: related_elements,
                    boxed: true
                });

                $.getJSON(GetIaPortfolioDocumentDataLink + '/ia_portfolio_document_id/' + agreement_related_portfolio, function (data) {
                    if (!data.result) {
                        App.unblockUI(related_elements);
                        return;
                    }

                    try {
                        data.data.owner_applicant = JSON.parse(data.data.owner_applicant);
                    } catch (e) {
                        data.data.owner_applicant = null;
                    }

                    modal.find('select[name="applicant_name[]"]').partnerSelect2Val(data.data.owner_applicant, function () {
                        $.each(related_fields[ip_type], function (portfolio_name, agreement_name) {
                            if (data.data.hasOwnProperty(portfolio_name)) {
                                if (portfolio_name !== 'owner_applicant')
                                    fillFormDataFields(modal, agreement_name, data.data[portfolio_name], false);
                            }
                            modal.find('*[name="' + agreement_name + '"], *[name="' + agreement_name + '[]"]').prop('disabled', true)
                                .parent().find('button').prop('disabled', true);
                        });
                        App.unblockUI(related_elements);
                    });
                });
            })
            .on('change', '#ia_agreement_modal select[name=agreement_related_post]', function () {
                let modal = $(this).parents('.modal:first');
                let related_elements = modal.find('.another_agreement_related');
                related_elements.find('input, select, textarea, button')
                    .filter(':not(.keep_disabled)').prop('disabled', false);

                let agreement_related_post = $(this).val();
                App.blockUI({
                    target: related_elements.find('.form-group'),
                    boxed: true
                });

                $.getJSON(GetIaAgreementDataLink + '/ia_agreement_id/' + agreement_related_post + '/is_related/1', function (data) {
                    if (!data.result) {
                        App.unblockUI(related_elements.find('.form-group'));
                        $.uniform.update();
                        return;
                    }

                    $.each(data.data, function (key, value) {
                        if (key === 'non_confidential') {
                            try {
                                value = JSON.parse(value);
                                if (typeof value === 'object' && value !== null) {
                                    $.each(value, function (key_2, value_2) {
                                        fillFormDataFields(related_elements, 'non_confidential_' + key_2, value_2, false);
                                    });
                                }
                            } catch (e) {
                            }
                        } else {
                            fillFormDataFields(related_elements, key, value, false);
                        }
                    });
                    related_elements.find('input, select, textarea, button').prop('disabled', true);
                    App.unblockUI(related_elements.find('.form-group'));
                    $.uniform.update();
                });
            })
            .on('click', '.btn_edit_ia_agreement_public_disclosure', function () {
                let ia_report_id = $(this).data('ia_report_id');
                let ia_agreement_public_disclosure_id = $(this).data('id');
                //If add new item and the reports is not saved or if the reporting year is changed
                if ((!(parseInt(ia_agreement_public_disclosure_id) > 0) && !(parseInt(IaEvents.getIaReportId()) > 0)) || IaEvents.IaReportingYearChanged()) {
                    askToSubmitIAForm();
                    return;
                }

                let parent = $(this).parents('.portlet:first');
                App.blockUI({
                    target: parent,
                    boxed: true
                });

                let modal = $('#ia_agreement_public_disclosure_modal');
                let ia_agreement_public_disclosure_form = modal.find('#ia_agreement_public_disclosure_form');
                DisableFormFields(ia_agreement_public_disclosure_form, false);

                modal.find('input:not([type=radio], [type=checkbox]), select, textarea').val('').change();
                modal.find('input[type=radio], input[type=checkbox]').prop('checked', false).change();
                modal.find('input[name=main_ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=ia_report_id]').val(IaEvents.getIaReportId());
                modal.find('input[name=reporting_year]').val(IaEvents.getIaReportingYear());
                modal.find('.update_rows').html('');
                modal.find('.other_public_disclosure_link_rows').html('');
                modal.find('.other_public_disclosure_link_rows_label').hide();

                let is_replicate = $(this).data('is_replicate');
                ia_agreement_public_disclosure_form.data('item_id', is_replicate || !ia_agreement_public_disclosure_id ? null : ia_agreement_public_disclosure_id);

                $.getJSON(GetAllIaAgreementsDataLink + '/for_drop_down/1/public_disclosure/1', function (agreements) {
                    let agreements_options = '<option value=""></option>';
                    $.each(agreements.data, function (index, agreement) {
                        agreements_options += '<option value="' + agreement.ia_agreement_id + '" ' + (parseInt(agreement.is_deleted) === 1 ? 'disabled' : '') + ' data-agreement_type="' + agreement.ia_agreement_type + '">(' + agreement.unique_identifier + (agreement.country != null && agreement.country !== '' ? ' - ' + agreement.country : '') + ') ' + agreement.agreement_title + '</option>';
                    });
                    modal.find('select[name=ia_agreement_id]').html(agreements_options).select2({allowClear: true});

                    if (parseInt(ia_agreement_public_disclosure_id) > 0) {
                        $.getJSON(GetIaAgreementPublicDisclosureDataLink + '/ia_agreement_public_disclosure_id/' + ia_agreement_public_disclosure_id + '/ia_report_id/' + ia_report_id, function (data) {
                            if (!data.result) {
                                App.alert({
                                    type: 'danger',  // alert's type
                                    message: data.message,  // alert's message
                                    closeInSeconds: 5, // auto close after defined seconds
                                });
                                App.unblockUI(parent);
                                return;
                            }

                            let editable_updates = null;
                            $.each(data.data.updates, function (index, update) {
                                editable_updates = IaEvents.prepare_update_block(ia_agreement_public_disclosure_form, update);
                            });

                            ia_agreement_public_disclosure_form.find('b.item_reporting_year').html(data.data.reporting_year);

                            try {
                                let public_disclosure_data = JSON.parse(data.data.other_links);
                                $.each(public_disclosure_data, function (key, value) {
                                    let last_row = IaEvents.createNewRow('other_public_disclosure_link_row', ia_agreement_public_disclosure_form, false);
                                    last_row.find('input[name="other_links[]"]').val(value).change();
                                });
                            } catch (e) {
                            }
                            if (ia_agreement_public_disclosure_form.find('.other_public_disclosure_link_row').length > 0)
                                ia_agreement_public_disclosure_form.find('.other_public_disclosure_link_rows_label').slideDown('slow');
                            else
                                ia_agreement_public_disclosure_form.find('.other_public_disclosure_link_rows_label').slideUp('slow');


                            if (data.data.can_edit_ia_report_public_disclosure !== true && !is_replicate)
                                DisableFormFields(ia_agreement_public_disclosure_form, true);
                            if (data.data.can_update_ia_report_public_disclosure) {
                                ia_agreement_public_disclosure_form.parents('.modal:first').find('.form_submit').prop('disabled', false).show();
                                if (editable_updates != null && editable_updates.length > 0)
                                    editable_updates.find('textarea').prop('disabled', false);
                            }

                            if (data.data.ia_report_is_draft === false && !is_replicate) {
                                if (modal.find('.modal-footer .btn_add_ia_public_disclosure_review').length === 0)
                                    modal.find('.modal-footer').prepend('<button type="button" class="btn green btn_add_ia_public_disclosure_review">Review</button>');
                                modal.find('.modal-footer .btn_add_ia_public_disclosure_review').data('ia_agreement_public_disclosure_id', ia_agreement_public_disclosure_id);
                            }

                            ia_agreement_public_disclosure_form.find('.fa.add-comment').removeClass('ignore_commentable');
                            if (!iaReportReviewPage && data.data.reporting_year !== IaEvents.getIaReportingYear()) {
                                ia_agreement_public_disclosure_form.find('.fa.add-comment').addClass('ignore_commentable');
                                modal.find('.modal-footer .btn_add_ia_public_disclosure_review').remove();
                            }

                            $.each(data.data, function (key, value) {
                                if (key === 'history')
                                    IaEvents.fillFormDataFieldsHistory(ia_agreement_public_disclosure_form, value, 'agreement_public_disclosure');
                                else if (key !== 'other_links')
                                    fillFormDataFields(ia_agreement_public_disclosure_form, key, value, false);
                            });

                            if (is_replicate) {
                                fillFormDataFields(ia_agreement_public_disclosure_form, 'ia_report_id', IaEvents.getIaReportId());
                                fillFormDataFields(ia_agreement_public_disclosure_form, 'reporting_year', IaEvents.getIaReportingYear());
                                fillFormDataFields(ia_agreement_public_disclosure_form, 'ia_agreement_public_disclosure_id', null);
                            }

                            App.unblockUI(parent);
                            $.uniform.update();
                            modal.modal();
                        });
                    } else {
                        IaEvents.prepare_update_block(ia_agreement_public_disclosure_form, {
                            reporting_year: IaEvents.getIaReportingYear(),
                            added_by_name: null,
                            added_date: null,
                            updated_by_name: null,
                            updated_date: null,
                            update_text: null,
                            can_update_ia_report: true,
                            history: {},
                        });
                        ia_agreement_public_disclosure_form.find('.fields_history').remove();
                        App.unblockUI(parent);
                        $.uniform.update();
                        modal.modal();
                    }
                });
            })
            .on('change', '#ia_agreement_public_disclosure_modal select[name=ia_agreement_id]', function () {
                let modal = $(this).parents('.modal:first');
                let related_elements = modal.find('.another_public_disclosure_related');
                related_elements.find('input, select, textarea, button')
                    .filter(':not(.keep_disabled)').prop('disabled', false);

                let ia_agreement_id = $(this).val();
                let ia_agreement_public_disclosure_id = modal.find('input[name=ia_agreement_public_disclosure_id]').val();
                App.blockUI({
                    target: related_elements.find('.form-group'),
                    boxed: true
                });

                $.getJSON(GetIaAgreementRelatedPublicDisclosureDataLink + '/ia_agreement_id/' + ia_agreement_id + '/ia_agreement_public_disclosure_id/' + ia_agreement_public_disclosure_id, function (data) {
                    if (!data.result) {
                        App.unblockUI(related_elements.find('.form-group'));
                        $.uniform.update();
                        return;
                    }

                    $.each(data.data, function (key, value) {
                        fillFormDataFields(related_elements, key, value, false);
                    });
                    related_elements.find('input, select, textarea, button').prop('disabled', true);
                    App.unblockUI(related_elements.find('.form-group'));
                    $.uniform.update();
                });
            })
            .on('change', '#ia_agreement_public_disclosure_modal input[name=no_public_disclosure]', function () {
                let modal = $(this).parents('.modal:first');

                if ($(this).prop('checked')) {
                    modal.find('.no_public_disclosure').slideDown('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                    modal.find('.public_disclosure').slideUp('slow').find('input, select, textarea').prop('disabled', true).prop('checked', false).change();
                    modal.find('.other_public_disclosure_link_rows').slideUp('slow').find('input, select, textarea').prop('disabled', true).prop('checked', false).change();
                } else {
                    modal.find('.no_public_disclosure').slideUp('slow').find('input, select, textarea').prop('disabled', true);
                    modal.find('.public_disclosure').slideDown('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                    modal.find('.other_public_disclosure_link_rows').slideDown('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                }
                $.uniform.update();
            })
            .on('change', '#ia_agreement_public_disclosure_modal input[name=is_public_disclosure_provided]', function () {
                let modal = $(this).parents('.modal:first');

                if (parseInt(modal.find('input[name=is_public_disclosure_provided]:checked').val()) === 0)
                    modal.find('.public_disclosure_not_provided').slideDown('slow').find('input, select, textarea').filter(':not(.keep_disabled)').prop('disabled', false);
                else
                    modal.find('.public_disclosure_not_provided').slideUp('slow').find('input, select, textarea').prop('disabled', true);
                $.uniform.update();
            })
            .on('click', '#ia_agreement_public_disclosure_modal #add_other_public_disclosure_link', function () {
                let last_row = IaEvents.createNewRow('other_public_disclosure_link_row', $(this).parents('.modal:first'), false);
                last_row.find('input[name="other_links[]"]').change();
                $('#ia_agreement_public_disclosure_modal .other_public_disclosure_link_rows_label').slideDown('slow');
            })
            .on('click', '#ia_agreement_public_disclosure_modal .other_public_disclosure_link_rows .delete_other_public_disclosure_link', function () {
                $(this).parents('.other_public_disclosure_link_row:first').fadeOut('slow', function () {
                    $(this).remove();
                    if ($('#ia_agreement_public_disclosure_modal .other_public_disclosure_link_rows .other_public_disclosure_link_row').length === 0)
                        $('#ia_agreement_public_disclosure_modal .other_public_disclosure_link_rows_label').slideUp('slow');
                });
            })
            .on('click', '.btn_delete_ia_agreement_public_disclosure', function () {
                let ia_agreement_public_disclosure_id = $(this).data('id');
                IaEvents.deleteEntity($(this), false, DeleteIaAgreementPublicDisclosureLink + '/ia_agreement_public_disclosure_id/' + ia_agreement_public_disclosure_id, [$('#ia_agreement_public_disclosure_table')]);
            })
            .on('change', '.uploader_input', function () {
                let parent = $(this).parents('.upload_parent:first');
                let file = $(this).val();
                if (file !== '' && file != null) {
                    parent.find('.uploader_button').hide();
                    parent.find('.uploader_view').attr('href', base_url + '/uploads/ia_reports/' + file).attr('download', file.substring(33));
                    parent.find('.uploader_view, .uploader_delete').fadeIn('slow');
                } else {
                    parent.find('.uploader_view, .uploader_delete').hide();
                    parent.find('.uploader_button').fadeIn('slow');
                    parent.find('.uploader_view').attr('href', '').attr('download', '');
                }
                $.uniform.update();
            })
            .on('click', '.uploader_delete', function () {
                $(this).parents('.upload_parent:first').find('input.uploader_input').val('').change();
            })
            .on('change', 'input.possible_url', function () {
                let url = $(this).val();

                if (isValidUrl(url)) {
                    $(this).parent().find('.possible_url_link_button').attr('href', url)
                        .attr('target', '_blank')
                        .popover('destroy');
                } else {
                    $(this).parent().find('.possible_url_link_button').attr('href', 'javascript:void(0);')
                        .attr('data-trigger', 'hover')
                        .attr('data-container', 'body')
                        .attr('data-placement', 'top')
                        .attr('data-content', 'URL is not valid')
                        .attr('target', '')
                        .popover();
                }
            })
            .on('click', '.fields_history', function () {
                IaEvents.show_version_compare($(this).data('compare'));
            })
            .on('click', '.ia_reporting_compare_version', function () {
                $(this).parents('.dropdown:first').data('version', $(this).data('version'));
                $(this).parents('.dropdown:first').data('reporting_year', $(this).data('reporting_year'));

                let parent = $(this).parents('.modal:first');
                let base_version = parent.find('.dropdown.base_version').data('version');
                let base_version_reporting_year = parent.find('.dropdown.base_version').data('reporting_year');
                let compare_version = parent.find('.dropdown.compare_version').data('version');
                let compare_version_reporting_year = parent.find('.dropdown.compare_version').data('reporting_year');
                let entity_id = $(this).data('entity_id');
                let entity_type = $(this).data('entity_type');
                let element_name = $(this).data('element_name');

                App.blockUI({
                    target: $('body'),
                    boxed: true
                });
                bootbox.hideAll();

                $.ajax({
                    url: CompareIaReportingVersionsDataLink,
                    type: 'post',
                    data: {
                        base_version: base_version,
                        base_version_reporting_year: base_version_reporting_year,
                        compare_version: compare_version,
                        compare_version_reporting_year: compare_version_reporting_year,
                        entity_id: entity_id,
                        entity_type: entity_type
                    },
                    success: function (data, status) {
                        App.unblockUI($('body'));
                        if (data.result === false) {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: data.message ? data.message : 'Oops! something went wrong.',  // alert's message
                                closeInSeconds: 5

                            });
                            return;
                        }

                        let diff = data.data.hasOwnProperty('diff') && data.data.diff.hasOwnProperty(element_name) ? data.data.diff[element_name] : null;
                        if (diff == null)
                            diff = 'There isn’t anything to compare.';

                        IaEvents.show_version_compare({
                            element_name: element_name,
                            entity_id: entity_id,
                            entity_type: data.data.entity_type,
                            available_versions: data.data.available_versions,
                            diff: diff,
                            base_version: data.data.base_version,
                            compare_version: data.data.compare_version
                        });
                    },
                    error: function (xhr, desc, err) {
                        App.alert({
                            type: 'danger',  // alert's type
                            message: 'Oops! something went wrong.',  // alert's message
                            closeInSeconds: 5
                        });
                        App.unblockUI($('body'));
                    }
                });
            })
            .on('click', '.form_submit', function () {
                let target_form = $($(this).data('form'));
                let is_review = $(this).data('is_review');
                if (is_review === true) {
                    let user_group_to = $(this).data('user_group_to');

                    App.blockUI({
                        target: $('body'),
                        boxed: true
                    });
                    $.ajax({
                        url: SubmitIaReportReviewLink,
                        type: 'post',
                        data: {
                            user_group_to: user_group_to,
                            ia_report_id: IaEvents.getIaReportId()
                        },
                        success: function (data, status) {
                            if (data.result === false) {
                                App.unblockUI($('body'));
                                if (data.missing_review === true)
                                    bootbox.alert(data.message);
                                else
                                    App.alert({
                                        type: 'danger',  // alert's type
                                        message: data.message ? data.message : 'Oops! something went wrong.',  // alert's message
                                        closeInSeconds: 5

                                    });
                                return;
                            }
                            App.alert({
                                type: 'success',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 5

                            });
                            window.location.pathname = '/ia/iareview';
                        },
                        error: function (xhr, desc, err) {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: 'Oops! something went wrong.',  // alert's message
                                closeInSeconds: 5

                            });
                            App.unblockUI($('body'));
                        }
                    });
                    return;
                } else {
                    if (target_form.attr('id') === 'ia_portfolio_document_form') {
                        let parent = target_form.find('.portfolio_country_rows:visible');
                        if (parent.find('.portfolio_country_row').length === 0) {
                            IaEvents.createNewRow('portfolio_country_row', parent.parents('.portfolio_documents_types:first'), false);
                        }
                    }
                }

                let is_draft = $(this).data('is_draft');
                let submit_review = $(this).data('submit_review');
                target_form.find('input[name=is_draft]').val(is_draft);
                target_form.find('input[name=submit_review]').val(submit_review);
                if (parseInt(is_draft) === 1)
                    target_form.find('input, select, textarea').filter('.required:not(.draft_required)').addClass('draftSubmitIgnore').removeClass('required');
                else
                    target_form.find('.draftSubmitIgnore').addClass('required').removeClass('draftSubmitIgnore');

                target_form.submit();
            });
    };

    const fillFormDataFields = function (form, key, value, is_selectize) {
        try {
            value = JSON.parse(value);
        } catch (e) {
        }

        let element = form.find('*[name=' + cleanStringValue(key) + '], *[name="' + cleanStringValue(key) + '[]"]');

        if (is_selectize) {
            try {
                if (element.length > 0 && element[0].selectize) {
                    element[0].selectize.addOption({value: value, text: value});
                    element[0].selectize.setValue(value, true);
                }
                return;
            } catch (e) {
            }
        }

        if (element.hasClass('date-picker')) {
            element.DatePicker('setDate', value === '' || value == null ? null : createDateUTC(value.toString()));
        } else if (element.is('input[type=radio], input[type=checkbox]')) {
            element.filter('[value="' + cleanStringValue(value) + '"]').prop('checked', true).change();
            $.uniform.update();
        } else {
            element.val(value).change();
        }
    };

    const DisableFormFields = function (form, is_disabled) {
        if (is_disabled === true) {
            form.find('.form-group').find('input, select, textarea')
                .filter(function () {
                    return $(this).parents('.dataTables_wrapper').length === 0
                })
                .prop('disabled', true).addClass('keep_disabled');
            form.find('.form-group').find('a, button')
                .filter(function () {
                    return $(this).parents('.dataTables_wrapper').length === 0
                })
                .prop('disabled', true).addClass('keep_disabled');
            form.find('.form-group')
                .filter(function () {
                    return $(this).parents('.dataTables_wrapper').length === 0
                })
                .find('select.selectize').each(function () {
                if ($(this)[0].selectize)
                    $(this)[0].selectize.disable();
            });
            form.parents('.modal:first').find('.form_submit').prop('disabled', true).hide();
        } else {
            form.find('.form-group').find('input, select, textarea').prop('disabled', false).removeClass('keep_disabled');
            form.find('.form-group').find('a, button').prop('disabled', false).removeClass('keep_disabled');
            form.find('.form-group').find('select.selectize').each(function () {
                if ($(this)[0].selectize)
                    $(this)[0].selectize.enable();
            });
            form.parents('.modal:first').find('.form_submit').prop('disabled', false).show();
        }

        $.uniform.update();
    };

    const askToSubmitIAForm = function () {
        let buttons = {
            close: {
                label: 'Cancel',
                className: 'btn-default',
                callback: function () {
                    //do nothing
                }
            }
        };
        if (parseInt(ia_report_data_json.is_draft) !== 0)
            buttons.draft = {
                label: 'Save IA report as draft',
                className: 'green',
                callback: function () {
                    $('.form_submit[data-form="#ia_form"][data-is_draft="1"]').click();
                }
            };
        buttons.submit = {
            label: 'Submit IA report',
            className: 'green-haze',
            callback: function () {
                $('.form_submit[data-form="#ia_form"][data-is_draft="0"]').click();
            }
        };

        bootbox.dialog({
            message: 'IA report should be saved, please save to continue.',
            buttons: buttons
        });
    };

    const isValidUrl = function (url) {
        //Replace %2F with /
        url = url.split('%2F');
        url = url.join('/');
        //Replace %3A with :
        url = url.split('%3A');
        url = url.join(':');

        return /^(https?|s?ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(url);
    };

    const initForms = function () {
        IaEvents.dynamic_rows.point_of_contact_row = $('#dynamic_point_of_contacts_row .point_of_contact_row').parent().html();
        IaEvents.dynamic_rows.update_row = $('#dynamic_updates_row .update_row').parent().html();
        IaEvents.dynamic_rows.portfolio_country_row = $('#dynamic_portfolio_countries_row .portfolio_country_row').parent().html();
        IaEvents.dynamic_rows.other_public_disclosure_link_row = $('#dynamic_other_public_disclosure_links_row .other_public_disclosure_link_row').parent().html();
        $('#dynamicRows').remove();

        let ia_form = $('#ia_form');
        /**
         * Form validation
         */
        //ia_form
        if (ia_form.length > 0)
            IaEvents.handleValidation(ia_form, 'form-group', [], false, null, function (data, target_modal, target_form) {
                if (!data.result) {
                    if (data.missing_review === true)
                        bootbox.alert(data.message);
                    else if (data.missing_public_disclosures_anticipated_message !== '')
                        bootbox.alert(data.message + '<br><br>' + data.missing_public_disclosures_anticipated_message);
                    return;
                }

                if ((!(parseInt(target_form.find('input[name=ia_report_id]').val()) > 0) || parseInt(data.is_draft) === 0) && parseInt(data.ia_report_id) > 0) {
                    App.blockUI({
                        target: $('body'),
                        boxed: true
                    });

                    window.location.pathname = '/ia/iareporting/ia_report_id/' + data.ia_report_id;
                    return;
                }

                ia_report_data_json.is_draft = data.is_draft;

                if (parseInt(ia_report_data_json.reporting_year) !== parseInt(data.reporting_year)) {
                    ia_report_data_json.reporting_year = data.reporting_year;

                    let tables = [
                        $('#ia_report_crp_table'),
                        $('#ia_management_document_table'),
                        $('#ia_management_other_document_table'),
                        $('#ia_portfolio_document_table_Patent'),
                        $('#ia_portfolio_document_table_PVP'),
                        $('#ia_portfolio_document_table_Trademark'),
                        $('#ia_agreement_table'),
                        $('#ia_agreement_public_disclosure_table')
                    ];

                    $.each(tables, function (index, table) {
                        let url = table.DataTable().ajax.url();
                        let url_array = url.split('/');

                        let reporting_year_index = url_array.indexOf('reporting_year');
                        if (reporting_year_index !== -1)
                            url_array[reporting_year_index + 1] = data.reporting_year;
                        else
                            url_array.push(...['reporting_year', data.reporting_year]);

                        table.DataTable().ajax.url(url_array.join('/')).load();
                    });
                }
            });
        //ia_report_crp_form
        $.validator.addMethod(
            "url_is_valid",
            function (value) {
                return (value === '' || value == null) ? true : isValidUrl(value);
            },
            'Please enter a valid URL.'
        );
        //ia_report_crp_form
        $.validator.addMethod(
            "at_least_one_is_filled",
            function (value, element, param) {
                let valid = false;
                let form = $(element).parents('form:first');

                form.find('.at_least_one_is_filled').each(function () {
                    let input_value = $(this).val();
                    if (input_value != null && input_value !== '')
                        valid = true;
                });
                return valid;
            },
            'Please fill at least one of the fields (1.3.b, 1.3.c, 1.3.d, 1.3.e or 1.3.f)'
        );
        IaEvents.handleValidation($('#ia_report_crp_form'), 'form-group', [$('#ia_report_crp_table')], true, {
            ia_crp_management_capacity: {
                at_least_one_is_filled: true
            },
            ia_crp_management_oversight: {
                at_least_one_is_filled: true
            },
            ia_crp_management_policies: {
                at_least_one_is_filled: true
            },
            ia_crp_management_committees: {
                at_least_one_is_filled: true
            }
        }, null);

        //ia_management_document_form
        $.validator.addMethod(
            "file_exists",
            function (value, element, param) {
                let form = $(element).parents('form:first');

                if (value === 'internal – willing to upload to the restricted CLIPNet repository on non-distribution basis') {
                    let file = form.find('input[name=availability_status_document]').val();
                    return file != null && file !== '';
                }
                return true;
            },
            'Please upload a file.'
        );
        IaEvents.handleValidation($('#ia_management_document_form'), 'form-group', [$('#ia_management_document_table'), $('#ia_management_other_document_table')], true, {
            public_url: {
                url_is_valid: true
            },
            availability_status: {
                file_exists: true
            }
        }, null);

        $.validator.addMethod(
            'portfolio_countries',
            function (value, element) {
                let valid = true;
                let parent = $('#ia_portfolio_document_form .portfolio_country_rows:visible');
                parent.find('.portfolio_country_row').each(function () {
                    $(this).find('.form-group')
                        .removeClass('has-error')
                        .find('span.help-block-error').remove();
                    $(this).find('select').each(function (){
                        if ($(this).val() === '' || $(this).val() == null) {
                            $(this).parents('.form-group:first')
                                .addClass('has-error')
                                .append('<span class="help-block help-block-error">This field is required.</span>');
                            valid = false;
                        }
                    });
                });
                return valid;
            },
            ''
        );
        //ia_portfolio_document_form
        IaEvents.handleValidation($('#ia_portfolio_document_form'), 'form-group', [$('#ia_portfolio_document_table_Patent'), $('#ia_portfolio_document_table_PVP'), $('#ia_portfolio_document_table_Trademark'), $('#ia_agreement_table')], true, {
            "country_id[]": {
                portfolio_countries: true
            }
        }, function (data, target_modal, target_form) {
            let target_select = target_modal.find('.form_submit').data('target_select');
            if (target_select.length > 0 && target_select.is('select') && parseInt(data.ia_portfolio_document_id) > 0) {
                target_select.val('');
                target_select.data('selected_options', data.ia_portfolio_document_id);
                $('#ia_agreement_modal input[name=ip_type]:first').change();
            }
        });
        //ia_agreement_form
        IaEvents.handleValidation($('#ia_agreement_form'), 'form-group', [$('#ia_agreement_table'), $('#ia_agreement_public_disclosure_table')], true, null, null);

        //ia_agreement_public_disclosure_form
        $.validator.addMethod(
            "ia_agreement_public_disclosure_file_or_link",
            function () {
                let valid = false;
                let inputsArray = ['public_disclosure_link', 'public_disclosure_document'];

                $.each(inputsArray, function (key, value) {
                    let input_value = $('#ia_agreement_public_disclosure_form').find('input[name=' + value + ']').val();
                    if (input_value != null && input_value !== '')
                        valid = true;
                });
                return valid;
            },
            'Upload a file or add a link.'
        );
        $.validator.addMethod(
            'other_public_disclosure_link',
            function (value, element) {
                let valid = true;
                $('#ia_agreement_public_disclosure_form .other_public_disclosure_link_row').each(function () {
                    $(this).find('.form-group')
                        .removeClass('has-error')
                        .find('span.help-block-error').remove();
                    let element = $(this).find('input[name="other_links[]"]');
                    if (element.val() === '' || element.val() == null) {
                        $(this).find('.form-group')
                            .addClass('has-error')
                            .append('<span class="help-block help-block-error">This field is required.</span>');
                        valid = false;
                    } else if (!isValidUrl(element.val())) {
                        $(this).find('.form-group')
                            .addClass('has-error')
                            .append('<span class="help-block help-block-error">Please enter a valid Link.</span>');
                        valid = false;
                    }
                });
                return valid;
            },
            ''
        );
        IaEvents.handleValidation($('#ia_agreement_public_disclosure_form'), 'form-group', [$('#ia_agreement_public_disclosure_table')], true, {
            public_disclosure_link: {
                ia_agreement_public_disclosure_file_or_link: true,
                url_is_valid: true
            },
            "other_links[]": {
                other_public_disclosure_link: true
            }
        }, null);

        /**
         * Uploader
         */
        IaEvents.initUpload('upload_availability_status');
        IaEvents.initUpload('upload_ia_agreement_public_disclosure_document');
        IaEvents.initUpload('upload_supplementary_information');

        let parents = $('#ia_form, #ia_report_crp_modal, #ia_management_document_modal, #ia_portfolio_document_modal, #ia_agreement_modal, #ia_agreement_public_disclosure_modal');

        parents.find('input.date-picker').each(function () {
            let options = {
                autoclose: true,
                clearBtn: true
            };
            if ($(this).attr('data-date_format'))
                options.format = $(this).attr('data-date_format');

            if ($(this).attr('data-date_viewMode'))
                options.viewMode = $(this).attr('data-date_viewMode');

            if ($(this).attr('data-date_minViewMode'))
                options.minViewMode = $(this).attr('data-date_minViewMode');

            $(this).DatePicker(options).on('changeDate', function () {
                let date_picker_input = $(this).find('input.date-picker');
                let related_date_pickers = date_picker_input.parents('.modal:first').find('input.date-picker[data-group="' + date_picker_input.data('group') + '"]');
                related_date_pickers.each(function (index, element) {
                    let temp_index = index - 1;
                    let start_date = null;
                    while (start_date == null && related_date_pickers.hasOwnProperty(temp_index)) {
                        start_date = $(related_date_pickers[temp_index]).DatePicker('getDate');
                        temp_index--;
                    }
                    $(this).DatePicker('setStartDate', start_date);

                    temp_index = index + 1;
                    let end_date = null;
                    while (end_date == null && related_date_pickers.hasOwnProperty(temp_index)) {
                        end_date = $(related_date_pickers[temp_index]).DatePicker('getDate');
                        temp_index++;
                    }
                    $(this).DatePicker('setEndDate', end_date);
                });
            });
        });

        parents.find('select.select2User').userSelect2();
        parents.find('select.select2partner').partnerSelect2();
        parents.find('select.select2').select2({allowClear: true});
        parents.find('select.selectize').each(function () {
            $(this).selectize({create: $(this).data('allow_add')});
        });

        comment_box.init({
            HasReviewCommentLink: base_url + '/ia/hasreviewcomments',
            GetAllReviewCommentsDataLink: base_url + '/ia/getallreviewcomments',
            GetReviewCommentDataLink: base_url + '/ia/getreviewcomment',
            SubmitReviewCommentLink: base_url + '/ia/submitreviewcomment',
            DeleteReviewCommentLink: base_url + '/ia/deletereviewcomment'
        }, UserPossibleShareWithGroups);
        parents.find('input.commentable, textarea.commentable, select.commentable').each(function () {
            if ($(this).data('comment_box_auto_add') !== false) {
                const element = $('<i class="fa fa-comments-o add-comment"></i>');
                if ($(this).is('input[type=radio], input[type=checkbox]'))
                    $(this).parents('.radio-list:first, .checkbox-list:first').append(element);
                else
                    element.insertBefore($(this));
            }
            let parent = $(this).parent();
            if ($(this).is('input[type=radio], input[type=checkbox]'))
                parent = $(this).parents('.radio-list:first, .checkbox-list:first');
            comment_box.add_comment_button_reposition(parent);
        });

        //Fill form data
        if (ia_form.length > 0) {
            try {
                let contacts_object = {ia_report_contacts: {}};

                $.each(ia_report_data_json.ia_report_contacts, function (key2, contact) {
                    if (parseInt(contact.is_primary) === 1) {
                        contacts_object.ia_report_contacts[contact.profile_id] = ia_form.find('select[name="ia_report_contacts[]"]:first');
                    } else {
                        contacts_object.ia_report_contacts[contact.profile_id] = IaEvents.createNewRow('point_of_contact_row', $('#ia_form'), false).find('select[name="ia_report_contacts[]"]');
                    }
                });

                $('body').userSelect2Val(contacts_object, function () {
                    $.each(ia_report_data_json, function (key, value) {
                        if (key === 'history')
                            IaEvents.fillFormDataFieldsHistory(ia_form, value, 'ia_report');
                        else if (key !== 'ia_report_contacts')
                            fillFormDataFields(ia_form, key, value);
                    });

                    DisableFormFields(ia_form, ia_report_data_json.can_edit_ia_report === false);
                    ia_form.data('item_id', ia_report_data_json.ia_report_id);
                    comment_box.has_comments(ia_form);
                }, true);
            } catch (e) {
            }
        }
    };

    return {
        //main function to initiate the module
        init: function () {
            initEvents();
            initForms();
        },
        getIaReportId: function () {
            let element = $('#ia_form input[name=ia_report_id]');
            if (element.length > 0)
                return element.val();
            else
                return null;
        },
        getIaReportingYear: function () {
            return $('#ia_form input[name=reporting_year]').val();
        },
        IaReportingYearChanged: function () {
            if (typeof ia_report_data_json === typeof undefined)
                return false;
            return parseInt(ia_report_data_json.reporting_year) !== parseInt(IaEvents.getIaReportingYear());
        },
        deleteEntity: function (element, confirm_message, deleteLink, reload_tables, callBack) {
            let parent = element.parents('.portlet:first, .modal:first');
            App.blockUI({
                target: parent,
                boxed: true
            });

            bootbox.confirm(confirm_message ? confirm_message : 'Are you sure?', function (result) {
                if (result) {
                    $.getJSON(deleteLink, function (data) {
                        if (data.result) {
                            if (data.confirm) {
                                element.data('confirm', data.message);
                                element.click();
                                return;
                            }

                            $.each(reload_tables, function (key, table) {
                                if (table.length !== 0)
                                    table.DataTable().ajax.reload();
                            });
                            if (callBack && $.isFunction(callBack))
                                callBack(parent);

                            App.alert({
                                type: 'success',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 10, // auto close after defined seconds
                            });
                        } else {
                            App.alert({
                                type: 'danger',  // alert's type
                                message: data.message,  // alert's message
                                closeInSeconds: 10, // auto close after defined seconds
                            });
                        }
                        App.unblockUI(parent);
                    });
                } else {
                    element.data('confirm', null);
                    App.unblockUI(parent);
                }
            });
        },
        handleValidation: function (target_form, highlight_parent, reload_tables, close_modal, special_rules, callBack) {
            let target_modal = target_form.parents('.modal:first');
            let target_error = $('.alert-danger', target_form);

            if (typeof special_rules === 'object' && special_rules != null) {

            } else {
                special_rules = {};
            }
            target_form.validate({
                errorElement: 'span', //default input error message container
                errorClass: 'help-block help-block-error', // default input error message class
                focusInvalid: false, // do not focus the last invalid input
                ignore: ':hidden:not(select.selectized)',
                errorPlacement: function (error, element) { // render error placement for each input type
                    if (element.attr("data-error-container")) {
                        $(element.attr("data-error-container")).html(error);
                    } else if (element.parent(".input-group").size() > 0) {
                        error.insertAfter(element.parent(".input-group"));
                    } else if (element.parents('.radio-list').size() > 0) {
                        error.appendTo(element.parents('.radio-list').attr("data-error-container"));
                    } else if (element.parents('.radio-inline').size() > 0) {
                        error.appendTo(element.parents('.radio-inline').attr("data-error-container"));
                    } else if (element.parents('.checkbox-list').size() > 0) {
                        error.appendTo(element.parents('.checkbox-list').attr("data-error-container"));
                    } else if (element.parents('.checkbox-inline').size() > 0) {
                        error.appendTo(element.parents('.checkbox-inline').attr("data-error-container"));
                    } else if (element.is('select')) {
                        element.parent().append(error);
                    } else {
                        error.insertAfter(element);
                    }
                },
                rules: special_rules,
                invalidHandler: function () {
                    target_error.show();
                    setTimeout(function () {
                        App.scrollTo(target_form.find('.has-error:first'), -200);
                    }, 5);
                },
                highlight: function (element) { // hightlight error inputs
                    if ($(element).attr('type') !== 'file' && $(element).attr('name') !== 'country_id[]' && $(element).attr('name') !== 'other_links[]')
                        $(element).parents('.' + highlight_parent + ':first').addClass('has-error'); // set error class to the control group
                },
                unhighlight: function (element) { // revert the change done by hightlight
                    if ($(element).attr('type') !== 'file' && $(element).attr('name') !== 'country_id[]' && $(element).attr('name') !== 'other_links[]')
                        $(element).parents('.' + highlight_parent + ':first').removeClass('has-error'); // set error class to the control group
                },
                submitHandler: function (form) {
                    $(form).ajaxSubmit({
                        success: function (data) {
                            App.unblockUI(target_modal.length ? target_modal : target_form);
                            if (data.result) {
                                if (close_modal && target_modal.length)
                                    target_modal.modal('hide');

                                if (target_form.data('related_table') != null)
                                    reload_tables.push(target_form.data('related_table'));

                                $.each(reload_tables, function (key, table) {
                                    if (table.length !== 0)
                                        table.DataTable().ajax.reload();
                                });

                                App.alert({
                                    type: 'success',  // alert's type
                                    message: data.message,  // alert's message
                                    closeInSeconds: 5, // auto close after defined seconds
                                });
                            } else {
                                if (data.missing_review !== true && data.review_has_related_items !== true && data.has_failed_related_reviews !== true && (!data.hasOwnProperty('missing_public_disclosures_anticipated_message') || data.missing_public_disclosures_anticipated_message === ''))
                                    App.alert({
                                        type: 'danger',  // alert's type
                                        message: data.message,  // alert's message
                                        closeInSeconds: 5, // auto close after defined seconds
                                    });
                            }

                            if (callBack && $.isFunction(callBack))
                                callBack(data, target_modal.length ? target_modal : target_form, target_form);
                        },
                        beforeSubmit: function () {
                            App.blockUI({
                                target: target_modal.length ? target_modal : target_form,
                                boxed: true
                            });
                        }
                    });
                }
            });
        },
        initUpload: function (browse_button) {
            let parent = $('#' + browse_button).parents('.upload_parent:first');
            let uploader = new plupload.Uploader({
                runtimes: 'html5,flash,silverlight,html4',
                browse_button: browse_button,
                url: base_url + '/uploadiareport.php',
                flash_swf_url: base_url + '/assets/global/plugins/plupload/js/Moxie.swf',
                chunk_size: '1mb',
                silverlight_xap_url: base_url + '/assets/global/plugins/plupload/js/Moxie.xap',
                filters: {
                    max_file_size: '100mb',
                    mime_types: [
                        {title: "Image files", extensions: "jpg,jpeg,gif,png,tif"},
                        {title: "Zip files", extensions: "zip"},
                        {title: "archives", extensions: "rar"},
                        {title: "Document files", extensions: "pdf,doc,docx,xls,xlsx,ppt,pptx"}
                    ]
                },

                init: {
                    PostInit: function () {
                    },
                    FilesAdded: function (up, files) {
                        up.start();
                        parent.find('.uploader_info').empty().show();
                        parent.find('.uploader_progress').show().find('.progress-bar').css('width', '0px');
                    },
                    UploadProgress: function (up, file) {
                        parent.find('.uploader_progress .progress-bar').css('width', file.percent + '%');
                        parent.find('.uploader_button').hide();
                    },
                    FileUploaded: function (up, file, info) {
                        parent.find('.uploader_progress').hide();
                        parent.find('.uploader_info').hide();

                        let obj = JSON.parse(info.response);
                        parent.find('input.uploader_input').val(obj.result).change();
                    },
                    Error: function (up, err) {
                        parent.find('.uploader_info').show();
                        parent.find('.uploader_info').html(err.message);
                    }
                }
            });
            uploader.init();
            return uploader
        },
        fillFormDataFieldsHistory: function (parent, dataHistory, type) {
            parent.find('.fields_history.' + type).remove();
            if (!dataHistory.hasOwnProperty('diff'))
                return;
            $.each(dataHistory.diff, function (key, value) {
                let element = parent.find('*[name=' + cleanStringValue(key) + '], *[name="' + cleanStringValue(key) + '[]"]');

                if (value !== '') {
                    let label = element.parents('.form-group:first').find('label:first');
                    if (label.hasClass('fake-label'))
                        label.html('').css('visibility', '');

                    let popover = $('<i class="fa fa-exchange popovers fields_history"></i> ');
                    popover
                        .addClass(type)
                        .data('compare', {
                            element_name: cleanStringValue(key),
                            entity_id: dataHistory.entity_id,
                            entity_type: dataHistory.entity_type,
                            available_versions: dataHistory.available_versions,
                            diff: value,
                            base_version: dataHistory.base_version,
                            compare_version: dataHistory.compare_version
                        })
                        .data('placement', 'right')
                        .data('trigger', 'hover')
                        .data('container', 'body')
                        .data('content', '<div class=\'diff_container\'>' + IaEvents.html_decode(value) + '</div>')
                        .data('title', 'Click on <i class=\'fa fa-exchange fields_history\'></i> to expand and compare other versions');
                    label.prepend(popover);
                }
            });
            parent.find('.fields_history.' + type).popover({html: true});
        },
        show_version_compare: function (compare_data) {
            let available_versions = '';
            if (!compare_data.hasOwnProperty('available_versions'))
                App.alert({
                    type: 'warning',  // alert's type
                    message: 'There isn’t anything to compare.',  // alert's message
                    closeInSeconds: 5

                });

            $.each(compare_data.available_versions, function (index, version) {
                available_versions +=
                    '<li>' +
                    '    <a type="button" class="ia_reporting_compare_version" ' +
                    '           data-version="' + version.version + '" ' +
                    '           data-reporting_year="' + version.reporting_year + '" ' +
                    '           data-entity_id="' + compare_data.entity_id + '" ' +
                    '           data-entity_type="' + compare_data.entity_type + '" ' +
                    '           data-element_name="' + compare_data.element_name + '">' +
                    '        ' + version.reporting_year + ' V' + version.version + ' <i> - ' + version.added_date + ' GMT</i>' +
                    '    </a>' +
                    '</li>';
            });

            let text =
                '<div class="text-center">' +
                '    Comparing' +
                '    <span class="ia_reporting-versions">' +
                '        <div class="dropdown base_version" data-version="' + compare_data.base_version.version + '" data-reporting_year="' + compare_data.base_version.reporting_year + '">' +
                '            <a type="button" data-toggle="dropdown">' +
                '                <i><b>' + compare_data.base_version.reporting_year + ' V' + compare_data.base_version.version + '</b></i>' +
                '            </a>' +
                '            <ul class="dropdown-menu">' + available_versions + '</ul>' +
                '        </div>' +
                '    </span>' +
                '    <br>Created by: <i><b>' + compare_data.base_version.added_by + '</b></i> On: <i><b>' + compare_data.base_version.added_date + ' GMT</b></i>' +
                '    <br>With' +
                '    <span class="ia_reporting-versions">' +
                '        <div class="dropdown compare_version" data-version="' + compare_data.compare_version.version + '" data-reporting_year="' + compare_data.compare_version.reporting_year + '">' +
                '            <a type="button" data-toggle="dropdown">' +
                '                <i><b>' + compare_data.compare_version.reporting_year + ' V' + compare_data.compare_version.version + '</b></i>' +
                '            </a>' +
                '            <ul class="dropdown-menu">' + available_versions + '</ul>' +
                '        </div>' +
                '    </span>' +
                '    <br>Created by: <i><b>' + compare_data.compare_version.added_by + '</b></i> On: <i><b>' + compare_data.compare_version.added_date + ' GMT</b></i>' +
                '</div>';

            let diff = $('<div>' + compare_data.diff + '</div>');
            diff.find('span').each(function () {
                $(this).html($(this).text());
            });

            text += '<div class="diff_container">' + diff.html() + '</div>';
            bootbox.alert(text);
        },
        html_decode: function (string) {
            let e = document.createElement('textarea');
            e.innerHTML = string;
            return e.childNodes.length === 0 ? '' : e.childNodes[0].nodeValue;
        },
        prepare_update_block: function (parent, update) {
            let editable_updates = null;
            let last_row = IaEvents.createNewRow('update_row', parent, false);

            last_row.find('.reporting_year').html(update.reporting_year);

            if (update.added_by_name == null || update.added_by_name === '') {
                last_row.find('.submission_details').remove();
            } else {
                last_row.find('.submission_details .added_by').html(update.added_by_name);
                last_row.find('.submission_details .added_date').html(update.added_date);
            }

            if (update.updated_by_name == null || update.updated_by_name === '') {
                last_row.find('.modification_details').remove();
            } else {
                last_row.find('.modification_details .updated_by').html(update.updated_by_name);
                last_row.find('.modification_details .updated_date').html(update.updated_date);
            }

            last_row.find('textarea[name=update_text]').val(update.update_text);
            if (!update.can_update_ia_report)
                last_row.find('textarea[name=update_text]').prop('disabled', true).addClass('keep_disabled');
            else
                editable_updates = last_row;

            IaEvents.fillFormDataFieldsHistory(last_row, update.history, 'updates');
            return editable_updates;
        },
        storedDynamicRows: {},
        dynamic_rows: {},
        createNewRow: function (type, parent, appendOnce) {
            let last_row = $(IaEvents.dynamic_rows[type]);
            if (!appendOnce) {
                parent.find('.' + type + 's:first').append(last_row);
                IaEvents.reInitiateSelects(last_row);
                last_row.slideDown('slow');
                App.scrollTo(last_row, -200);
                return last_row;
            } else {
                IaEvents.addStoredDynamicRows(type, last_row);
                return last_row;
            }
        },
        appendStoredDynamicRows: function (type, parent) {
            if (IaEvents.storedDynamicRows[type]) {
                let parent = parent.find('.' + type + 's:first');
                parent.append(IaEvents.storedDynamicRows[type])
                    .find('.added_row:hidden').show();
                IaEvents.reInitiateSelects(parent);
            }
        },
        addStoredDynamicRows: function (type, last_row) {
            if (!IaEvents.storedDynamicRows[type])
                IaEvents.storedDynamicRows[type] = $().add(last_row);
            else
                IaEvents.storedDynamicRows[type] = $(IaEvents.storedDynamicRows[type]).add(last_row);
        },
        reInitiateSelects: function (parentElement) {
            parentElement.find('.popovers').popover({html: true});
            parentElement.find('select.select2').select2({allowClear: true});
            parentElement.find('select.selectize').selectize({create: true});
            parentElement.find('select.select2User').userSelect2();
        }
    };
}();