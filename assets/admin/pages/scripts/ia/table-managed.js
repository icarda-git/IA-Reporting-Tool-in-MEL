const TableManaged = function () {
    const hide_non_unique_checkbox = '<div class="checkbox-inline">' +
        '    <label class="checkbox-inline">' +
        '        <input type="checkbox" class="hide_non_unique">' +
        '        Show unique entries only' +
        '    </label>' +
        '</div>';
    const status_radio = '<div style="display: inline; float: right;">' +
        '    <label class="control-label">Review Grade</label>' +
        '    <div class="radio-list">' +
        '        <label class="radio-inline">' +
        '            <input type="radio" class="review_status" name="status" value="-1" checked>' +
        '            All' +
        '        </label>' +
        '        <label class="radio-inline">' +
        '            <input type="radio" class="review_status" name="status" value="">' +
        '            Not graded' +
        '        </label>' +
        '        <label class="radio-inline">' +
        '            <input type="radio" class="review_status" name="status" value="0">' +
        '            Pending' +
        '        </label>' +
        '        <label class="radio-inline">' +
        '            <input type="radio" class="review_status" name="status" value="1">' +
        '            <span class="agreements_label">Unsatisfactory</span>' +
        '            <span class="public_disclosures_label">Improvements recommended</span>' +
        '        </label>' +
        '        <label class="radio-inline public_disclosures_label">' +
        '            <input type="radio" class="review_status" name="status" value="3">' +
        '            <span class="public_disclosures_label">Significant improvements recommended</span>' +
        '        </label>' +
        '        <label class="radio-inline">' +
        '            <input type="radio" class="review_status" name="status" value="2">' +
        '            <span class="agreements_label">Satisfactory</span>' +
        '            <span class="public_disclosures_label">Satisfactory</span>' +
        '        </label>' +
        '    </div>' +
        '</div>';
    const patent_filters = '<div class="col-md-8">' +
        '   <div class="row">' +
        '       <div class="col-md-4">' +
        '           <label class="label-control">Short title</label>' +
        '           <select name="short_title" class="form-control patent_filters" data-target_column="4" multiple' +
        '                   data-placeholder="Select Short title...">' +
        '           </select>' +
        '       </div>' +
        '       <div class="col-md-4">' +
        '           <label class="label-control">Type of Filing</label>' +
        '           <select name="filing_type_1" class="form-control patent_filters" data-target_column="7" multiple' +
        '                   data-placeholder="Select Type of Filing...">' +
        '           </select>' +
        '       </div>' +
        '       <div class="col-md-4">' +
        '           <label class="label-control">Active Status</label>' +
        '           <select name="active_status" class="form-control patent_filters" data-target_column="6" multiple' +
        '                   data-placeholder="Select Status...">' +
        '           </select>' +
        '       </div>' +
        '   </div>' +
        '</div>';

    const tablesAjaxFunction = function (data, callback, settings) {
        let table = $(settings.nTable);
        if (table.data('initialized') !== true)
            callback({data: []});
        else
            $.ajax({
                url: table.data('ajax_url'),
                success: function (data) {
                    callback(data);
                }
            });
    }

    const initIaReportCrpTable = function () {
        let ia_report_crp_table = $('#ia_report_crp_table');
        ia_report_crp_table.data('ajax_url', GetAllIaReportCrpsDataLink);

        ia_report_crp_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "display_id",
                "width": "25px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "crp",
                "render": function (data, type, row) {
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "ia_report_crp_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_report_crp_id', 'btn_edit_ia_report_crp', 'btn_edit_ia_update', 'btn_delete_ia_report_crp');
                },
                "width": "136px"
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
            },
            initComplete: function () {
                ia_report_crp_table.data('initialized', true);
            }
        });
    };

    const initIaManagementDocumentTable = function () {
        let ia_management_document_table = $('#ia_management_document_table');
        ia_management_document_table.data('ajax_url', GetAllIaManagementDocumentsDataLink);

        ia_management_document_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "display_id",
                "width": "25px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "policy_title",
                "render": function (data, type, row) {
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "category",
                "width": "215px"
            }, {
                "mData": "manage_status",
                "width": "200px"
            }, {
                "mData": "ia_management_document_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_management_document_id', 'btn_edit_ia_management_document', 'btn_edit_ia_update', 'btn_delete_ia_management_document');
                },
                "width": "136px"
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (aaData.is_revoked)
                    $(nRow).addClass('revoked');
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
            },
            initComplete: function () {
                ia_management_document_table.data('initialized', true);
            }
        });
    };

    const initIaManagementOtherDocumentTable = function () {
        let ia_management_other_document_table = $('#ia_management_other_document_table');
        ia_management_other_document_table.data('ajax_url', GetAllIaManagementDocumentsDataLink + '/is_other_type/true');

        ia_management_other_document_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "display_id",
                "width": "25px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "policy_title",
                "render": function (data, type, row) {
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "other_document_type"
            }, {
                "mData": "category",
                "width": "215px"
            }, {
                "mData": "manage_status",
                "width": "200px"
            }, {
                "mData": "ia_management_document_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_management_document_id', 'btn_edit_ia_management_document', 'btn_edit_ia_update', 'btn_delete_ia_management_document');
                },
                "width": "136px"
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (aaData.is_revoked)
                    $(nRow).addClass('revoked');
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
            },
            initComplete: function () {
                ia_management_other_document_table.data('initialized', true);
            }
        });
    };

    const initIaPortfolioDocumentTable = function (ip_type) {
        let ia_portfolio_document_table = $('#ia_portfolio_document_table_' + ip_type);
        ia_portfolio_document_table.data('ajax_url', GetAllIaPortfolioDocumentsDataLink + '/ip_type/' + ip_type);

        ia_portfolio_document_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "display_id",
                "width": "25px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "short_title",
                "render": function (data, type, row) {
                    data = data !== '' && data != null ? data : row.portfolio_title;
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "trademark_type",
                "width": "50px",
                "visible": ip_type === 'Trademark'
            }, {
                "mData": "active_status",
                "width": "50px"
            }, {
                "mData": "filing_type_1",
                "width": "110px",
                "visible": ip_type === 'Patent'
            }, {
                "mData": "country",
                "width": "200px"
            }, {
                "mData": "owners",
                "width": "100px",
                "render": function (data, type, row) {
                    if (Array.isArray(data))
                        return data.join(' | ');
                    return '';
                }
            }, {
                "mData": "public_disclosure",
                "width": "80px",
                "render": function (data, type, row) {
                    if (data === 'No public disclosure issued' || data === 'NA')
                        return data;
                    else if (data !== '' && data != null)
                        return '<a href="' + data + '" target="_blank">View</a>';
                    return 'NA';
                },
                "visible": ip_type !== 'Trademark'
            }, {
                "mData": "ia_portfolio_document_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_portfolio_document_id', 'btn_edit_ia_portfolio_document', 'btn_edit_ia_update', 'btn_delete_ia_portfolio_document');
                },
                "width": "80px",
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
            },
            "fnDrawCallback": function (oSettings, json) {
                ia_portfolio_document_table.parents('.dataTables_wrapper:first')
                    .find('.patent_filters').each(function () {
                    let selected = $(this).val();
                    if (selected === '' || selected == null) {
                        let name = $(this).attr('name');
                        let filters_options = [];
                        let filters_values = [];
                        ia_portfolio_document_table.DataTable().rows({filter: 'applied'}).data().map((row) => {
                            if (row.hasOwnProperty(name) && filters_values.indexOf(row[name]) === -1) {
                                filters_values.push(row[name]);
                                filters_options.push('<option value="' + row[name] + '">' + row[name] + '</option>');
                            }
                        });
                        $(this).html(filters_options.join('')).val(selected).select2();
                    }
                });
            },
            initComplete: function () {
                ia_portfolio_document_table.data('initialized', true);
            }
        });

        if (ip_type === 'Patent') {
            ia_portfolio_document_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .parent().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-2').addClass('col-sm-2')
                .next().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-2').addClass('col-sm-2');

            $(patent_filters).insertAfter(ia_portfolio_document_table.parents('.dataTables_wrapper:first').find('.dataTables_length').parent());
            ia_portfolio_document_table.parents('.dataTables_wrapper:first')
                .find('select.patent_filters').select2();
        }
    };

    const initIaAgreementTable = function () {
        let ia_agreement_table = $('#ia_agreement_table');
        ia_agreement_table.data('ajax_url', GetAllIaAgreementsDataLink);

        ia_agreement_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "unique_identifier",
                "width": "200px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "agreement_title",
                "render": function (data, type, row) {
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "review_status",
                "width": "200px"
            }, {
                "mData": "ia_agreement_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_agreement_id', 'btn_edit_ia_agreement', 'btn_edit_ia_update', 'btn_delete_ia_agreement');
                },
                "width": "200px"
            }, {
                "mData": "is_agreement_related",
                "orderable": false,
                "visible": false
            }, {
                "mData": "review_grade",
                "orderable": false,
                "visible": false
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
                if (aaData.hasOwnProperty('cluster_color'))
                    $(nRow).css('background-color', aaData.cluster_color);
            },
            initComplete: function () {
                ia_agreement_table.data('initialized', true);
            }
        });

        if (!isNaN(parseInt(ia_agreement_table.data('contains_non_unique'))))
            ia_agreement_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .append(hide_non_unique_checkbox)
                .find('input[type=checkbox]')
                .data('contains_non_unique', ia_agreement_table.data('contains_non_unique'))
                .uniform();
        if (!isNaN(parseInt(ia_agreement_table.data('contains_review_status')))) {
            ia_agreement_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .append(status_radio)
                .find('input[type=radio]')
                .data('contains_review_status', ia_agreement_table.data('contains_review_status'))
                .uniform();
            ia_agreement_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length').find('.public_disclosures_label').hide();

            ia_agreement_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .parent().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-10').addClass('col-sm-10')
                .next().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-2').addClass('col-sm-2');
        } else {
            ia_agreement_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .parent().removeClass('col-md-10').removeClass('col-sm-10').addClass('col-md-6').addClass('col-sm-6')
                .next().removeClass('col-md-2').removeClass('col-sm-2').addClass('col-md-6').addClass('col-sm-6');
        }
    };

    const initIaAgreementPublicDisclosureTable = function () {
        let ia_agreement_public_disclosure_table = $('#ia_agreement_public_disclosure_table');
        ia_agreement_public_disclosure_table.data('ajax_url', GetAllIaAgreementPublicDisclosuresDataLink);

        ia_agreement_public_disclosure_table.dataTable({
            ajax: tablesAjaxFunction,
            "columns": [{
                "mData": "ia_agreement_public_disclosure_id",
                "width": "25px"
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.min(...data);
                }
            }, {
                "mData": "reporting_years",
                "width": "30px",
                "render": function (data, type, row) {
                    return Math.max(...data);
                }
            }, {
                "mData": "partner_id",
                "visible": false
            }, {
                "mData": "agreement_unique_identifier",
                "render": function (data, type, row) {
                    return (row.has_comments ? '<i class="fa fa-comments" style="font-size: x-large;"></i> ' : '') + data;
                }
            }, {
                "mData": "links",
                "render": function (data, type, row) {
                    if (parseInt(row.no_public_disclosure) === 1) {
                        return '<b>No public disclosure issued</b>';
                    } else {
                        let html = [];
                        $.each(data, function (key, value) {
                            if (data !== '' && data != null) {
                                if (value.name.length > 25)
                                    value.name = value.name.substring(0, 25) + '...';
                                html.push('<a target="_blank" href="' + value.link + '">' + value.name + '</a>');
                            }
                        });
                        return html.join('<hr>');
                    }
                },
                "width": "170px"
            }, {
                "mData": "anticipated_public_disclosure",
                "orderable": true,
                "searchable": true,
                "render": function (data, type, row) {
                    if (parseInt(data) === 1 && row.anticipated_public_disclosure_date != null && row.anticipated_public_disclosure_date !== '')
                        return 'Yes, ' + row.anticipated_public_disclosure_date;
                    else if (parseInt(data) === 2)
                        return 'Yes, not in the near future.';
                    else
                        return 'No';
                },
                "width": "115px"
            }, {
                "mData": "review_status",
                "width": "200px"
            }, {
                "mData": "ia_agreement_public_disclosure_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    return PrepareActionsHTML(data, row, 'ia_agreement_public_disclosure_id', 'btn_edit_ia_agreement_public_disclosure', 'btn_edit_ia_update', 'btn_delete_ia_agreement_public_disclosure');
                },
                "width": "200px"
            }, {
                "mData": "is_agreement_related",
                "orderable": false,
                "visible": false
            }, {
                "mData": "review_grade",
                "orderable": false,
                "visible": false
            }],
            "lengthMenu": [
                [10, 15, 20, -1],
                [10, 15, 20, "All"] // change per page values here
            ],
            // set the initial value
            "pageLength": 10,
            "pagingType": "bootstrap_full_number",
            "language": {
                "lengthMenu": "_MENU_ records",
                "paginate": {
                    "previous": "Prev",
                    "next": "Next",
                    "last": "Last",
                    "first": "First"
                }
            },
            "order": [],
            "fnRowCallback": function (nRow, aaData, iDisplayIndex) {
                if (parseInt(aaData.is_deleted) === 1)
                    $(nRow).addClass('deleted');
                else if (aaData.is_modified === true)
                    $(nRow).addClass('modified');
                else if (aaData.is_new === true)
                    $(nRow).addClass('new');
                if (aaData.hasOwnProperty('cluster_color'))
                    $(nRow).css('background-color', aaData.cluster_color);
            },
            initComplete: function () {
                ia_agreement_public_disclosure_table.data('initialized', true);
            }
        });

        if (!isNaN(parseInt(ia_agreement_public_disclosure_table.data('contains_non_unique'))))
            ia_agreement_public_disclosure_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .append(hide_non_unique_checkbox)
                .find('input[type=checkbox]')
                .data('contains_non_unique', ia_agreement_public_disclosure_table.data('contains_non_unique'))
                .uniform();
        if (!isNaN(parseInt(ia_agreement_public_disclosure_table.data('contains_review_status')))) {
            ia_agreement_public_disclosure_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .append(status_radio)
                .find('input[type=radio]')
                .data('contains_review_status', ia_agreement_public_disclosure_table.data('contains_review_status'))
                .uniform();
            ia_agreement_public_disclosure_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length').find('.agreements_label').hide();

            ia_agreement_public_disclosure_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .parent().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-10').addClass('col-sm-10')
                .next().removeClass('col-md-6').removeClass('col-sm-6').addClass('col-md-2').addClass('col-sm-2');
        } else {
            ia_agreement_public_disclosure_table.parents('.dataTables_wrapper:first')
                .find('.dataTables_length')
                .parent().removeClass('col-md-10').removeClass('col-sm-10').addClass('col-md-6').addClass('col-sm-6')
                .next().removeClass('col-md-2').removeClass('col-sm-2').addClass('col-md-6').addClass('col-sm-6');
        }
    };

    const PrepareActionsHTML = function (data, row, id_field_name, edit_btn_class, update_btn_class, delete_btn_class) {
        let can_edit = false;
        if (row.hasOwnProperty('can_edit_ia_report'))
            can_edit = row.can_edit_ia_report;
        //section 3.1 don't follow the general report can edit
        if (row.hasOwnProperty('can_edit_ia_report_agreement'))
            can_edit = row.can_edit_ia_report_agreement;
        //section 3.2 don't follow the general report can edit
        if (row.hasOwnProperty('can_edit_ia_report_public_disclosure'))
            can_edit = row.can_edit_ia_report_public_disclosure;

        let can_update = false;
        if (!row.hasOwnProperty('can_update_ia_report'))
            row.can_update_ia_report = false;
        if (row.can_update_ia_report)
            can_edit = true;
        //section 3.1 don't follow the general report can update
        if (!row.hasOwnProperty('can_update_ia_report_agreement'))
            row.can_update_ia_report_agreement = false;
        //section 3.2 don't follow the general report can update
        if (!row.hasOwnProperty('can_update_ia_report_public_disclosure'))
            row.can_update_ia_report_public_disclosure = false;

        let can_delete = false;
        if (row.hasOwnProperty('can_delete_ia_report'))
            can_delete = row.can_delete_ia_report;
        //section 3.1 don't follow the general report can delete
        if (row.hasOwnProperty('can_delete_ia_report_agreement'))
            can_delete = row.can_delete_ia_report_agreement;
        //section 3.2 don't follow the general report can delete
        if (row.hasOwnProperty('can_delete_ia_report_public_disclosure'))
            can_delete = row.can_delete_ia_report_public_disclosure;

        if (iaReportReviewPage === true) {
            can_edit = false;
            can_delete = false;
            row.can_update_ia_report = false;
            row.can_update_ia_report_agreement = false;
            row.can_update_ia_report_public_disclosure = false;
        }

        let edit_btn_text = 'Edit';
        if (row.can_update_ia_report_agreement || row.can_update_ia_report_public_disclosure)
            edit_btn_text = 'View/update record';
        else if (!can_edit && !row.can_update_ia_report && !row.can_update_ia_report_agreement && !row.can_update_ia_report_public_disclosure)
            edit_btn_text = 'View record';
        let ia_report_id = IaEvents.getIaReportId();
        let goto_btn = '<a href="/ia/iareporting/ia_report_id/' + row.ia_report_id + '" target="_blank" class="btn default btn-xs purple-stripe">Go to report</a>';
        let edit_btn = edit_btn_class != null ? '<a href="javascript:void(0);" class="btn default btn-xs blue-stripe ' + edit_btn_class + '" data-id="' + data + '" data-ia_report_id="' + ia_report_id + '">' + edit_btn_text + '</a>' : '';
        let replicate_btn = edit_btn_class != null ? '<a href="javascript:void(0);" class="btn default btn-xs yellow-stripe ' + edit_btn_class + '" data-id="' + data + '" data-is_replicate="true" data-ia_report_id="' + ia_report_id + '">Replicate</a>' : '';
        let delete_btn = delete_btn_class != null ? '<a href="javascript:void(0);" class="btn default btn-xs red-stripe ' + delete_btn_class + '" data-id="' + data + '">Delete</a>' : '';

        if (!can_delete)
            delete_btn = '';

        if (!can_edit)
            delete_btn = '';
        if (!can_edit && !row.can_update_ia_report && !row.can_update_ia_report_agreement && !row.can_update_ia_report_public_disclosure)
            replicate_btn = ''

        if (iaReportReviewPage === false)
            goto_btn = '';
        else
            replicate_btn = '';
        return edit_btn + replicate_btn + delete_btn + goto_btn;
    };

    const PrepareUpdatesHTML = function (data) {
        let html = [];
        $.each(data, function (key, value) {
            if (iaReportReviewPage === true)
                value.can_edit_update = false;

            let update_text = '(<b>' + value.reporting_year + '</b>) ' + value.added_date;

            let status_class = '';
            if (parseInt(value.is_deleted) === 1)
                status_class = 'deleted';
            else if (value.is_modified === true)
                status_class = 'modified';
            else if (value.is_new === true)
                status_class = 'new';

            let edit_btn = '<a href="javascript:void(0);" class="btn_edit_ia_update popovers ' + status_class + '" data-trigger="hover" data-container="body" data-placement="top" data-content="' + (value.can_edit_update ? 'Edit update' : 'View update') + '" data-id="' + value.ia_report_update_id + '">' + update_text + ' <i class="fa ' + (value.can_edit_update ? 'fa-edit' : 'fa-eye') + '"></i></a>';
            let delete_btn = '<a href="javascript:void(0);" class="btn_delete_ia_update popovers" data-trigger="hover" data-container="body" data-placement="top" data-content="Delete update" data-id="' + value.ia_report_update_id + '"> <i class="fa fa-trash"></i></a>';
            if (!value.can_edit_update)
                delete_btn = '';

            html.push(edit_btn + delete_btn);
        });
        return html.join('<br>');
    };


    const tables = [
        {
            id: 'ia_report_crp_table',
        },
        {
            id: 'ia_management_document_table',
        },
        {
            id: 'ia_management_other_document_table',
        },
        {
            id: 'ia_portfolio_document_table_Patent',
        },
        {
            id: 'ia_portfolio_document_table_PVP',
        },
        {
            id: 'ia_portfolio_document_table_Trademark',
        },
        {
            id: 'ia_agreement_table',
        },
        {
            id: 'ia_agreement_public_disclosure_table',
        },
    ]

    let window_onscroll = window.onscroll;
    let table_init_timeout = null;
    window.onscroll = function () {
        if (table_init_timeout)
            clearTimeout(table_init_timeout);
        table_init_timeout = setTimeout(function () {
            let page_bottom = $(window).scrollTop() + $(window).height();

            $.each(tables, function (index, table) {
                if (table.id != null && $('#' + table.id).offset().top - 200 < page_bottom) {
                    $('#' + table.id).DataTable().ajax.reload();
                    table.id = null;
                }
            });
        }, 25);

        if (window_onscroll != null && $.isFunction(window_onscroll))
            window_onscroll();
    };

    return {
        init: function () {
            $('table.common_table').each(function () {
                $('#' + $(this).attr('id') + '_holder').html($(this));
            });
            initIaReportCrpTable();
            initIaManagementDocumentTable();
            initIaManagementOtherDocumentTable();
            initIaPortfolioDocumentTable('Patent');
            initIaPortfolioDocumentTable('PVP');
            initIaPortfolioDocumentTable('Trademark');
            initIaAgreementTable();
            initIaAgreementPublicDisclosureTable();

            $('body')
                .on('change', '.table_holder .hide_non_unique', function () {
                    let search_for = $(this).prop('checked') ? '^0$' : '';
                    $(this).parents('.table_holder:first').find('table').DataTable()
                        .column($(this).data('contains_non_unique'))
                        .search(search_for, true, false).draw();
                })
                .on('change', '.table_holder .review_status', function () {
                    let value = $('.table_holder .review_status:checked').val();
                    let search_for = parseInt(value) === -1 ? '' : '^' + value + '$';
                    $(this).parents('.table_holder:first').find('table').DataTable()
                        .column($(this).data('contains_review_status'))
                        .search(search_for, true, false).draw();
                })
                .on('change', '.table_holder .patent_filters', function () {
                    $('.table_holder .patent_filters').each(function () {
                        let target_column = $(this).data('target_column');
                        let value = $(this).val();

                        if ($(this).is('[type=checkbox]'))
                            value = $(this).prop('checked') ? '1' : null;

                        try {
                            value = value.split(',');
                        } catch (e) {
                        }
                        let search_for = '';
                        if (Array.isArray(value)) {
                            value = value.map(v => v.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'));
                            search_for = '^' + value.join('|') + '$';
                        }
                        $(this).parents('.table_holder:first').find('table').DataTable()
                            .column(target_column)
                            .search(search_for, true, false).draw();
                    });
                });
        }
    };
}();
