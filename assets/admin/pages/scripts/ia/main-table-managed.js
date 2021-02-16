const MainTableManaged = function () {

    const initIaReportTable = function () {
        let ia_report_table = $('#ia_report_table');
        ia_report_table.dataTable({
            "sAjaxSource": GetAllIaReportsDataLink,
            "sAjaxDataProp": "data",
            "columns": [{
                "mData": "ia_report_id",
                "width": "25px"
            }, {
                "mData": "reporting_year",
                "width": "105px"
            }, {
                "mData": "organization_name"
            }, {
                "mData": "added_by_name",
                "width": "120px"
            }, {
                "mData": "added_date",
                "width": "80px"
            }, {
                "mData": "updated_by_name",
                "width": "120px"
            }, {
                "mData": "updated_date",
                "width": "80px"
            }, {
                "mData": "status",
                "width": "50px"
            }, {
                "mData": "ia_report_id",
                "orderable": false,
                "searchable": false,
                "render": function (data, type, row) {
                    let edit_btn = '<a href="/ia/iareporting/ia_report_id/' + data + '" target="_blank" class="btn default btn-xs blue-stripe">' + (row.can_edit_ia_report ? 'Edit' : 'View') + '</a>';
                    let delete_btn = '<a href="javascript:void(0);" class="btn default btn-xs red-stripe btn_delete_ia_report" data-id="' + data + '">Delete</a>';
                    let pdf_export_btn = '<a href="javascript:void(0);" data-href="/ia/iareportpdf" data-id="' + data + '" class="btn default btn-xs red-stripe export_pdf"><i class="fa fa-file-pdf-o"></i> Export as PDF</a>';
                    let excel_export_btn = '<a href="/ia/iareportagreementsexcel/ia_report_id/' + data + '" class="btn default btn-xs green-stripe export_excel"><i class="fa fa-file-excel-o"></i> Export Section 3 as Excel</a>';

                    if (!row.can_edit_ia_report || parseInt(row.is_draft) !== 1)
                        delete_btn = '';
                    if (!row.can_edit_ia_report && parseInt(row.is_draft) === 1)
                        pdf_export_btn = excel_export_btn = '';

                    return edit_btn + delete_btn + pdf_export_btn + excel_export_btn;
                },
                "width": "100px"
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
            "order": []
        });

        $('body')
            .on('click', '.btn_delete_ia_report', function () {
                let ia_report_id = $(this).data('id');
                MainTableManaged.deleteEntity($(this), false, DeleteIaReportLink + '/ia_report_id/' + ia_report_id, [$('#ia_report_table')]);
            })
            .on('click', '#ia_report_table .export_excel', function (e) {
                e.preventDefault();
                startExporting($(this).attr('href'), {});
            });
    };

    return {
        init: function () {
            initIaReportTable();
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
        }, exportEvents: function () {
            $('body')
                .on('click', '#ia_report_table .export_excel', function (e) {
                    e.preventDefault();
                    startExporting($(this).attr('href'), {});
                })
                .on('click', '.export_pdf', function () {
                    let href = $(this).data('href');
                    let id = $(this).data('id');
                    let reporting_year = $('.ia_report_reporting_year').val();
                    let last_reporting_year = $('.ia_report_last_reporting_year').val();
                    let all_year_export = $(this).data('all_year_export');
                    if (all_year_export) {
                        if (!Array.isArray(reporting_year))
                            reporting_year = [];
                        if (!Array.isArray(last_reporting_year))
                            last_reporting_year = [];
                        reporting_year = reporting_year.concat(last_reporting_year.filter((item) => reporting_year.indexOf(item) < 0))

                        if (reporting_year.length !== 1) {
                            App.alert({
                                type: 'warning',  // alert's type
                                message: 'Please select one reporting year to export the combined year report.',  // alert's message
                                closeInSeconds: 5, // auto close after defined seconds
                            });
                            return;
                        }
                        reporting_year = reporting_year.join(',');
                    } else {
                        reporting_year = '';
                    }

                    let pdf_export_modal = $('#ia_report_pdf_export_modal');
                    pdf_export_modal.find('input:not([type=radio], [type=checkbox])').val('').change();

                    pdf_export_modal.find('input[name=ia_report_id]').val(id);
                    pdf_export_modal.find('input[name=reporting_year]').val(reporting_year);
                    pdf_export_modal.find('.generate_export_pdf_link').data('href', href);

                    pdf_export_modal.find('select.select2').select2();
                    $.uniform.update();

                    pdf_export_modal.modal();
                })
                .on('change', '#ia_report_pdf_export_modal form input[name="sections[]"]', function () {
                    let parent = $(this).parents('.modal:first');

                    if (parent.find('form input[name="sections[]"][value=3]:checked').length > 0) {
                        parent.find('form .section_3_related').slideDown('slow')
                            .find('input').prop('disabled', false);
                    } else {
                        parent.find('form .section_3_related').slideUp('slow')
                            .find('input').prop('disabled', true);
                    }
                    parent.find('form input[name=section_3_review]').change();
                    $.uniform.update();
                })
                .on('change', '#ia_report_pdf_export_modal form input[name=section_3_review]', function () {
                    let parent = $(this).parents('.modal:first');

                    if (parent.find('form input[name=section_3_review][value=1]:checked').length > 0) {
                        parent.find('form .section_3_related_reviews').slideDown('slow')
                            .find('input').prop('disabled', false);
                    } else {
                        parent.find('form .section_3_related_reviews').slideUp('slow')
                            .find('input').prop('disabled', true);
                    }
                    $.uniform.update();
                })
                .on('click', '#ia_report_pdf_export_modal .generate_export_pdf_link', function () {
                    let data = $('#ia_report_pdf_export_modal form').serialize();
                    $('#ia_report_pdf_export_modal .export_pdf_link')
                        .attr('href', $(this).data('href') + '?' + data)
                        .fadeIn('slow');
                })
                .on('change', '#ia_report_pdf_export_modal form .export_options', function () {
                    $('#ia_report_pdf_export_modal .export_pdf_link').fadeOut('slow');
                })
                .on('change', '#ia_report_pdf_export_modal .export_pdf_link', function () {
                    $('#ia_report_pdf_export_modal .export_pdf_link').fadeOut('slow');
                });
        }
    };
}();
